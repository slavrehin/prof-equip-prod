<?php
/**
 * Hobart: 3 серии конвейерных посудомоечных машин (PREMAX CP, PROFI CN, PROFI CS) — карточка товара на серию
 * в разделе «Посудомоечное оборудование», «Тип» = Конвейерная. Артикулов и цен нет («Запросить стоимость»).
 * Данные — conveyor_series.json (бриф владельца 2026-10-13, дословно), фото — папка --photos ({slug}.jpg).
 * Нужные свойства/значения (SPECS, BRAND, PROIZVODITEL=Hobart, TIP_POSUDOMOECHNOJ_MASHINY=konveyernaya)
 * уже созданы миграциями 2026-10-08 — здесь ничего структурного не создаётся.
 *
 * Запуск (в контейнере test3 / на хосте прода с SERVER_NAME=prof-equip.ru):
 *   php local/deploy/hobart_conveyor/import.php --photos=/path/jpg --created=/path/created.json
 *       [--only=hobart-premax-cp] [--draft] [--dry [--show-specs]]
 *
 * --draft — новые товары создаются с ACTIVE=N (для прода); у существующих ACTIVE не меняется.
 * Идемпотентен по CODE (= slug). Картинка загружается, только если у товара её ещё нет (иначе обновляется alt).
 * NAME = seo.h1 (H1 на сайте = название товара); если title ≠ h1 — остановка, пока не задано решение в NAME_OVERRIDE.
 * Характеристики — в SPECS (текст, в фильтр не попадают), порядок как в JSON; «Производитель» не пишется
 * (его показывает PROIZVODITEL); синонимы сводятся SPEC_RENAME.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'test3.prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\CModule::IncludeModule('iblock');
\CModule::IncludeModule('catalog');
global $DB;

$opts = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) { $opts[$m[1]] = $m[2] ?? true; }
}
$dry = isset($opts['dry']);
$draft = isset($opts['draft']);
$only = $opts['only'] ?? null;
$photosDir = rtrim($opts['photos'] ?? '', '/');
$createdFile = $opts['created'] ?? '';
if (!$dry && ($photosDir === '' || $createdFile === '')) { throw new RuntimeException('Укажите --photos=… и --created=…'); }

const IBLOCK_ID = 11;
const BRANDS_IBLOCK_ID = 14;
const SECTION_CODE = 'posudomoechnoe-oborudovanie';
const TYPE_PROP = 'TIP_POSUDOMOECHNOJ_MASHINY';
const TYPE_XML_ID = 'konveyernaya';
const PHOTO_EXT = 'jpg';

// Решение владельца по названию, если title ≠ seo.h1 (slug => NAME)
const NAME_OVERRIDE = [
    'hobart-profi-cs' => 'Конвейерные посудомоечные машины Hobart PROFI CS',   // как H1 и две другие серии (2026-10-13)
];

// Синонимы из карточек → единое название (как у 15 моделей Hobart)
const SPEC_RENAME = [
    'Корпус' => 'Материал корпуса',
    'Зоны обработки' => 'Технологические зоны',
];

// «Описание для анонса» (PREVIEW_TEXT) — основные характеристики списком <ul>, как у остальных Hobart
const PREVIEW_SPECS = ['Серия', 'Тип оборудования', 'Производительность', 'Технологические зоны', 'Управление', 'Материал корпуса'];

function previewHtml(array $specs): string
{
    $byName = array_column($specs, 1, 0);
    $li = [];
    foreach (PREVIEW_SPECS as $name) {
        if (isset($byName[$name])) {
            $li[] = '    <li><b>' . htmlspecialcharsbx($name) . '</b>: ' . htmlspecialcharsbx($byName[$name]) . '</li>';
        }
    }
    return "<ul>\n" . implode("\n", $li) . "\n</ul>";
}

/** characteristics[] → [[name, value], …], порядок JSON, единые названия */
function normalizeSpecs(string $slug, array $characteristics): array
{
    $rows = [];
    $seen = [];
    foreach ($characteristics as $c) {
        if ($c['name'] === 'Производитель') { continue; }
        $name = SPEC_RENAME[$c['name']] ?? $c['name'];
        if (isset($seen[$name])) { throw new RuntimeException("$slug: «{$name}» дважды после сведения названий"); }
        $seen[$name] = true;
        $rows[] = [$name, $c['value']];
    }
    return $rows;
}

$isTest3 = is_link($_SERVER['DOCUMENT_ROOT'] . '/upload/iblock') || !is_writable($_SERVER['DOCUMENT_ROOT'] . '/upload/iblock');
$uploadSubdir = $isTest3 ? 'import_test' : 'iblock';

function saveImage(string $path, string $name, string $alt, string $subdir): int
{
    if (!is_file($path)) { throw new RuntimeException("Нет файла $path"); }
    $arr = \CFile::MakeFileArray($path);
    $arr['name'] = $name;
    $arr['description'] = $alt;
    $arr['MODULE_ID'] = 'iblock';
    $id = \CFile::SaveFile($arr, $subdir);
    if (!$id) { throw new RuntimeException("Файл $path не сохранён"); }
    return (int) $id;
}

function clearCache(): void
{
    foreach (['cache', 'managed_cache', 'stack_cache'] as $d) {
        foreach (glob($_SERVER['DOCUMENT_ROOT'] . "/bitrix/$d/*") ?: [] as $f) { \Bitrix\Main\IO\Directory::deleteDirectory($f); }
    }
}

$data = json_decode(file_get_contents(__DIR__ . '/conveyor_series.json'), true, 512, JSON_THROW_ON_ERROR);

$props = [];
$rs = \CIBlockProperty::GetList([], ['IBLOCK_ID' => IBLOCK_ID]);
while ($p = $rs->Fetch()) { $props[$p['CODE']] = (int) $p['ID']; }
foreach (['SPECS', 'BRAND', 'PROIZVODITEL', TYPE_PROP] as $code) {
    if (empty($props[$code])) { throw new RuntimeException("Нет свойства $code."); }
}
$enumHobart = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $props['PROIZVODITEL'], 'VALUE' => 'Hobart'])->Fetch();
if (!$enumHobart) { throw new RuntimeException('Нет значения Hobart у PROIZVODITEL.'); }
$typeEnum = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $props[TYPE_PROP], 'XML_ID' => TYPE_XML_ID])->Fetch();
if (!$typeEnum) { throw new RuntimeException('Нет значения «Тип» ' . TYPE_XML_ID); }

$brand = \CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK_ID, 'CODE' => 'hobart'], false, false, ['ID'])->Fetch();
if (!$brand) { throw new RuntimeException('Нет бренда Hobart в инфоблоке брендов.'); }
$brandId = (int) $brand['ID'];

$section = \CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => SECTION_CODE], false, ['ID'])->Fetch();
if (!$section) { throw new RuntimeException('Нет раздела ' . SECTION_CODE); }
$sectionId = (int) $section['ID'];

$created = is_file($createdFile) ? json_decode(file_get_contents($createdFile), true, 512, JSON_THROW_ON_ERROR) : [];
$created += ['site' => $_SERVER['SERVER_NAME'], 'products' => []];

foreach ($data['products'] as $p) {
    $slug = $p['slug'];
    if ($only && $only !== $slug) { continue; }
    if ($p['sku'] !== null) { throw new RuntimeException("$slug: sku не null — скрипт рассчитан на серии без артикула"); }
    $name = NAME_OVERRIDE[$slug] ?? ($p['title'] === $p['seo']['h1'] ? $p['title'] : null);
    if ($name === null) { throw new RuntimeException("$slug: title ≠ seo.h1 — нужно решение владельца (NAME_OVERRIDE)"); }
    $photo = "$photosDir/$slug." . PHOTO_EXT;
    if (!$dry && !is_file($photo)) { throw new RuntimeException("$slug: нет фото $photo"); }

    $existing = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $slug], false, false, ['ID', 'DETAIL_PICTURE', 'ACTIVE'])->Fetch();
    // дубль серии под другим CODE
    $dup = $DB->Query("SELECT ID, CODE FROM b_iblock_element WHERE IBLOCK_ID=" . IBLOCK_ID
        . " AND NAME LIKE '%" . $DB->ForSql('Hobart ' . $p['series']) . "%'" . ($existing ? " AND ID <> {$existing['ID']}" : ''))->Fetch();
    if ($dup) { throw new RuntimeException("$slug: серия {$p['series']} уже у товара ID={$dup['ID']} ({$dup['CODE']})"); }

    $normalized = normalizeSpecs($slug, $p['characteristics']);
    $specs = [];
    foreach ($normalized as [$n, $v]) { $specs[] = ['VALUE' => $v, 'DESCRIPTION' => $n]; }

    if ($dry) {
        echo "[dry] $slug → " . ($existing ? "update ID={$existing['ID']}" : 'create') . ", NAME «{$name}», " . count($specs) . " хар., фото $slug." . PHOTO_EXT . "\n";
        if (isset($opts['show-specs'])) {
            echo previewHtml($normalized), "\n";
            foreach ($specs as $s) { echo "      {$s['DESCRIPTION']}: {$s['VALUE']}\n"; }
        }
        continue;
    }

    $fields = [
        'IBLOCK_ID' => IBLOCK_ID,
        'IBLOCK_SECTION_ID' => $sectionId,
        'IBLOCK_SECTION' => [$sectionId],
        'NAME' => $name,
        'CODE' => $slug,
        'PREVIEW_TEXT' => previewHtml($normalized),
        'PREVIEW_TEXT_TYPE' => 'html',
        'DETAIL_TEXT' => $p['description_html'],
        'DETAIL_TEXT_TYPE' => 'html',
    ];
    $el = new \CIBlockElement();
    if ($existing) {
        $id = (int) $existing['ID'];
        if (!$el->Update($id, $fields)) { throw new RuntimeException("$slug: update: " . $el->LAST_ERROR); }
        $status = 'updated';
    } else {
        $fields['ACTIVE'] = $draft ? 'N' : 'Y';
        $fields['SORT'] = 500;
        $fields['PROPERTY_VALUES'] = [];
        $id = (int) $el->Add($fields);
        if (!$id) { throw new RuntimeException("$slug: add: " . $el->LAST_ERROR); }
        $status = 'created';
    }

    // товар каталога без цены («Запросить стоимость»)
    if (!\CCatalogProduct::GetByID($id)) {
        \CCatalogProduct::Add(['ID' => $id, 'QUANTITY' => 0, 'MEASURE' => 796]);
    }

    \CIBlockElement::SetPropertyValuesEx($id, IBLOCK_ID, [
        'BRAND' => [$brandId],
        'PROIZVODITEL' => (int) $enumHobart['ID'],
        TYPE_PROP => (int) $typeEnum['ID'],
        'SPECS' => $specs,
    ]);
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(IBLOCK_ID, $id);

    $fileId = $existing['DETAIL_PICTURE'] ?? null;
    if (!$fileId) {
        $fileId = saveImage($photo, "$slug." . PHOTO_EXT, $p['image']['alt'], $uploadSubdir);
        $DB->Query("UPDATE b_iblock_element SET PREVIEW_PICTURE=$fileId, DETAIL_PICTURE=$fileId, TIMESTAMP_X=NOW() WHERE ID=$id");
    } else {
        $DB->Query("UPDATE b_file SET DESCRIPTION='" . $DB->ForSql($p['image']['alt']) . "' WHERE ID=" . (int) $fileId);
    }

    $tpl = new \Bitrix\Iblock\InheritedProperty\ElementTemplates(IBLOCK_ID, $id);
    $tpl->set(['ELEMENT_META_TITLE' => $p['seo']['title'], 'ELEMENT_META_DESCRIPTION' => $p['seo']['meta_description']]);
    (new \Bitrix\Iblock\InheritedProperty\ElementValues(IBLOCK_ID, $id))->clearValues();

    // проверка по базе, а не по коду возврата
    $chk = $DB->Query("SELECT IBLOCK_SECTION_ID, ACTIVE, DETAIL_PICTURE FROM b_iblock_element WHERE ID=$id")->Fetch();
    $cnt = (int) $DB->Query("SELECT COUNT(*) c FROM b_iblock_section_element WHERE IBLOCK_ELEMENT_ID=$id")->Fetch()['c'];
    $specCnt = (int) $DB->Query("SELECT COUNT(*) c FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID={$props['SPECS']}")->Fetch()['c'];
    if ((int) $chk['IBLOCK_SECTION_ID'] !== $sectionId || $cnt !== 1 || (int) $chk['DETAIL_PICTURE'] !== (int) $fileId || $specCnt !== count($specs)) {
        throw new RuntimeException("$slug: запись не сошлась (раздел {$chk['IBLOCK_SECTION_ID']}, привязок $cnt, фото {$chk['DETAIL_PICTURE']}, хар. $specCnt)");
    }

    $created['products'][$slug] = [
        'element_id' => $id,
        'file_id' => (int) $fileId,
        'status' => $status,
        'active' => $chk['ACTIVE'],
        'at' => date('c'),
    ] + (isset($created['products'][$slug]) && $status === 'updated' ? ['first' => $created['products'][$slug]['first'] ?? $created['products'][$slug]['at']] : []);
    file_put_contents($createdFile, json_encode($created, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    echo "$status $slug → ID=$id, file $fileId, ACTIVE={$chk['ACTIVE']}, хар. $specCnt\n";
}

if (!$dry) {
    clearCache();
    echo "Кэш очищен\n";
}
