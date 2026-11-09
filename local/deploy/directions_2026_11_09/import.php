<?php
/**
 * Страницы направлений «Кухня», «Химия», «Мебель», «Комплексное оснащение отелей», 2026-11-09.
 *
 * После потери Beget элементы инфоблоков направлений (kuhnya, chemistry, furniture, equipment)
 * остались без свойств — страницы рисовали только пустой «О направлении» и форму.
 * Контент взят из wayback-копий боевых страниц (май–июнь 2026), см. extract_wayback.py → data.json.
 * Оригиналы картинок из /upload утеряны — ставятся картинки дизайнерского макета
 * (local/layout/dist/assets/img/...), по которым и верстались эти блоки.
 *
 * Что делает:
 *  - заполняет тексты/ссылки/привязки (проекты, бренды, разделы, решения, товары);
 *  - создаёт элементы «Этапы работ» (кухня 10, химия 9) и «Отзывы» (9), ключ — XML_ID;
 *  - файлы-свойства ставит только если пусто (--force — перезаписать).
 *
 * Запуск (локально, на dev и на проде, из корня сайта, от www-data):
 *   php local/deploy/directions_2026_11_09/import.php [--dry] [--force]
 * Идемпотентен.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

$dry   = in_array('--dry', $argv, true);
$force = in_array('--force', $argv, true);
$data  = json_decode(file_get_contents(__DIR__ . '/data.json'), true);
$IMG   = $_SERVER['DOCUMENT_ROOT'] . '/local/layout/dist/assets/img/';

$IB = [];
foreach (['kuhnya', 'chemistry', 'equipment', 'furniture', 'steps', 'reviews', 'projects', 'brands', 'solution', 'catalog'] as $c) {
    $r = \CIBlock::GetList([], ['CODE' => $c, 'CHECK_PERMISSIONS' => 'N'])->Fetch();
    if (!$r) { fwrite(STDERR, "нет инфоблока $c\n"); exit(1); }
    $IB[$c] = (int) $r['ID'];
}

function elementId(int $iblock, string $code): int
{
    $r = \CIBlockElement::GetList([], ['IBLOCK_ID' => $iblock, '=CODE' => $code, 'CHECK_PERMISSIONS' => 'N'], false, ['nTopCount' => 1], ['ID'])->Fetch();
    return $r ? (int) $r['ID'] : 0;
}

/** Коды → ID, с отчётом о ненайденных. */
function ids(int $iblock, array $codes, string $what): array
{
    $out = [];
    foreach ($codes as $c) {
        $id = elementId($iblock, $c);
        if ($id) { $out[] = $id; } else { echo "  ! $what не найден: $c\n"; }
    }
    return $out;
}

function sectionIds(int $iblock, array $codes): array
{
    $out = [];
    foreach ($codes as $c) {
        $r = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblock, '=CODE' => $c, 'CHECK_PERMISSIONS' => 'N'], false, ['ID'])->Fetch();
        if ($r) { $out[] = (int) $r['ID']; } else { echo "  ! раздел не найден: $c\n"; }
    }
    return $out;
}

function html(string $s): array { return ['VALUE' => ['TYPE' => 'HTML', 'TEXT' => $s]]; }

/** Файл макета: берём @2x, если есть. */
function img(string $rel): array
{
    global $IMG;
    $p = $IMG . $rel;
    $x2 = preg_replace('/\.(\w+)$/', '@2x.$1', $p);
    if (is_file($x2)) { $p = $x2; }
    if (!is_file($p)) { echo "  ! нет файла $rel\n"; return []; }
    return \CFile::MakeFileArray($p);
}

/** Создаёт/обновляет элемент по XML_ID. */
function upsert(int $iblock, string $xmlId, array $fields, array $props = []): int
{
    global $dry, $force;
    $el = new \CIBlockElement();
    $id = (int) (\CIBlockElement::GetList([], ['IBLOCK_ID' => $iblock, '=XML_ID' => $xmlId, 'CHECK_PERMISSIONS' => 'N'], false, ['nTopCount' => 1], ['ID'])->Fetch()['ID'] ?? 0);
    if ($dry) { echo '  ' . ($id ? 'upd' : 'new') . " $xmlId «{$fields['NAME']}»\n"; return $id; }
    $fields += ['IBLOCK_ID' => $iblock, 'XML_ID' => $xmlId, 'ACTIVE' => 'Y'];
    if ($id) {
        $el->Update($id, $fields) or print("  ERR {$el->LAST_ERROR}\n");
    } else {
        $id = (int) $el->Add($fields);
        if (!$id) { echo "  ERR $xmlId {$el->LAST_ERROR}\n"; return 0; }
        echo "  + $xmlId «{$fields['NAME']}»\n";
    }
    foreach ($props as $code => $file) {
        $cur = \CIBlockElement::GetProperty($iblock, $id, [], ['CODE' => $code])->Fetch();
        if ($force || empty($cur['VALUE'])) {
            \CIBlockElement::SetPropertyValuesEx($id, $iblock, [$code => $file]);
        }
    }
    return $id;
}

/** Пишет свойства элемента направления; файловые — только если пусто (или --force). */
function setProps(int $iblock, int $id, array $props, array $files): void
{
    global $dry, $force;
    foreach ($files as $code => $val) {
        if (!$val) { continue; }
        $cur = \CIBlockElement::GetProperty($iblock, $id, [], ['CODE' => $code])->Fetch();
        if (!$force && !empty($cur['VALUE'])) { continue; }
        if (is_array($val) && isset($val[0])) {           // множественное
            $val = array_values(array_filter($val));
            if ($force && !$dry) {                         // очистить старые значения
                $old = [];
                $r = \CIBlockElement::GetProperty($iblock, $id, [], ['CODE' => $code]);
                while ($v = $r->Fetch()) { if ($v['PROPERTY_VALUE_ID']) { $old[$v['PROPERTY_VALUE_ID']] = ['VALUE' => ['del' => 'Y']]; } }
                if ($old) { \CIBlockElement::SetPropertyValueCode($id, $code, $old); }
            }
            $val = array_map(fn($f) => ['VALUE' => $f], $val);
        }
        $props[$code] = $val;
    }
    if ($dry) {
        foreach ($props as $k => $v) { echo "  $k: " . (is_array($v) ? count($v) . ' знач.' : mb_substr((string) $v, 0, 60)) . "\n"; }
        return;
    }
    \CIBlockElement::SetPropertyValuesEx($id, $iblock, $props);
}

$sortBase = 100;
function steps(array $list, string $prefix, string $iconDir, string $ext): array
{
    global $IB, $sortBase;
    $out = [];
    foreach ($list as $i => $s) {
        $n = $i + 1;
        $icon = is_file($GLOBALS['IMG'] . "$iconDir/$n.$ext") ? img("$iconDir/$n.$ext") : [];
        $out[] = upsert($IB['steps'], "$prefix-$n", [
            'NAME' => $s['name'], 'PREVIEW_TEXT' => $s['text'], 'PREVIEW_TEXT_TYPE' => 'text', 'SORT' => $sortBase + $n * 10,
        ], $icon ? ['ICONS' => $icon] : []);
    }
    return array_filter($out);
}

$reviewIds = [];
function reviews(array $list): array
{
    global $IB, $reviewIds;
    $out = [];
    foreach ($list as $r) {
        $key = 'review-' . substr(md5($r['author']), 0, 10);
        if (!isset($reviewIds[$key])) {
            $reviewIds[$key] = upsert($IB['reviews'], $key, [
                'NAME' => $r['author'], 'PREVIEW_TEXT' => $r['text'], 'PREVIEW_TEXT_TYPE' => 'text',
                'SORT' => 100 + count($reviewIds) * 10,
            ]);
        }
        $out[] = $reviewIds[$key];
    }
    return array_filter($out);
}

// Сначала отзывы «оснащения» — там полный список (9) в боевом порядке.
reviews($data['equipment']['REVIEWS']);

// ---------- Кухня
$d = $data['kuhnya'];
$id = elementId($IB['kuhnya'], 'kuhnya');
echo "kuhnya #$id\n";
setProps($IB['kuhnya'], $id, [
    'DESCRIPTION'        => html($d['DESCRIPTION']),
    'LINK_CATALOG'       => $d['LINK_CATALOG'],
    'PROJECTS'           => ids($IB['projects'], $d['PROJECTS'], 'проект'),
    'BRANDS'             => ids($IB['brands'], $d['BRANDS'], 'бренд'),
    'CATEGORIES_CATALOG' => sectionIds($IB['catalog'], $d['CATEGORIES_CATALOG']),
    'STEPS'              => steps($d['STEPS'], 'dir-kuhnya-step', 'steps', 'svg'),
    'SOLUTION'           => ids($IB['solution'], $d['SOLUTION'], 'решение'),
    'REVIEWS'            => reviews($d['REVIEWS']),
], ['IMG_BLOCK' => img('direction-about/1.jpg')]);

// ---------- Химия
$d = $data['chemistry'];
$id = elementId($IB['chemistry'], 'chemistry');
echo "chemistry #$id\n";
$sortBase = 500;
setProps($IB['chemistry'], $id, [
    'MAIN_TITLE'       => $d['MAIN_TITLE'],
    'MAIN_TEXT_LEFT'   => html($d['MAIN_TEXT_LEFT']),
    'MAIN_TEXT_RIGHT'  => html($d['MAIN_TEXT_RIGHT']),
    'STAT'             => array_map(fn($s) => ['VALUE' => $s['v'], 'DESCRIPTION' => $s['d']], $d['STAT']),
    'TITLE_GARANT'     => $d['TITLE_GARANT'],
    'LIST_GARANT'      => $d['LIST_GARANT'],
    'STEPS'            => steps($d['STEPS'], 'dir-chemistry-step', 'chemistry-steps', 'png'),
    'CATALOG_PRODUCTS' => ids($IB['catalog'], $d['CATALOG_PRODUCTS'], 'товар'),
    'TITLE_FORM'       => $d['TITLE_FORM'],
    'TEXT_FORM'        => html($d['TEXT_FORM']),
    'REVIEWS'          => reviews($d['REVIEWS']),
], []);

// ---------- Комплексное оснащение отелей
$d = $data['equipment'];
$id = elementId($IB['equipment'], 'equipment');
echo "equipment #$id\n";
$p = [];
for ($i = 1; $i <= 3; $i++) {
    $p["ITEM_{$i}_TITLE"] = $d["ITEM_{$i}_TITLE"];
    $p["ITEM_{$i}_TEXT"] = html($d["ITEM_{$i}_TEXT"]);
    $p["ITEM_{$i}_BUTTON_LINK"] = $d["ITEM_{$i}_BUTTON_LINK"];
}
$p['PROJECTS'] = ids($IB['projects'], $d['PROJECTS'], 'проект');
$p['BRANDS']   = ids($IB['brands'], $d['BRANDS'], 'бренд');
$p['REVIEWS']  = reviews($d['REVIEWS']);
setProps($IB['equipment'], $id, $p, [
    'ITEM_1_IMAGE' => img('equipment-about/1.png'),
    'ITEM_2_IMAGE' => img('equipment-about/2.png'),
    'ITEM_3_IMAGE' => img('equipment-about/3.png'),
]);

// ---------- Мебель
$d = $data['furniture'];
$id = elementId($IB['furniture'], 'furniture');
echo "furniture #$id\n";
$p = [
    'HERO_TITLE'            => $d['HERO_TITLE'],
    'HERO_DESCR'            => $d['HERO_DESCR'],
    'ABOUT_TEXT'            => $d['ABOUT_TEXT'],
    'CABINET_TITLE'         => $d['CABINET_TITLE'],
    'CABINET_DESCR'         => $d['CABINET_DESCR'],
    'CASES_TEXT'            => html($d['CASES_TEXT']),
    'CASES_ADDITIONAL_TEXT' => html($d['CASES_ADDITIONAL_TEXT']),
];
$f = [
    'HERO_BG'                => img('furniture-hero/bg.jpg'),
    'ABOUT_IMAGE'            => img('furniture-about/img.jpg'),
    'CABINET_IMAGE'          => img('furniture-cabinet/img.jpg'),
    'CASES_ADDITIONAL_IMAGE' => img('furniture-cases/new.png'),
    'CASES_SLIDER_IMAGES'    => array_map(fn($n) => img("furniture-cases/$n.jpg"), [1, 2, 3, 4]),
    'SUPPLIERS_IMAGES'       => array_map(fn($n) => img("furniture-supliers/$n.jpg"), range(1, 12)),
];
foreach ($d['ABOUT_DESC'] as $i => $t) { $n = $i + 1; $p["ABOUT_DESC_$n"] = $t; $f["ABOUT_ICON_$n"] = img("furniture-about/$n.svg"); }
foreach ($d['PLACES'] as $i => $t) { $n = $i + 1; $p["PLACES_TITLE_$n"] = $t['t']; $p["PLACES_DESC_$n"] = $t['d']; $f["PLACES_IMAGE_$n"] = img("furniture-places/$n.jpg"); }
foreach ($d['CABINET_FEATURES'] as $i => $t) { $n = $i + 1; $p["CABINET_FEATURE_TEXT_$n"] = $t; $f["CABINET_FEATURE_ICON_$n"] = img("furniture-cabinet/$n.png"); }
setProps($IB['furniture'], $id, $p, $f);

if (!$dry) {
    foreach (['kuhnya', 'chemistry', 'equipment', 'furniture', 'steps', 'reviews'] as $c) { \CIBlock::clearIblockTagCache($IB[$c]); }
    BXClearCache(true);
}
echo "OK\n";
