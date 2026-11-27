<?php
/**
 * Winterhalter: посудомоечные машины в раздел «Посудомоечное оборудование» (posudomoechnoe-oborudovanie).
 *
 * 1. products.json → 13 карточек моделей (UC-S/M/L/XL, PT-M/L/XL, PT-L/XL Utensil, UF-M/L/XL, GS 630):
 *    NAME (= H1), CODE, PREVIEW_TEXT (основные характеристики списком), DETAIL_TEXT, SPECS, BRAND, PROIZVODITEL,
 *    TIP_POSUDOMOECHNOJ_MASHINY, мета title/description, товар каталога без цены («Запросить стоимость»).
 *    Товар с "prod_draft": true создаётся с ACTIVE=N (во всех окружениях); у существующих ACTIVE не меняется.
 * 2. series_cards → существующие карточки серий конвейерных машин (MTR, CTR, MTF, STF; созданы 2026-11-07 в
 *    разделе «Оборудование профессиональной кухни») ДОПОЛНИТЕЛЬНО привязываются к разделу посудомоечного
 *    оборудования (основной раздел не меняется) и получают «Тип». Остальное в них не трогается.
 * Фото — отдельной партией local/deploy/product_photos/batches/winterhalter_20261127 (после этого импорта).
 * Карточки серий UC, PT и котломоечных машин (2444–2446) не меняются.
 *
 * Запуск (из корня сайта; локально — в контейнере web, на проде — sudo -u www-data):
 *   php local/deploy/winterhalter_dishwashers/import.php --dry [--show-specs] [--only=winterhalter-uc-m]
 *   php local/deploy/winterhalter_dishwashers/import.php --log=/path/rollback.json
 *   откат: php local/deploy/winterhalter_dishwashers/import.php --rollback=/path/rollback.json
 * Идемпотентен: товар ищется по CODE; повторный запуск обновляет поля теми же значениями.
 * Откат удаляет только созданные этим импортом товары и возвращает привязки/«Тип» карточек серий;
 * изменения полей у товаров, которые уже существовали до импорта, откат не возвращает (до импорта их нет).
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

$opts = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) { $opts[$m[1]] = $m[2] ?? true; }
}
$dry = isset($opts['dry']);
$only = $opts['only'] ?? null;
$logFile = $opts['log'] ?? '';

const IBLOCK_ID = 11;
const BRANDS_IBLOCK_ID = 14;
const SECTION_CODE = 'posudomoechnoe-oborudovanie';
const BRAND_CODE = 'winterhalter';
const BRAND_ENUM = 'Winterhalter';
const TYPE_PROP = 'TIP_POSUDOMOECHNOJ_MASHINY';

// Единый порядок характеристик: общие → мойка → электрика → габариты → подключение → оснащение
const SPEC_ORDER = [
    'Серия', 'Модель', 'Тип оборудования', 'Варианты мойки',
    'Размер корзины', 'Высота загрузки', 'Ширина камеры', 'Производительность', 'Производительность по бокалам',
    'Продолжительность цикла', 'Объём моечного бака', 'Расход воды за цикл', 'Температура мойки', 'Температура ополаскивания',
    'Мощность моечного насоса', 'Мощность нагрева бака', 'Мощность бойлера', 'Общая подключаемая мощность', 'Электропитание',
    'Габариты, Ш×Г×В', 'Высота', 'Высота с открытым куполом', 'Высота с открытой дверью', 'Глубина с открытой дверью',
    'Масса без упаковки', 'Уровень шума', 'Степень защиты',
    'Подключение воды', 'Подключение слива',
    'Панель управления', 'Дозатор моющего средства', 'Дозатор ополаскивающего средства', 'Насос ополаскивания', 'Сливной насос',
    'Thermostop', 'Программа самоочистки', 'Теплообменник сточной воды', 'Wi-Fi', 'USB-интерфейс',
    'Комплектация', 'Опции', 'Сертификаты', 'Страна производства',
];

// «Описание для анонса» (PREVIEW_TEXT) — основные характеристики списком, как у Hobart
const PREVIEW_SPECS = [
    'Размер корзины', 'Высота загрузки', 'Производительность', 'Расход воды за цикл', 'Объём моечного бака',
    'Общая подключаемая мощность', 'Электропитание', 'Габариты, Ш×Г×В', 'Масса без упаковки', 'Серия',
];

function normalizeSpecs(string $slug, array $characteristics): array
{
    $pos = array_flip(SPEC_ORDER);
    $seen = [];
    foreach ($characteristics as [$name, $value]) {
        if (!isset($pos[$name])) { throw new RuntimeException("$slug: характеристика «{$name}» не в словаре SPEC_ORDER"); }
        if (isset($seen[$name])) { throw new RuntimeException("$slug: «{$name}» дважды"); }
        if (trim((string) $value) === '') { throw new RuntimeException("$slug: пустое значение «{$name}»"); }
        $seen[$name] = true;
    }
    usort($characteristics, static fn($a, $b) => $pos[$a[0]] <=> $pos[$b[0]]);
    return $characteristics;
}

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

function clearCache(): void
{
    foreach (['cache', 'managed_cache', 'stack_cache'] as $d) {
        foreach (glob($_SERVER['DOCUMENT_ROOT'] . "/bitrix/$d/*") ?: [] as $f) { \Bitrix\Main\IO\Directory::deleteDirectory($f); }
    }
}

function sectionsOf(int $id): array
{
    global $DB;
    $r = [];
    $rs = $DB->Query("SELECT IBLOCK_SECTION_ID FROM b_iblock_section_element WHERE IBLOCK_ELEMENT_ID=$id AND ADDITIONAL_PROPERTY_ID IS NULL ORDER BY IBLOCK_SECTION_ID");
    while ($x = $rs->Fetch()) { $r[] = (int) $x['IBLOCK_SECTION_ID']; }
    return $r;
}

$props = [];
$rs = \CIBlockProperty::GetList([], ['IBLOCK_ID' => IBLOCK_ID]);
while ($p = $rs->Fetch()) { $props[$p['CODE']] = (int) $p['ID']; }
foreach (['SPECS', 'BRAND', 'PROIZVODITEL', TYPE_PROP] as $code) {
    if (empty($props[$code])) { throw new RuntimeException("Нет свойства $code."); }
}

// ---------- откат ----------
if (!empty($opts['rollback'])) {
    $log = json_decode(file_get_contents($opts['rollback']), true, 512, JSON_THROW_ON_ERROR);
    foreach ($log['created'] ?? [] as $code => $id) {
        $id = (int) $id;
        $row = $DB->Query("SELECT CODE FROM b_iblock_element WHERE ID=$id")->Fetch();
        if (!$row) { echo "нет ID=$id ($code), пропуск\n"; continue; }
        if ($row['CODE'] !== $code) { throw new RuntimeException("ID=$id: CODE {$row['CODE']} ≠ $code — откат остановлен"); }
        if (!$dry) {
            if (!\CIBlockElement::Delete($id)) { throw new RuntimeException("ID=$id не удалён"); }
        }
        echo ($dry ? '[dry] ' : '') . "удалён $code ID=$id\n";
    }
    foreach ($log['activated'] ?? [] as $code => $id) {
        if (!$dry) { $DB->Query("UPDATE b_iblock_element SET ACTIVE='N', TIMESTAMP_X=NOW() WHERE ID=" . (int) $id . " AND CODE='" . $DB->ForSql($code) . "'"); }
        echo ($dry ? '[dry] ' : '') . "выключен $code ID=$id\n";
    }
    foreach ($log['series'] ?? [] as $code => $prev) {
        $id = (int) $prev['id'];
        if ($dry) { echo "[dry] вернуть $code: разделы " . implode(',', $prev['sections']) . ", тип " . ($prev['type'] ?: '—') . "\n"; continue; }
        \CIBlockElement::SetElementSection($id, $prev['sections'], false, 0, (int) $prev['main_section']);
        \CIBlockElement::SetPropertyValuesEx($id, IBLOCK_ID, [TYPE_PROP => $prev['type'] ? (int) $prev['type'] : false]);
        \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(IBLOCK_ID, $id);
        $now = sectionsOf($id);
        if ($now !== $prev['sections']) { throw new RuntimeException("$code: разделы после отката " . implode(',', $now)); }
        echo "возвращено $code: разделы " . implode(',', $now) . "\n";
    }
    if (!$dry) { clearCache(); echo "Кэш очищен\n"; }
    exit(0);
}

// ---------- включение черновика после появления фото: --activate=winterhalter-uf-xl ----------
if (!empty($opts['activate'])) {
    $code = (string) $opts['activate'];
    $e = $DB->Query("SELECT ID, ACTIVE, IBLOCK_SECTION_ID, DETAIL_PICTURE FROM b_iblock_element WHERE IBLOCK_ID=" . IBLOCK_ID . " AND CODE='" . $DB->ForSql($code) . "'")->Fetch();
    if (!$e) { throw new RuntimeException("нет товара $code"); }
    if (!(int) $e['DETAIL_PICTURE']) { throw new RuntimeException("$code: нет фото — не включаю"); }
    if ($e['ACTIVE'] === 'Y') { echo "$code уже активен\n"; exit(0); }
    echo ($dry ? '[dry] ' : '') . "включить $code ID={$e['ID']}\n";
    if ($dry) { exit(0); }
    if ($logFile === '' || is_file($logFile)) { throw new RuntimeException('Укажите новый --log=/path/rollback.json'); }
    file_put_contents($logFile, json_encode(['site' => $_SERVER['SERVER_NAME'], 'activated' => [$code => (int) $e['ID']]], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    // прямым SQL, чтобы не задеть раздел (см. CLAUDE.md, «Известные особенности»)
    $DB->Query("UPDATE b_iblock_element SET ACTIVE='Y', TIMESTAMP_X=NOW() WHERE ID=" . (int) $e['ID']);
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(IBLOCK_ID, (int) $e['ID']);
    \CIBlock::clearIblockTagCache(IBLOCK_ID);
    $chk = $DB->Query("SELECT ACTIVE, IBLOCK_SECTION_ID FROM b_iblock_element WHERE ID=" . (int) $e['ID'])->Fetch();
    if ($chk['ACTIVE'] !== 'Y' || $chk['IBLOCK_SECTION_ID'] !== $e['IBLOCK_SECTION_ID']) { throw new RuntimeException("$code: не сошлось после включения"); }
    clearCache();
    echo "включён $code. Откат: --rollback=$logFile\n";
    exit(0);
}

if (!$dry && $logFile === '') { throw new RuntimeException('Укажите --log=/path/rollback.json (или --dry)'); }
if (!$dry && is_file($logFile)) { throw new RuntimeException("Лог $logFile уже существует — укажите новый файл"); }

$data = json_decode(file_get_contents(__DIR__ . '/products.json'), true, 512, JSON_THROW_ON_ERROR);

$enumBrand = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $props['PROIZVODITEL'], 'VALUE' => BRAND_ENUM])->Fetch();
if (!$enumBrand) { throw new RuntimeException('Нет значения ' . BRAND_ENUM . ' у PROIZVODITEL'); }
$typeEnums = [];
$rs = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $props[TYPE_PROP]]);
while ($e = $rs->Fetch()) { $typeEnums[$e['XML_ID']] = (int) $e['ID']; }

$brand = \CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK_ID, '=CODE' => BRAND_CODE], false, false, ['ID'])->Fetch();
if (!$brand) { throw new RuntimeException('Нет бренда ' . BRAND_CODE . ' в инфоблоке брендов.'); }
$brandId = (int) $brand['ID'];

$section = \CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, '=CODE' => SECTION_CODE], false, ['ID'])->Fetch();
if (!$section) { throw new RuntimeException('Нет раздела ' . SECTION_CODE); }
$sectionId = (int) $section['ID'];

// предварительная проверка всего файла до любых записей
$slugs = [];
foreach ($data['products'] as $p) {
    if (isset($slugs[$p['slug']])) { throw new RuntimeException("дубль slug {$p['slug']}"); }
    $slugs[$p['slug']] = true;
    if (empty($typeEnums[$p['type']])) { throw new RuntimeException("{$p['slug']}: нет значения «Тип» {$p['type']}"); }
    if (mb_strlen($p['seo']['meta_description']) > 300) { throw new RuntimeException("{$p['slug']}: meta_description длиннее 300"); }
    normalizeSpecs($p['slug'], $p['characteristics']);
}
foreach ($data['series_cards'] as $s) {
    if (empty($typeEnums[$s['type']])) { throw new RuntimeException("{$s['code']}: нет значения «Тип» {$s['type']}"); }
}

$log = ['site' => $_SERVER['SERVER_NAME'], 'started' => date('c'), 'created' => [], 'updated' => [], 'series' => []];
$saveLog = static function () use (&$log, $logFile, $dry) {
    if (!$dry) { file_put_contents($logFile, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)); }
};
$saveLog();

foreach ($data['products'] as $p) {
    if ($only && $only !== $p['slug']) { continue; }
    $specsN = normalizeSpecs($p['slug'], $p['characteristics']);
    $specs = array_map(static fn($r) => ['VALUE' => $r[1], 'DESCRIPTION' => $r[0]], $specsN);
    $existing = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, '=CODE' => $p['slug'], 'CHECK_PERMISSIONS' => 'N'], false, false, ['ID', 'ACTIVE'])->Fetch();
    $dupName = $DB->Query("SELECT ID, CODE FROM b_iblock_element WHERE IBLOCK_ID=" . IBLOCK_ID . " AND NAME='" . $DB->ForSql($p['title']) . "'"
        . ($existing ? " AND ID<>{$existing['ID']}" : ''))->Fetch();
    if ($dupName) { throw new RuntimeException("{$p['slug']}: такое же название у ID={$dupName['ID']} ({$dupName['CODE']})"); }

    if ($dry) {
        echo "[dry] {$p['slug']} → " . ($existing ? "update ID={$existing['ID']}" : 'create' . (!empty($p['prod_draft']) ? ' (ACTIVE=N)' : '')) . ", тип {$p['type']}, " . count($specs) . " хар.\n";
        if (isset($opts['show-specs'])) {
            echo previewHtml($specsN), "\n";
            foreach ($specsN as [$n, $v]) { echo "      $n: $v\n"; }
        }
        continue;
    }

    $fields = [
        'IBLOCK_ID' => IBLOCK_ID,
        'IBLOCK_SECTION_ID' => $sectionId,
        'IBLOCK_SECTION' => [$sectionId],
        'NAME' => $p['title'],
        'CODE' => $p['slug'],
        'PREVIEW_TEXT' => previewHtml($specsN),
        'PREVIEW_TEXT_TYPE' => 'html',
        'DETAIL_TEXT' => $p['description_html'],
        'DETAIL_TEXT_TYPE' => 'html',
    ];
    $el = new \CIBlockElement();
    if ($existing) {
        $id = (int) $existing['ID'];
        if (!$el->Update($id, $fields)) { throw new RuntimeException("{$p['slug']}: update: " . $el->LAST_ERROR); }
        $log['updated'][$p['slug']] = $id;
        $status = 'updated';
    } else {
        $fields['ACTIVE'] = !empty($p['prod_draft']) ? 'N' : 'Y';
        $fields['SORT'] = 500;
        $fields['PROPERTY_VALUES'] = [];
        $id = (int) $el->Add($fields);
        if (!$id) { throw new RuntimeException("{$p['slug']}: add: " . $el->LAST_ERROR); }
        $log['created'][$p['slug']] = $id;
        $status = 'created';
    }
    $saveLog();

    if (!\CCatalogProduct::GetByID($id)) {
        \CCatalogProduct::Add(['ID' => $id, 'QUANTITY' => 0, 'MEASURE' => 796]);
    }
    \CIBlockElement::SetPropertyValuesEx($id, IBLOCK_ID, [
        'BRAND' => [$brandId],
        'PROIZVODITEL' => (int) $enumBrand['ID'],
        TYPE_PROP => $typeEnums[$p['type']],
        'SPECS' => $specs,
    ]);
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(IBLOCK_ID, $id);

    $tpl = new \Bitrix\Iblock\InheritedProperty\ElementTemplates(IBLOCK_ID, $id);
    $tpl->set(['ELEMENT_META_TITLE' => $p['seo']['title'], 'ELEMENT_META_DESCRIPTION' => $p['seo']['meta_description']]);
    (new \Bitrix\Iblock\InheritedProperty\ElementValues(IBLOCK_ID, $id))->clearValues();

    // проверка по базе, а не по коду возврата
    $chk = $DB->Query("SELECT NAME, IBLOCK_SECTION_ID, ACTIVE FROM b_iblock_element WHERE ID=$id")->Fetch();
    $secs = sectionsOf($id);
    $specCnt = (int) $DB->Query("SELECT COUNT(*) c FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID={$props['SPECS']}")->Fetch()['c'];
    $type = (int) ($DB->Query("SELECT VALUE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID={$props[TYPE_PROP]}")->Fetch()['VALUE'] ?? 0);
    $prodOk = (bool) $DB->Query("SELECT ID FROM b_catalog_product WHERE ID=$id")->Fetch();
    if ($chk['NAME'] !== $p['title'] || (int) $chk['IBLOCK_SECTION_ID'] !== $sectionId || $secs !== [$sectionId]
        || $specCnt !== count($specs) || $type !== $typeEnums[$p['type']] || !$prodOk) {
        throw new RuntimeException("{$p['slug']}: запись не сошлась (раздел {$chk['IBLOCK_SECTION_ID']}, привязки " . implode(',', $secs) . ", хар. $specCnt, тип $type, товар " . (int) $prodOk . ')');
    }
    echo "$status {$p['slug']} → ID=$id, ACTIVE={$chk['ACTIVE']}, хар. $specCnt\n";
}

foreach ($data['series_cards'] as $s) {
    if ($only) { break; }
    $e = $DB->Query("SELECT ID, IBLOCK_SECTION_ID, ACTIVE FROM b_iblock_element WHERE IBLOCK_ID=" . IBLOCK_ID . " AND CODE='" . $DB->ForSql($s['code']) . "'")->Fetch();
    if (!$e) { echo "нет карточки серии {$s['code']} — пропуск\n"; continue; }
    $id = (int) $e['ID'];
    $secs = sectionsOf($id);
    $prevType = (int) ($DB->Query("SELECT VALUE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID={$props[TYPE_PROP]}")->Fetch()['VALUE'] ?? 0);
    $newSecs = array_values(array_unique(array_merge($secs, [$sectionId])));
    sort($newSecs);
    if ($dry) {
        echo "[dry] серия {$s['code']} ID=$id (ACTIVE={$e['ACTIVE']}): разделы " . implode(',', $secs) . ' → ' . implode(',', $newSecs) . ", тип " . ($prevType ?: '—') . " → {$s['type']}\n";
        continue;
    }
    if (!isset($log['series'][$s['code']])) {
        $log['series'][$s['code']] = ['id' => $id, 'sections' => $secs, 'main_section' => (int) $e['IBLOCK_SECTION_ID'], 'type' => $prevType];
        $saveLog();
    }
    \CIBlockElement::SetElementSection($id, $newSecs, false, 0, (int) $e['IBLOCK_SECTION_ID']);
    \CIBlockElement::SetPropertyValuesEx($id, IBLOCK_ID, [TYPE_PROP => $typeEnums[$s['type']]]);
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(IBLOCK_ID, $id);
    $after = sectionsOf($id);
    $main = (int) $DB->Query("SELECT IBLOCK_SECTION_ID FROM b_iblock_element WHERE ID=$id")->Fetch()['IBLOCK_SECTION_ID'];
    $type = (int) ($DB->Query("SELECT VALUE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID={$props[TYPE_PROP]}")->Fetch()['VALUE'] ?? 0);
    if ($after !== $newSecs || $main !== (int) $e['IBLOCK_SECTION_ID'] || $type !== $typeEnums[$s['type']]) {
        throw new RuntimeException("{$s['code']}: не сошлось (разделы " . implode(',', $after) . ", основной $main, тип $type)");
    }
    echo "серия {$s['code']} ID=$id: разделы " . implode(',', $after) . " (основной $main), тип {$s['type']}\n";
}

if (!$dry) {
    $log['finished'] = date('c');
    $saveLog();
    clearCache();
    echo "Кэш очищен. Лог отката: $logFile\n";
}
