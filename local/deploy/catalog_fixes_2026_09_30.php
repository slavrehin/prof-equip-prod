<?php
/**
 * Правки карточек каталога 2026-09-30 (по замечаниям владельца):
 *  1. Пароконвектоматы Rational iCombi: в шапке (PREVIEW_TEXT) была смесь описания и таблицы,
 *     во вкладке «Описание» (DETAIL_TEXT) — только список доп. характеристик. Приводим к формату
 *     карточек MKN: шапка = «Основные характеристики» (таблица), «Описание» = текст описания +
 *     «Дополнительные характеристики» списком. Вкладка «Характеристики» (свойства) не меняется.
 *  2. MKN FlexiCombi MagicPilot 20.1 и Junior SKECOD623TG2: неквадратное фото обрезалось в шапке —
 *     делаем квадратную копию с белыми полями (старые файлы не удаляются).
 *  3. SAYL: Производитель Enofrigo → SAYL, значения «Модель» (чужое DOGE) удаляются.
 *     Скрытие «Модели» по всему сайту — в шаблоне catalog.element.
 *  4. SAYL INTEGRA LINE: порядок фото — «2», «3», фото с тёмным фоном («4»), далее остальные;
 *     дубль тёмного фото в галерее убран.
 *
 * Запуск: php local/deploy/catalog_fixes_2026_09_30.php [--dry]
 *   test3: docker compose exec web php local/deploy/catalog_fixes_2026_09_30.php
 *   прод:  SERVER_NAME=prof-equip.ru php local/deploy/catalog_fixes_2026_09_30.php
 * Идемпотентен: товары ищутся по CODE, файлы — по ORIGINAL_NAME; перед записью в --backup-dir
 * (по умолчанию рядом с DOCUMENT_ROOT недоступно — пишется в /tmp) сохраняется JSON старых значений.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'test3.prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\CModule::IncludeModule('iblock');
global $DB;

$dry = in_array('--dry', $argv, true);
const IBLOCK_ID = 11;

$isTest3 = is_link($_SERVER['DOCUMENT_ROOT'] . '/upload/iblock') || !is_writable($_SERVER['DOCUMENT_ROOT'] . '/upload/iblock');
$uploadSubdir = $isTest3 ? 'import_test' : 'iblock';

$backup = [];
$backupFile = '/tmp/catalog_fixes_2026_09_30_' . ($isTest3 ? 'test3' : 'prod') . '_' . date('Ymd_His') . '.json';

function out(string $s): void { echo $s, "\n"; }
function el(string $code): ?array {
    return \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, '=CODE' => $code], false, false,
        ['ID', 'CODE', 'NAME', 'PREVIEW_TEXT', 'DETAIL_TEXT', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'])->Fetch() ?: null;
}
function clean(string $s): string {
    $s = str_replace(["\u{200B}", "\u{00A0}"], ['', ' '], $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}
function dom(string $html): DOMXPath {
    $d = new DOMDocument();
    @$d->loadHTML('<?xml encoding="utf-8"?><div id="root">' . $html . '</div>');
    return new DOMXPath($d);
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

// ---------------------------------------------------------------- 1. Rational iCombi
out('== 1. Rational iCombi: шапка/описание');
$rs = \CIBlockElement::GetList(['ID' => 'ASC'], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => 'parokonvektomat-rational-icombi-%'],
    false, false, ['ID', 'CODE', 'NAME', 'PREVIEW_TEXT', 'DETAIL_TEXT']);
while ($e = $rs->Fetch()) {
    if (str_starts_with(ltrim($e['DETAIL_TEXT']), '<h2>Описание</h2>')) { out("  skip {$e['CODE']} (уже исправлен)"); continue; }

    // шапка: абзацы описания и строки таблицы
    $xp = dom($e['PREVIEW_TEXT']);
    $paragraphs = [];
    foreach ($xp->query('//div[@id="root"]/p') as $p) {
        $t = clean($p->textContent);
        if ($t !== '') { $paragraphs[] = $t; }
    }
    $rows = [];
    foreach ($xp->query('//table//tr') as $tr) {
        $cells = [];
        foreach ($xp->query('./td|./th', $tr) as $td) { $cells[] = clean($td->textContent); }
        $cells = array_values(array_filter($cells, static fn($c) => $c !== ''));
        if (count($cells) >= 2) { $rows[] = [$cells[0], $cells[1]]; }
    }

    // описание: пункты списка доп. характеристик (в любой вложенности) — плоско, по порядку
    $xd = dom($e['DETAIL_TEXT']);
    $items = [];
    foreach ($xd->query('//li') as $li) {
        $own = '';
        foreach ($li->childNodes as $ch) { if ($ch->nodeName !== 'ul') { $own .= $ch->textContent; } }
        $t = clean($own);
        if ($t !== '') { $items[] = $t; }
    }
    if (!$paragraphs || !$rows || !$items) { out("  !! {$e['CODE']}: не распознано (p=" . count($paragraphs) . ' rows=' . count($rows) . ' li=' . count($items) . '), пропуск'); continue; }

    // дерево: «Мощность:» / «Макс. … нагрузка:» → вложенные «Потребляемая», «Режим …», «Природный/Сжиженный газ»;
    // «Природный газ:» без значения внутри «Мощности» → вложенные «Режим …»
    $tree = [];
    $group = null; $fuel = null;
    foreach ($items as $t) {
        $isHeader = str_ends_with($t, ':');
        if (preg_match('/^(Природный газ|Сжиженный газ)\b/u', $t) && $group !== null) {
            $node = ['t' => $t, 'c' => []];
            $tree[$group]['c'][] = $node;
            $fuel = $isHeader ? array_key_last($tree[$group]['c']) : null;
            continue;
        }
        if (preg_match('/^(Потребляемая|Режим)\b/u', $t) && $group !== null) {
            if ($fuel !== null) { $tree[$group]['c'][$fuel]['c'][] = ['t' => $t, 'c' => []]; }
            else { $tree[$group]['c'][] = ['t' => $t, 'c' => []]; }
            continue;
        }
        $tree[] = ['t' => $t, 'c' => []];
        $group = $isHeader ? array_key_last($tree) : null;
        $fuel = null;
    }
    $renderList = static function (array $nodes, string $indent = '') use (&$renderList): string {
        $html = $indent . "<ul>\n";
        foreach ($nodes as $n) {
            $html .= $indent . "\t<li>" . h($n['t']);
            if ($n['c']) { $html .= "\n" . $renderList($n['c'], $indent . "\t") . $indent . "\t"; }
            $html .= "</li>\n";
        }
        return $html . $indent . "</ul>\n";
    };

    $preview = "<h2>Основные характеристики</h2>\n<table class=\"ch\">\n<tbody>\n";
    foreach ($rows as [$n, $v]) {
        $preview .= "<tr>\n<td class=\"name\">" . h($n) . "</td>\n<td class=\"value\">" . h($v) . "</td>\n</tr>\n";
    }
    $preview .= "</tbody>\n</table>\n";

    $detail = "<h2>Описание</h2>\n";
    foreach ($paragraphs as $p) { $detail .= '<p>' . h($p) . "</p>\n"; }
    $detail .= "<p><b>Дополнительные характеристики:</b></p>\n" . $renderList($tree);

    $backup['texts'][$e['CODE']] = ['ID' => $e['ID'], 'PREVIEW_TEXT' => $e['PREVIEW_TEXT'], 'DETAIL_TEXT' => $e['DETAIL_TEXT']];
    out("  {$e['CODE']}: p=" . count($paragraphs) . ' rows=' . count($rows) . ' li=' . count($items));
    if (!$dry) {
        $DB->Query("UPDATE b_iblock_element SET PREVIEW_TEXT='" . $DB->ForSql($preview) . "', PREVIEW_TEXT_TYPE='html',"
            . " DETAIL_TEXT='" . $DB->ForSql($detail) . "', DETAIL_TEXT_TYPE='html' WHERE ID=" . (int) $e['ID']);
        \CIBlockElement::UpdateSearch((int) $e['ID'], true);
    }
}

// ---------------------------------------------------------------- 2. MKN: квадратное фото с белыми полями
out('== 2. MKN: фото с белыми полями');
foreach (['parokonvektomat-mkn-flexicombi-magicpilot-20-1', 'parokonvektomat-mkn-junior-magicpilot-skecod623tg2'] as $code) {
    $e = el($code);
    if (!$e) { out("  !! нет $code"); continue; }
    $f = \CFile::GetFileArray((int) $e['DETAIL_PICTURE']);
    if (!$f) { out("  !! $code: нет DETAIL_PICTURE"); continue; }
    if ((int) $f['WIDTH'] === (int) $f['HEIGHT']) { out("  skip $code (уже квадратное {$f['WIDTH']}x{$f['HEIGHT']})"); continue; }
    $src = imagecreatefromstring(file_get_contents($_SERVER['DOCUMENT_ROOT'] . $f['SRC']));
    $w = imagesx($src); $hgt = imagesy($src);
    $side = (int) round(max($w, $hgt) * 1.06);
    $dst = imagecreatetruecolor($side, $side);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopy($dst, $src, intdiv($side - $w, 2), intdiv($side - $hgt, 2), 0, 0, $w, $hgt);
    $backup['pictures'][$code] = ['ID' => $e['ID'], 'PREVIEW_PICTURE' => $e['PREVIEW_PICTURE'], 'DETAIL_PICTURE' => $e['DETAIL_PICTURE']];
    out("  $code: {$w}x{$hgt} → {$side}x{$side}");
    if ($dry) { continue; }
    $ids = [];
    foreach (['PREVIEW_PICTURE', 'DETAIL_PICTURE'] as $field) {
        $tmp = tempnam(sys_get_temp_dir(), 'pad') . '.jpg';
        imagejpeg($dst, $tmp, 92);
        $ids[$field] = (int) \CFile::SaveFile([
            'name' => $f['ORIGINAL_NAME'], 'tmp_name' => $tmp, 'type' => 'image/jpeg', 'size' => filesize($tmp),
            'MODULE_ID' => 'iblock', 'description' => $f['DESCRIPTION'] ?? '',
        ], $uploadSubdir);
        @unlink($tmp);
        if (!$ids[$field]) { throw new RuntimeException("CFile::SaveFile failed for $code"); }
    }
    // только прямым SQL — см. «Известные грабли» в CLAUDE.md
    $DB->Query('UPDATE b_iblock_element SET PREVIEW_PICTURE=' . $ids['PREVIEW_PICTURE'] . ', DETAIL_PICTURE=' . $ids['DETAIL_PICTURE'] . ' WHERE ID=' . (int) $e['ID']);
    out('    новые файлы ' . implode(', ', $ids));
}

// ---------------------------------------------------------------- 3. SAYL: производитель, модель
out('== 3. SAYL: Производитель / Модель');
$prop = \CIBlockProperty::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => 'PROIZVODITEL'])->Fetch();
$enum = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $prop['ID'], 'VALUE' => 'SAYL'])->Fetch();
$enumId = $enum ? (int) $enum['ID'] : 0;
if (!$enumId && !$dry) {
    $enumId = (int) (new \CIBlockPropertyEnum())->Add(['PROPERTY_ID' => $prop['ID'], 'VALUE' => 'SAYL', 'XML_ID' => 'sayl', 'SORT' => 500]);
    out("  создано значение PROIZVODITEL=SAYL ($enumId)");
}
$rs = \CIBlockElement::GetList(['ID' => 'ASC'], ['IBLOCK_ID' => IBLOCK_ID, 'NAME' => '%SAYL%'], false, false, ['ID', 'CODE', 'NAME', 'PROPERTY_PROIZVODITEL', 'PROPERTY_MODEL']);
$seen = [];
while ($e = $rs->Fetch()) {
    if (isset($seen[$e['ID']])) { continue; } // PROPERTY_MODEL множественное — строки дублируются
    $seen[$e['ID']] = true;
    $models = [];
    $rsM = \CIBlockElement::GetProperty(IBLOCK_ID, $e['ID'], [], ['CODE' => 'MODEL']);
    while ($m = $rsM->Fetch()) { if ($m['VALUE']) { $models[] = $m['VALUE_ENUM']; } }
    $backup['sayl'][$e['CODE']] = ['ID' => $e['ID'], 'PROIZVODITEL' => $e['PROPERTY_PROIZVODITEL_VALUE'], 'MODEL' => $models];
    out("  {$e['CODE']}: {$e['PROPERTY_PROIZVODITEL_VALUE']} → SAYL; MODEL [" . implode(',', $models) . '] → удалить');
    if (!$dry) {
        \CIBlockElement::SetPropertyValuesEx((int) $e['ID'], IBLOCK_ID, ['PROIZVODITEL' => $enumId, 'MODEL' => false]);
        \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(IBLOCK_ID, (int) $e['ID']);
    }
}

// ---------------------------------------------------------------- 4. SAYL INTEGRA LINE: порядок фото
out('== 4. SAYL INTEGRA LINE: порядок фото');
$e = el('innovatsionnaya-modulnaya-liniya-razdachi-sayl-integra-line');
$galleryProp = \CIBlockProperty::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => 'GALLERY'])->Fetch();
$rows = [];
$res = $DB->Query('SELECT v.ID VID, f.ID FID, f.ORIGINAL_NAME FROM b_iblock_element_property v JOIN b_file f ON f.ID=v.VALUE'
    . ' WHERE v.IBLOCK_ELEMENT_ID=' . (int) $e['ID'] . ' AND v.IBLOCK_PROPERTY_ID=' . (int) $galleryProp['ID'] . ' ORDER BY v.ID');
while ($r = $res->Fetch()) { $rows[] = $r; }
$main = \CFile::GetFileArray((int) $e['DETAIL_PICTURE']);
$second = null;
foreach ($rows as $r) { if (preg_match('/ 2\.png$/u', $r['ORIGINAL_NAME'])) { $second = $r; } }
if (!$second || str_ends_with($main['ORIGINAL_NAME'], ' 2.png')) {
    out('  skip (уже исправлено или нет фото «2»)');
} else {
    $backup['integra'] = ['ID' => $e['ID'], 'PREVIEW_PICTURE' => $e['PREVIEW_PICTURE'], 'DETAIL_PICTURE' => $e['DETAIL_PICTURE'], 'GALLERY' => $rows];
    // главное фото = «2» (детальная — сам файл из галереи, анонс — копия записи b_file на тот же физический файл)
    // галерея: убрать «2» — остаются «3», «4» (тёмный фон), «6», «без номера»; старые записи b_file главного фото
    // не удаляются — они ссылаются на тот же физический файл, что и «4» в галерее
    out("  главное: {$main['ORIGINAL_NAME']} → {$second['ORIGINAL_NAME']}");
    if (!$dry) {
        $f = $DB->Query('SELECT * FROM b_file WHERE ID=' . (int) $second['FID'])->Fetch();
        unset($f['ID']);
        $f['TIMESTAMP_X'] = date('Y-m-d H:i:s');
        $cols = implode(',', array_keys($f));
        $vals = implode(',', array_map(static fn($v) => $v === null ? 'NULL' : "'" . $DB->ForSql($v) . "'", $f));
        $DB->Query("INSERT INTO b_file ($cols) VALUES ($vals)");
        $previewId = (int) $DB->LastID();
        $DB->Query('DELETE FROM b_iblock_element_property WHERE ID=' . (int) $second['VID']);
        $DB->Query('UPDATE b_iblock_element SET PREVIEW_PICTURE=' . $previewId . ', DETAIL_PICTURE=' . (int) $second['FID'] . ' WHERE ID=' . (int) $e['ID']);
        \CFile::CleanCache((int) $second['FID']);
    }
}

if (!$dry) {
    file_put_contents($backupFile, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    out("Бэкап старых значений: $backupFile");
    \CIBlock::clearIblockTagCache(IBLOCK_ID);
    BXClearCache(true, '/iblock/');
    out('Кэш инфоблока очищен');
}
out($dry ? 'DRY RUN — ничего не записано' : 'Готово');
