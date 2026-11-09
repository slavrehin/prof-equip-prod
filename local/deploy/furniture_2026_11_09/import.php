<?php
/**
 * Мебель Rugiano и Visionnaire (2026-11-09): 2 карточки брендов (инфоблок 14, раздел brands-furniture)
 * и 10 товаров каталога — по одному на подраздел «Мебели» (диваны, кресла, стулья, светильники, шезлонги).
 * Данные — products.json (собирает build_data.py), картинки — img/<slug>/1..5.jpg, логотипы — brands/.
 *
 * Запуск (локально в контейнере, на dev и на проде — из корня сайта):
 *   php local/deploy/furniture_2026_11_09/import.php [--dry]
 *
 * Идемпотентен: бренд и товар ищутся по CODE и обновляются; картинки/логотип загружаются, только если
 * их ещё нет. Значения свойств-списков создаются, если их нет (как у остальной мебели — по одному
 * значению на вариант). Цен нет («Запросить стоимость»). Картинки элементов — прямым SQL
 * (см. «Известные грабли» в CLAUDE.md).
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\CModule::IncludeModule('iblock');
\CModule::IncludeModule('catalog');
global $DB;

$dry = in_array('--dry', $argv, true);
const IBLOCK_ID = 11;
const BRANDS_IBLOCK_ID = 14;
const ROOT_SECTION = 'mebel';
const BRAND_SECTION = 'brands-furniture';
$dir = __DIR__;
$data = json_decode(file_get_contents("$dir/products.json"), true, 512, JSON_THROW_ON_ERROR);

function saveFile(string $path, string $alt): int
{
    if (!is_file($path)) { throw new RuntimeException("Нет файла $path"); }
    $arr = \CFile::MakeFileArray($path);
    $arr['description'] = $alt;
    $arr['MODULE_ID'] = 'iblock';
    $id = \CFile::SaveFile($arr, 'iblock');
    if (!$id) { throw new RuntimeException("Файл $path не сохранён"); }
    return (int) $id;
}

$enumCache = [];
function enumId(array $prop, string $value, bool $dry): ?int
{
    global $enumCache;
    $key = $prop['ID'] . '|' . $value;
    if (isset($enumCache[$key])) { return $enumCache[$key]; }
    $e = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $prop['ID'], 'VALUE' => $value])->Fetch();
    if ($e) { return $enumCache[$key] = (int) $e['ID']; }
    if ($dry) { echo "  [dry] новое значение {$prop['CODE']} = «{$value}»\n"; return null; }
    $id = (int) (new \CIBlockPropertyEnum())->Add(['PROPERTY_ID' => $prop['ID'], 'VALUE' => $value,
        'XML_ID' => md5($prop['CODE'] . '|' . $value), 'SORT' => 500]);
    if (!$id) { throw new RuntimeException("Не создано значение {$prop['CODE']} = {$value}"); }
    return $enumCache[$key] = $id;
}

function setSeo(int $iblock, int $id, array $seo): void
{
    (new \Bitrix\Iblock\InheritedProperty\ElementTemplates($iblock, $id))
        ->set(['ELEMENT_META_TITLE' => $seo['title'], 'ELEMENT_META_DESCRIPTION' => $seo['description']]);
    (new \Bitrix\Iblock\InheritedProperty\ElementValues($iblock, $id))->clearValues();
}

// --- справочники ------------------------------------------------------------------------
$props = [];
foreach ([IBLOCK_ID, BRANDS_IBLOCK_ID] as $ib) {
    $rs = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $ib]);
    while ($p = $rs->Fetch()) { $props[$ib][$p['CODE']] = $p; }
}
$root = \CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => ROOT_SECTION])->Fetch()
    or throw new RuntimeException('Нет раздела ' . ROOT_SECTION);
$sections = [];
$rs = \CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'SECTION_ID' => $root['ID']]);
while ($s = $rs->Fetch()) { $sections[$s['CODE']] = (int) $s['ID']; }
$brandSection = \CIBlockSection::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK_ID, 'CODE' => BRAND_SECTION])->Fetch()
    or throw new RuntimeException('Нет раздела брендов ' . BRAND_SECTION);
$tipMebel = enumId($props[IBLOCK_ID]['TIP_TOVARA'], 'Мебель', $dry);

// --- бренды -----------------------------------------------------------------------------
$brandIds = [];
foreach ($data['brands'] as $b) {
    $existing = \CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK_ID, 'CODE' => $b['code']], false, false,
        ['ID', 'PROPERTY_LOGO'])->Fetch();
    if ($dry) { echo "[dry] бренд {$b['code']}: " . ($existing ? "обновить ID={$existing['ID']}" : 'создать') . "\n"; $brandIds[$b['code']] = (int) ($existing['ID'] ?? 0); continue; }
    $fields = [
        'IBLOCK_ID' => BRANDS_IBLOCK_ID, 'IBLOCK_SECTION_ID' => (int) $brandSection['ID'],
        'NAME' => $b['name'], 'CODE' => $b['code'], 'ACTIVE' => 'Y', 'SORT' => 500,
        'PREVIEW_TEXT' => $b['html'], 'PREVIEW_TEXT_TYPE' => 'html',
        'DETAIL_TEXT' => $b['html'], 'DETAIL_TEXT_TYPE' => 'html',
    ];
    $el = new \CIBlockElement();
    if ($existing) {
        $id = (int) $existing['ID'];
        $el->Update($id, $fields) or throw new RuntimeException("бренд {$b['code']}: " . $el->LAST_ERROR);
        $status = 'обновлён';
    } else {
        $id = (int) $el->Add($fields) or throw new RuntimeException("бренд {$b['code']}: " . $el->LAST_ERROR);
        $status = 'создан';
    }
    $pv = ['WEBSITE_URL' => $b['site']];
    if (!$existing || empty($existing['PROPERTY_LOGO_VALUE'])) {
        $f = \CFile::MakeFileArray("$dir/brands/{$b['logo']}");
        $f['MODULE_ID'] = 'iblock';
        $f['description'] = "Логотип {$b['name']}";
        $pv['LOGO'] = $f;
    }
    \CIBlockElement::SetPropertyValuesEx($id, BRANDS_IBLOCK_ID, $pv);
    setSeo(BRANDS_IBLOCK_ID, $id, $b['seo']);
    $brandIds[$b['code']] = $id;
    echo "бренд {$b['name']} $status → ID=$id\n";
}

// --- товары -----------------------------------------------------------------------------
foreach ($data['products'] as $p) {
    $secId = $sections[$p['section']] ?? throw new RuntimeException("{$p['slug']}: нет подраздела {$p['section']}");
    foreach ($p['images'] as $img) {
        if (!is_file("$dir/img/{$img['file']}")) { throw new RuntimeException("{$p['slug']}: нет {$img['file']}"); }
    }
    $pv = ['BRAND' => [$brandIds[$p['brand']]], 'TIP_TOVARA' => $tipMebel];
    foreach ($p['props'] as $code => $value) {
        $prop = $props[IBLOCK_ID][$code] ?? throw new RuntimeException("{$p['slug']}: нет свойства $code");
        $eid = enumId($prop, $value, $dry);
        $pv[$code] = $prop['MULTIPLE'] === 'Y' ? [$eid] : $eid;
    }
    if ($dry) { echo "[dry] {$p['slug']} → {$p['section']}, " . count($p['props']) . ' хар., ' . count($p['images']) . " фото\n"; continue; }

    $groups = [$secId, (int) $root['ID']];
    $fields = [
        'IBLOCK_ID' => IBLOCK_ID, 'IBLOCK_SECTION_ID' => $secId, 'IBLOCK_SECTION' => $groups,
        'NAME' => $p['name'], 'CODE' => $p['slug'], 'ACTIVE' => 'Y', 'SORT' => 500,
        'PREVIEW_TEXT' => $p['preview_html'], 'PREVIEW_TEXT_TYPE' => 'html',
        'DETAIL_TEXT' => $p['description_html'], 'DETAIL_TEXT_TYPE' => 'html',
    ];
    $el = new \CIBlockElement();
    $existing = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $p['slug']], false, false, ['ID', 'DETAIL_PICTURE'])->Fetch();
    if ($existing) {
        $id = (int) $existing['ID'];
        $el->Update($id, $fields) or throw new RuntimeException("{$p['slug']}: " . $el->LAST_ERROR);
        $status = 'обновлён';
    } else {
        $fields['PROPERTY_VALUES'] = [];
        $id = (int) $el->Add($fields) or throw new RuntimeException("{$p['slug']}: " . $el->LAST_ERROR);
        $status = 'создан';
    }
    if (!\CCatalogProduct::GetByID($id)) {
        \CCatalogProduct::Add(['ID' => $id, 'QUANTITY' => 0, 'MEASURE' => 796]);
    }
    \CIBlockElement::SetPropertyValuesEx($id, IBLOCK_ID, $pv);
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(IBLOCK_ID, $id);

    if (!$existing || !$existing['DETAIL_PICTURE']) {
        $fileIds = [];
        foreach ($p['images'] as $img) { $fileIds[] = saveFile("$dir/img/{$img['file']}", $img['alt']); }
        $main = array_shift($fileIds);
        $gal = (int) $props[IBLOCK_ID]['GALLERY']['ID'];
        $DB->Query("UPDATE b_iblock_element SET PREVIEW_PICTURE=$main, DETAIL_PICTURE=$main, TIMESTAMP_X=NOW() WHERE ID=$id");
        $DB->Query("DELETE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID=$gal");
        foreach ($fileIds as $fid) {
            $DB->Query("INSERT INTO b_iblock_element_property (IBLOCK_PROPERTY_ID, IBLOCK_ELEMENT_ID, VALUE, VALUE_TYPE, VALUE_NUM) VALUES ($gal, $id, '$fid', 'text', $fid)");
        }
    }
    setSeo(IBLOCK_ID, $id, $p['seo']);

    // проверка по базе
    $chk = $DB->Query("SELECT IBLOCK_SECTION_ID, DETAIL_PICTURE FROM b_iblock_element WHERE ID=$id")->Fetch();
    $cnt = (int) $DB->Query("SELECT COUNT(*) c FROM b_iblock_section_element WHERE IBLOCK_ELEMENT_ID=$id")->Fetch()['c'];
    if ((int) $chk['IBLOCK_SECTION_ID'] !== $secId || $cnt !== 2 || !$chk['DETAIL_PICTURE']) {
        throw new RuntimeException("{$p['slug']}: раздел/картинка записаны неверно (" . json_encode($chk) . ", привязок $cnt)");
    }
    echo "$status {$p['slug']} → ID=$id\n";
}

// --- прежняя мебель: привязки к подразделам ----------------------------------------------
// При восстановлении после потери Beget мебель попала только в корень «Мебели», и подразделы
// показывали пустоту. Состав подразделов на проде — по их SEO-текстам («Что представлено»).
// Привязка только добавляется (основной раздел не меняется); буфет, стол и столик остаются в корне.
const LEGACY = [
    'divany' => ['dvukhmestnyy-divan-premium-klassa-dlya-otkrytykh-terras-i-launzh-zon-manutti-yiko',
        'divan-giorgetti-lorelei', 'launzh-divan-pedrali-lamorisse-3682', 'tsentralnyy-modul-divana-kettal-tilos',
        'modulnyy-dizaynerskiy-divan-tacchini-solar-xl'],
    'kresla' => ['premialnoe-launzh-kreslo-dlya-otkrytykh-terras-manutti-muyu-', 'launzh-kreslo-ethimo-patio',
        'launzh-kreslo-ethimo-venexia', 'launzh-kreslo-kettal-tilos', 'myagkoe-launzh-kreslo-tacchini-butter',
        'obedennoe-kreslo-ethimo-rafael', 'obedennoe-kreslo-kettal-landscape', 'kreslo-giorgetti-olimpia',
        'kreslo-giorgetti-janet', 'kreslo-giorgetti-hug', 'kreslo-pedrali-hera-2866'],
    'svetilniki' => ['nastennyy-svetilnik-ethimo-lucerna-applique', 'napolnyy-svetilnik-kettal-anda',
        'premialnyy-ulichnyy-svetilnik-dlya-terras-manutti-sandua-medium',
        'dekorativnyy-svetilnik-dlya-zhilykh-i-obshchestvennykh-intererov-tacchini-1809'],
    'stulya' => ['premialnyy-obedennyy-stul-dlya-otkrytykh-terras-manutti-radoc', 'stul-pedrali-nuova-guinea-3635',
        'barnyy-stul-kettal-roll', 'dizaynerskiy-stul-iz-rotanga-i-kozhi-tacchini-basilan'],
    'shezlongi' => ['premialnyy-shezlong-dlya-basseynov-plyazhnykh-klubov-i-kurortnykh-kompleksov-manutti-kobo',
        'plyazhnaya-krovat-s-baldakhinom-ethimo-allaperto-mountain-etwick', 'shezlong-pedrali-fibra-fbr010-011'],
];
$linked = $missing = 0;
foreach (LEGACY as $sec => $codes) {
    $secId = $sections[$sec];
    foreach ($codes as $code) {
        $e = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $code], false, false, ['ID'])->Fetch();
        if (!$e) { $missing++; echo "  нет товара $code\n"; continue; }
        $id = (int) $e['ID'];
        if ($DB->Query("SELECT 1 FROM b_iblock_section_element WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_SECTION_ID=$secId")->Fetch()) { continue; }
        if ($dry) { echo "  [dry] $code → $sec\n"; $linked++; continue; }
        $DB->Query("INSERT INTO b_iblock_section_element (IBLOCK_SECTION_ID, IBLOCK_ELEMENT_ID) VALUES ($secId, $id)");
        \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(IBLOCK_ID, $id);
        $linked++;
    }
}
echo "прежняя мебель: добавлено привязок к подразделам $linked" . ($missing ? ", не найдено $missing" : '') . "\n";

if (!$dry) {
    \CIBlock::clearIblockTagCache(IBLOCK_ID);
    \CIBlock::clearIblockTagCache(BRANDS_IBLOCK_ID);
    BXClearCache(true, '/');
    $GLOBALS['CACHE_MANAGER']->CleanAll();
    echo "Кэш очищен\n";
}
