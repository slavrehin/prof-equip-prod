<?php
/**
 * Импорт контента из Web Archive (data/content.json, собирается archive/extract/extract_content.py)
 * в инфоблоки, созданные bootstrap.php. Идемпотентно: элементы ищутся по CODE; существующие
 * пропускаются (с --update перезаписываются).
 *
 * Картинки: /wp-content/uploads/... берутся из смонтированного архива (docker-compose: ../archive/wayback/images),
 * при отсутствии оригинала — крупнейший сохранённый вариант размера (-768x512 и т.п.).
 * Битрикс-картинок /upload/... в архиве нет — такие пропускаются (список — data/missing_images.txt).
 *
 * Запуск: docker compose exec -T web php /var/www/html/local/bootstrap/import_content.php [--update] [--only=brands,catalog]
 */
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;

Loader::includeModule('iblock');
Loader::includeModule('catalog');
global $DB;
@set_time_limit(0);

$opts = getopt('', ['update', 'only:', 'props-only', 'data:']);
$PROPS_ONLY = isset($opts['props-only']);
$UPDATE = isset($opts['update']);
$ONLY = isset($opts['only']) ? explode(',', $opts['only']) : null;
$data = json_decode(file_get_contents(__DIR__ . '/data/' . ($opts['data'] ?? 'content.json')), true);
// служебные пометки источника (content_b24.json): дубли пропускаем, неподтверждённые публикации — неактивными
foreach ($data as $k => $list) {
    if (!is_array($list)) continue;
    $data[$k] = array_values(array_filter($list, fn($e) => empty($e['extra']['possible_duplicate_of'])));
}
$data += ['brands' => [], 'catalog_sections' => [], 'catalog' => [], 'services' => [], 'news' => [], 'blog' => [], 'projects' => [], 'solution' => []];
$missing = [];

function out($s) { echo $s, "\n"; }
function ib($code) { static $c = []; return $c[$code] ??= (int) CIBlock::GetList([], ['CODE' => $code, 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID']; }

/** Путь на сайте -> массив файла для Битрикса или null. */
function fileArr($url)
{
    global $missing;
    if (!$url) return null;
    $root = $_SERVER['DOCUMENT_ROOT'];
    $url = rawurldecode(parse_url($url, PHP_URL_PATH) ?? '');
    $f = str_starts_with($url, '/mnt/') ? $url : $root . $url;
    if (!is_file($f) && str_starts_with($url, '/wp-content/')) {
        $info = pathinfo($f);
        $best = null;
        foreach (glob($info['dirname'] . '/' . glob_escape($info['filename']) . '-*x*.' . $info['extension']) ?: [] as $cand) {
            if (preg_match('/-\d+x\d+$/', pathinfo($cand, PATHINFO_FILENAME)) && (!$best || filesize($cand) > filesize($best))) $best = $cand;
        }
        $f = $best;
    }
    if (!$f || !is_file($f)) {
        $missing[$url] = true;
        return null;
    }
    $a = CFile::MakeFileArray($f);
    $a['MODULE_ID'] = 'iblock';
    return $a;
}
function glob_escape($s) { return preg_replace('/([*?\[\]])/', '[$1]', $s); }

function slug($text)
{
    $s = CUtil::translit(trim($text), 'ru', ['max_len' => 90, 'change_case' => 'L', 'replace_space' => '-', 'replace_other' => '-', 'delete_repeat_replace' => true]);
    return trim($s, '-') ?: md5($text);
}

/* ---------------------------------------------------------------- свойства и варианты списков */
function propsOf($iblockId, $reset = false)
{
    static $cache = [];
    if ($reset || !isset($cache[$iblockId])) {
        $cache[$iblockId] = [];
        $r = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CHECK_PERMISSIONS' => 'N']);
        while ($p = $r->Fetch()) $cache[$iblockId][$p['CODE']] = $p;
    }
    return $cache[$iblockId];
}
function enumId($prop, $text, $slug)
{
    static $enums = [];
    $pid = (int) $prop['ID'];
    if (!isset($enums[$pid])) {
        $enums[$pid] = ['x' => [], 'v' => []];
        $r = CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $pid]);
        while ($e = $r->Fetch()) { $enums[$pid]['x'][$e['XML_ID']] = $e['ID']; $enums[$pid]['v'][mb_strtolower($e['VALUE'])] = $e['ID']; }
    }
    $slug = $slug ?: slug($text);
    if (isset($enums[$pid]['x'][$slug])) return $enums[$pid]['x'][$slug];
    if (isset($enums[$pid]['v'][mb_strtolower($text)])) return $enums[$pid]['v'][mb_strtolower($text)];
    $id = (new CIBlockPropertyEnum())->Add(['PROPERTY_ID' => $pid, 'VALUE' => $text, 'XML_ID' => $slug, 'SORT' => 500]);
    if (!$id) throw new RuntimeException("Вариант «{$text}» свойства {$prop['CODE']} не создан");
    $enums[$pid]['x'][$slug] = $id;
    $enums[$pid]['v'][mb_strtolower($text)] = $id;
    return $id;
}

/** Название характеристики WordPress -> CODE свойства каталога. */
function propCodeByName($name)
{
    static $map = null;
    $n = fn($s) => preg_replace('/[^a-zа-я0-9]+/u', '', str_replace('ё', 'е', mb_strtolower($s)));
    if ($map === null) {
        $map = [];
        foreach (CatalogStandard::PROPERTY_NAMES as $c => $nm) $map[$n($nm)] = $c;
        foreach (file(__DIR__ . '/archive_prop_codes.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
            [$c, $nm] = explode("\t", $l . "\t");
            $map[$n($nm)] ??= strtoupper($c);
        }
        foreach (propsOf(ib('catalog')) as $c => $p) $map[$n($p['NAME'])] ??= $c;
        $map[$n('Бренд')] = 'PROIZVODITEL';
        $map[$n('Страна производитель')] = 'COUNTRY';
        $map[$n('Страна')] = 'COUNTRY';
    }
    return $map[$n($name)] ?? null;
}

/* ---------------------------------------------------------------- общий upsert элемента */
function upsert($ibCode, array $e, array $fields, array $props = [])
{
    global $UPDATE;
    $iblockId = ib($ibCode);
    $row = CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, '=CODE' => $e['code'], 'CHECK_PERMISSIONS' => 'N'], false, false, ['ID', 'XML_ID'])->Fetch();
    // заглушки (бренды из краула без описания) обновляем всегда
    if ($row && !$UPDATE && !str_starts_with((string) $row['XML_ID'], 'stub:')) return [(int) $row['ID'], false];
    $seo = array_filter([
        'ELEMENT_META_TITLE' => $e['seo']['title'] ?? '', 'ELEMENT_META_DESCRIPTION' => $e['seo']['description'] ?? '',
        'ELEMENT_META_KEYWORDS' => $e['seo']['keywords'] ?? '',
    ]);
    $f = $fields + ['IBLOCK_ID' => $iblockId, 'CODE' => $e['code'], 'NAME' => $e['name'], 'ACTIVE' => 'Y',
        'XML_ID' => ($e['source'] === 'b24' ? 'b24:' : 'wayback:') . ($e['snapshot'] ?: $e['source']), 'IPROPERTY_TEMPLATES' => $seo];
    if (($e['extra']['active'] ?? true) === false || ($e['extra']['publication_confirmed'] ?? true) === false) $f['ACTIVE'] = 'N';
    if (!empty($e['active_from'])) $f['ACTIVE_FROM'] = ConvertTimeStamp(strtotime($e['active_from']), 'FULL');
    $el = new CIBlockElement();
    if ($row) {
        if (!$el->Update($row['ID'], $f)) throw new RuntimeException("$ibCode/{$e['code']}: " . $el->LAST_ERROR);
        if ($props) CIBlockElement::SetPropertyValuesEx($row['ID'], $iblockId, $props);
        return [(int) $row['ID'], true];
    }
    $f['PROPERTY_VALUES'] = $props;
    $id = $el->Add($f, false, false, false);
    if (!$id) {
        // не роняем весь импорт из-за одного элемента
        out("  ! $ibCode/{$e['code']}: " . strip_tags($el->LAST_ERROR));
        return [0, false];
    }
    return [(int) $id, true];
}

function textFields(array $e, $previewFromDetail = true)
{
    $f = ['DETAIL_TEXT' => $e['detail_html'], 'DETAIL_TEXT_TYPE' => 'html'];
    if ($e['preview_html'] ?? '') {
        $f['PREVIEW_TEXT'] = $e['preview_html']; $f['PREVIEW_TEXT_TYPE'] = 'html';
    } elseif ($previewFromDetail) {
        $txt = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($e['detail_html']))));
        $f['PREVIEW_TEXT'] = mb_strlen($txt) > 300 ? mb_substr($txt, 0, mb_strrpos(mb_substr($txt, 0, 300), ' ')) . '…' : $txt;
        $f['PREVIEW_TEXT_TYPE'] = 'text';
    }
    if ($p = fileArr($e['picture'])) { $f['PREVIEW_PICTURE'] = $p; $f['DETAIL_PICTURE'] = fileArr($e['picture']); }
    return $f;
}
function gallery(array $urls)
{
    $v = [];
    foreach ($urls as $u) if ($a = fileArr($u)) $v['n' . count($v)] = ['VALUE' => $a, 'DESCRIPTION' => ''];
    return $v;
}
function want($k) { global $ONLY; return !$ONLY || in_array($k, $ONLY, true); }
function report($k, $n, $all) { out(sprintf('%-10s создано/обновлено %d из %d', $k, $n, $all)); }

/* ---------------------------------------------------------------- бренды */
$brandByName = [];
if (want('brands')) {
    $n = 0;
    foreach ($data['brands'] as $e) {
        $f = textFields($e, false);
        $f['PREVIEW_TEXT'] = $e['detail_html']; $f['PREVIEW_TEXT_TYPE'] = 'html';
        $props = [];
        if ($logo = fileArr($e['picture'])) $props['LOGO'] = $logo;
        [$id, $ch] = upsert('brands', $e, $f, $props);
        $n += $ch;
    }
    report('brands', $n, count($data['brands']));
}
$r = CIBlockElement::GetList([], ['IBLOCK_ID' => ib('brands')], false, false, ['ID', 'NAME', 'CODE']);
while ($b = $r->Fetch()) {
    $brandByName[mb_strtolower($b['NAME'])] = $b['ID'];
    $brandByName[mb_strtolower($b['CODE'])] = $b['ID'];
}

/* ---------------------------------------------------------------- разделы каталога: имена, родители, SEO, новые */
$catId = ib('catalog');
$secByCode = [];
$loadSecs = function () use ($catId, &$secByCode) {
    $secByCode = [];
    $r = CIBlockSection::GetList([], ['IBLOCK_ID' => $catId, 'CHECK_PERMISSIONS' => 'N'], false, ['ID', 'CODE', 'DEPTH_LEVEL']);
    while ($s = $r->Fetch()) $secByCode[$s['CODE']] = $s;
};
$loadSecs();
if (want('sections')) {
    $bs = new CIBlockSection();
    $n = 0;
    foreach ($data['catalog_sections'] as $c) {
        $name = $c['name'] && $c['name'] !== 'Каталог товаров' ? $c['name'] : null;
        $parent = end($c['extra']['parents']) ?: null;
        $f = ['IPROPERTY_TEMPLATES' => array_filter(['SECTION_META_TITLE' => $c['seo']['title'], 'SECTION_META_DESCRIPTION' => $c['seo']['description']])];
        if (!isset($secByCode[$c['code']])) {
            if (!$name) continue;
            $f += ['IBLOCK_ID' => $catId, 'CODE' => $c['code'], 'NAME' => $name, 'ACTIVE' => 'Y',
                'IBLOCK_SECTION_ID' => $parent && isset($secByCode[$parent]) ? $secByCode[$parent]['ID'] : false];
            if ($bs->Add($f, false)) { $n++; out("  новый раздел {$c['code']}"); }
            continue;
        }
        $bs->Update($secByCode[$c['code']]['ID'], $f, false);
        $n++;
    }
    CIBlockSection::ReSort($catId);
    $loadSecs();
    report('sections', $n, count($data['catalog_sections']));
}

/* ---------------------------------------------------------------- товары */
if (want('catalog')) {
    $props = propsOf($catId);
    $unknown = [];
    $n = 0;
    foreach ($data['catalog'] as $i => $e) {
        $pv = [];
        foreach ($e['props'] as $key => $vals) {
            $code = $key[0] === '#' ? propCodeByName(substr($key, 1)) : $key;
            if ((!$code || !isset($props[$code])) && $key[0] === '#') {
                // характеристики WordPress, которых нет в словаре: заводим новое свойство-список
                $nm = mb_convert_case(mb_strtolower(substr($key, 1)), MB_CASE_TITLE);
                $nm = mb_strtoupper(mb_substr($nm, 0, 1)) . mb_strtolower(mb_substr($nm, 1));
                $code = strtoupper(str_replace('-', '_', slug(preg_replace('/,.*$/u', '', $nm) . ' ' . preg_replace('/^[^,]*,?\s*/u', '', $nm))));
                $code = substr(trim($code, '_'), 0, 50);
                // TSVET/COLOR/CVET шаблон фильтра обрабатывает через App\ProductArticleHelper, которого нет в репозитории
                if (in_array($code, ['TSVET', 'COLOR', 'CVET'], true)) $code .= '_IZDELIYA';
                if (!isset($props[$code])) {
                    $pp = new CIBlockProperty();
                    if ($pp->Add(['IBLOCK_ID' => $catId, 'CODE' => $code, 'NAME' => $nm, 'PROPERTY_TYPE' => 'L', 'SMART_FILTER' => 'Y', 'SORT' => 2000])) {
                        out("  новое свойство $code «{$nm}»");
                        $props = propsOf($catId, true);
                    }
                }
            }
            if (!$code || !isset($props[$code])) { $unknown[$key] = ($unknown[$key] ?? 0) + 1; continue; }
            $p = $props[$code];
            if ($p['PROPERTY_TYPE'] === 'L') {
                $ids = [];
                if ($code === 'TIP_PREDPRIYATIYA') {
                    // в Bitrix24 «Тип предприятия» — одна строка через запятую: режем на отдельные значения
                    // и приводим к стандарту («Прачечная в больнице» → «Прачечная больницы»)
                    $split = [];
                    foreach ($vals as $v) foreach (preg_split('/,\s*/u', $v['text']) as $t) {
                        $t = trim($t);
                        if ($t === '') continue;
                        $t = mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
                        $t = CatalogStandard::VALUE_MAP['TIP_PREDPRIYATIYA'][$t] ?? $t;
                        $split[$t] = ['text' => $t, 'slug' => ''];
                    }
                    $vals = array_values($split);
                }
                foreach ($vals as $v) if (trim($v['text']) !== '') $ids[] = enumId($p, trim($v['text']), $v['slug']);
                if ($ids) $pv[$code] = $p['MULTIPLE'] === 'Y' ? $ids : $ids[0];
            } elseif ($p['PROPERTY_TYPE'] === 'N') {
                // после миграции 10-13-catalog-04 часть характеристик — числа (ползунок)
                if (preg_match('/\d+(?:[.,]\d+)?/', $vals[0]['text'], $nm)) $pv[$code] = (float) str_replace(',', '.', $nm[0]);
            } elseif ($p['PROPERTY_TYPE'] === 'S') {
                $pv[$code] = $vals[0]['text'];
            }
            if ($code === 'PROIZVODITEL') {
                $bid = $brandByName[mb_strtolower(trim($vals[0]['text']))] ?? $brandByName[slug($vals[0]['text'])] ?? null;
                if ($bid) $pv['BRAND'] = [$bid];
            }
        }
        if ($PROPS_ONLY) {
            $row = CIBlockElement::GetList([], ['IBLOCK_ID' => $catId, '=CODE' => $e['code']], false, false, ['ID'])->Fetch();
            if ($row && $pv) { CIBlockElement::SetPropertyValuesEx($row['ID'], $catId, $pv); $n++; }
            continue;
        }
        if ($g = gallery($e['gallery'])) $pv['GALLERY'] = $g;
        $secs = array_values(array_unique(array_merge($e['extra']['listing_sections'] ?? [], $e['sections'])));
        if (!$secs && preg_match('/мебел/ui', (string) ($e['extra']['section_hint'] ?? ''))) $secs = ['mebel'];
        $secIds = [];
        foreach ($secs as $sc) if (isset($secByCode[$sc])) $secIds[$sc] = $secByCode[$sc];
        uasort($secIds, fn($a, $b) => $b['DEPTH_LEVEL'] <=> $a['DEPTH_LEVEL']);   // самый глубокий — основной
        $f = textFields($e, false);
        if ($secIds) { $f['IBLOCK_SECTION'] = array_column($secIds, 'ID'); $f['IBLOCK_SECTION_ID'] = reset($secIds)['ID']; }
        else { $f['ACTIVE'] = 'N'; }
        [$id, $ch] = upsert('catalog', $e, $f, $pv);
        if ($id && $ch && !CCatalogProduct::GetByID($id)) CCatalogProduct::Add(['ID' => $id, 'QUANTITY' => 0, 'TYPE' => 1]);
        $n += $ch;
        if ($i % 100 === 0) out("  товары: {$i}…");
    }
    report('catalog', $n, count($data['catalog']));
    arsort($unknown);
    file_put_contents(__DIR__ . '/data/unknown_props.txt', implode("\n", array_map(fn($k, $v) => "$v\t$k", array_keys($unknown), $unknown)));
    out('  нераспознанных характеристик: ' . count($unknown) . ' (data/unknown_props.txt)');
}

/* ---------------------------------------------------------------- контентные инфоблоки */
foreach (['services', 'news', 'blog', 'projects', 'solution'] as $k) {
    if (!want($k)) continue;
    $n = 0;
    foreach ($data[$k] as $e) {
        $f = textFields($e);
        $pv = [];
        if (in_array($k, ['news', 'projects', 'solution'], true) && ($g = gallery($e['gallery']))) $pv['GALLERY'] = $g;
        [$id, $ch] = upsert($k, $e, $f, $pv);
        $n += $ch;
    }
    report($k, $n, count($data[$k]));
}

file_put_contents(__DIR__ . '/data/missing_images.txt', implode("\n", array_keys($missing)));
out('картинок нет в архиве: ' . count($missing) . ' (data/missing_images.txt)');
foreach (['brands', 'catalog', 'services', 'news', 'blog', 'projects', 'solution'] as $k) {
    $c = $DB->Query('SELECT COUNT(*) C, SUM(PREVIEW_PICTURE IS NOT NULL) P FROM b_iblock_element WHERE IBLOCK_ID=' . ib($k))->Fetch();
    out(sprintf('в базе %-9s %5d элементов, с картинкой %d', $k, $c['C'], $c['P']));
}
