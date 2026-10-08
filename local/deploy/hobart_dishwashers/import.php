<?php
/**
 * Hobart: 9 посудомоечных / стаканомоечных машин в раздел «Посудомоечное оборудование».
 * Данные — products.json (из products.json владельца 2026-10-08), картинки — папка --photos.
 * Свойство «Тип» (TIP_POSUDOMOECHNOJ_MASHINY) и значение Hobart у PROIZVODITEL создаёт миграция
 * local/migrations/2026-10-08-hobart-dishwasher-type.php (запускать её первой).
 *
 * Запуск (в контейнере test3 / на хосте прода с SERVER_NAME=prof-equip.ru):
 *   php local/deploy/hobart_dishwashers/import.php --photos=/path/hobart_photos --created=/path/created.json
 *       [--only=hobart-f504-12b-ecomax] [--draft] [--dry]
 *
 * --draft — новые товары создаются с ACTIVE=N (для прода); у существующих ACTIVE не меняется.
 * Цен нет — товар каталога без цены (карточка показывает «Запросить стоимость»).
 * Картинка: файл сохраняется штатно под именем {slug}.png (CFile::SaveFile, описание файла = alt),
 * ID ставится элементу прямым SQL (см. «Известные грабли» в CLAUDE.md). test3 — upload/import_test,
 * прод — upload/iblock.
 * Идемпотентен: товар ищется по CODE (slug); если артикул (SPECS «Артикул производителя») уже есть
 * у другого товара — остановка. Картинка загружается, только если у товара её ещё нет
 * (иначе обновляется только alt). Созданные/обновлённые ID дописываются в --created.
 * «Производитель» из characteristics в SPECS не пишется — его показывает свойство-список PROIZVODITEL.
 * Названия характеристик сводятся к единому словарю (SPEC_RENAME) и единому порядку блоков
 * (SPEC_ORDER: общие → мойка → электрика → габариты → оснащение) — решение владельца 2026-10-08.
 * products.json остаётся дословным; неизвестное название или дубль после сведения — остановка.
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
const SKU_SPEC = 'Артикул производителя';

// Единый порядок характеристик по блокам
const SPEC_ORDER = [
    // общие
    'Серия', 'Модель', 'Артикул производителя', 'Тип оборудования', 'Тип установки',
    // мойка
    'Размер корзины', 'Производительность', 'Производительность по тарелкам', 'Производительность по стаканам',
    'Продолжительность цикла', 'Количество автоматических программ', 'Высота загрузки', 'Максимальный диаметр тарелок',
    'Объём моечного бака', 'Расход воды за цикл', 'Температура мойки', 'Температура ополаскивания',
    // электрика
    'Мощность моечного насоса', 'Мощность нагрева бака', 'Мощность бойлера', 'Мощность бойлера при 230 В',
    'Общая подключаемая мощность', 'Общая подключаемая мощность при 230 В', 'Электропитание', 'Возможность перенастройки',
    // габариты
    'Ширина', 'Глубина', 'Высота', 'Высота с открытым куполом', 'Габариты, Ш×Г×В', 'Масса без упаковки', 'Уровень шума',
    // оснащение
    'Панель управления', 'Дозатор моющего средства', 'Дозатор ополаскивающего средства', 'Насос ополаскивания',
    'Сливной насос', 'Моющие рукава', 'Thermostop', 'Thermolabel 71 °C', 'Гигиеническая программа', 'Программа самоочистки',
    'Genius-X²', 'SensoActive', 'Wi-Fi', 'SmartConnect', 'USB-интерфейс', 'Multi-Phasing', 'SOFT-START', 'CLIP-IN',
    'Двойная стенка двери', 'Материал корпуса', 'Гарантия',
];

// Синонимы из карточек → единое название
const SPEC_RENAME = [
    'Линейка' => 'Серия',
    'Размер кассеты' => 'Размер корзины',
    'Продолжительность программ' => 'Продолжительность цикла',
    'Моечные циклы' => 'Продолжительность цикла',
    'Максимальная высота загрузки' => 'Высота загрузки',
    'Расход воды' => 'Расход воды за цикл',
    'Нагрев бака' => 'Мощность нагрева бака',
    'Мощность нагревателя' => 'Мощность бойлера',
    'Мощность бойлера, 400 В' => 'Мощность бойлера',
    'Мощность бойлера, заводская' => 'Мощность бойлера',
    'Мощность бойлера, адаптируемая' => 'Мощность бойлера при 230 В',
    'Общая подключаемая мощность, 400 В' => 'Общая подключаемая мощность',
    'Общая подключаемая мощность, 230 В' => 'Общая подключаемая мощность при 230 В',
    'Общая мощность' => 'Общая подключаемая мощность',
    'Общая мощность, заводская' => 'Общая подключаемая мощность',
    'Мощность' => 'Общая подключаемая мощность',
    'Напряжение' => 'Электропитание',
    'Электропитание, стандарт' => 'Электропитание',
    'Высота с закрытым куполом' => 'Высота',
    'Габариты (Ш×Г×В)' => 'Габариты, Ш×Г×В',
    'Насос слива' => 'Сливной насос',
    'Дренажный насос' => 'Сливной насос',
    'Сливная помпа' => 'Сливной насос',
    'Система Thermostop' => 'Thermostop',
    'Система Genius-X²' => 'Genius-X²',
    'Система GENIUS-X²' => 'Genius-X²',
    'Система SensoActive' => 'SensoActive',
    'Система SENSO-ACTIVE' => 'SensoActive',
    'Самоочистка' => 'Программа самоочистки',
    'Автоматическая самоочистка' => 'Программа самоочистки',
    'USB для сервисного доступа' => 'USB-интерфейс',
];

/** characteristics[] → [[name, value], …] в едином словаре и порядке */
function normalizeSpecs(string $slug, array $characteristics): array
{
    $rows = [];
    foreach ($characteristics as $c) {
        [$name, $value] = [$c['name'], $c['value']];
        if ($name === 'Производитель') { continue; }
        if ($name === 'Температура мойки / ополаскивания') {   // FXL-10C: одна строка → две
            [$wash, $rinse] = array_map('trim', explode(' / ', $value, 2));
            $rows[] = ['Температура мойки', $wash];
            $rows[] = ['Температура ополаскивания', $rinse];
            continue;
        }
        if ($name === 'VISIOTRONIC-TOUCH' && $value === 'есть') {   // GX-10C: как у FXL/AMX
            $rows[] = ['Панель управления', 'Visiotronic-touch'];
            continue;
        }
        $rows[] = [SPEC_RENAME[$name] ?? $name, $value];
    }
    $pos = array_flip(SPEC_ORDER);
    $seen = [];
    foreach ($rows as [$name]) {
        if (!isset($pos[$name])) { throw new RuntimeException("$slug: характеристика «{$name}» не в словаре SPEC_ORDER"); }
        if (isset($seen[$name])) { throw new RuntimeException("$slug: «{$name}» дважды после сведения названий"); }
        $seen[$name] = true;
    }
    usort($rows, static fn($a, $b) => $pos[$a[0]] <=> $pos[$b[0]]);
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

$data = json_decode(file_get_contents(__DIR__ . '/products.json'), true, 512, JSON_THROW_ON_ERROR);

$props = [];
$rs = \CIBlockProperty::GetList([], ['IBLOCK_ID' => IBLOCK_ID]);
while ($p = $rs->Fetch()) { $props[$p['CODE']] = (int) $p['ID']; }
foreach (['SPECS', 'BRAND', 'PROIZVODITEL', TYPE_PROP] as $code) {
    if (empty($props[$code])) { throw new RuntimeException("Нет свойства $code (запустите миграцию)."); }
}
$enumHobart = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $props['PROIZVODITEL'], 'VALUE' => 'Hobart'])->Fetch();
if (!$enumHobart) { throw new RuntimeException('Нет значения Hobart у PROIZVODITEL (миграция?)'); }
$typeEnums = [];
$rs = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $props[TYPE_PROP]]);
while ($e = $rs->Fetch()) { $typeEnums[$e['XML_ID']] = (int) $e['ID']; }

$brand = \CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK_ID, 'CODE' => 'hobart'], false, false, ['ID'])->Fetch();
if (!$brand) { throw new RuntimeException('Нет бренда Hobart в инфоблоке брендов.'); }
$brandId = (int) $brand['ID'];

$section = \CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => SECTION_CODE], false, ['ID'])->Fetch();
if (!$section) { throw new RuntimeException('Нет раздела ' . SECTION_CODE); }
$sectionId = (int) $section['ID'];

$created = is_file($createdFile) ? json_decode(file_get_contents($createdFile), true, 512, JSON_THROW_ON_ERROR) : [];
$created += ['site' => $_SERVER['SERVER_NAME'], 'products' => []];

foreach ($data['products'] as $p) {
    if ($only && $only !== $p['slug']) { continue; }
    if ($p['title'] !== $p['seo']['h1']) { throw new RuntimeException("{$p['slug']}: title ≠ seo.h1 — H1 на сайте = название, нужно решение"); }
    if (empty($typeEnums[$p['type']])) { throw new RuntimeException("{$p['slug']}: нет значения «Тип» {$p['type']}"); }
    $photo = "$photosDir/{$p['image']['file']}";
    if (!$dry && !is_file($photo)) { throw new RuntimeException("{$p['slug']}: нет фото {$p['image']['file']}"); }

    $existing = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $p['slug']], false, false, ['ID', 'DETAIL_PICTURE', 'ACTIVE'])->Fetch();
    // дубль по артикулу под другим CODE
    $skuOwner = $DB->Query("SELECT e.ID, e.CODE FROM b_iblock_element_property ep JOIN b_iblock_element e ON e.ID = ep.IBLOCK_ELEMENT_ID
        WHERE ep.IBLOCK_PROPERTY_ID = {$props['SPECS']} AND ep.DESCRIPTION = '" . $DB->ForSql(SKU_SPEC) . "' AND ep.VALUE = '" . $DB->ForSql($p['sku']) . "'"
        . ($existing ? " AND e.ID <> {$existing['ID']}" : ''))->Fetch();
    if ($skuOwner) { throw new RuntimeException("{$p['slug']}: артикул {$p['sku']} уже у товара ID={$skuOwner['ID']} ({$skuOwner['CODE']})"); }

    $specs = [];
    foreach (normalizeSpecs($p['slug'], $p['characteristics']) as [$name, $value]) {
        $specs[] = ['VALUE' => $value, 'DESCRIPTION' => $name];
    }
    if ($dry) {
        echo "[dry] {$p['slug']} → " . ($existing ? "update ID={$existing['ID']}" : 'create') . ", тип {$p['type']}, " . count($specs) . " хар., фото {$p['image']['file']}\n";
        if (isset($opts['show-specs'])) {
            foreach ($specs as $s) { echo "      {$s['DESCRIPTION']}: {$s['VALUE']}\n"; }
        }
        continue;
    }

    $fields = [
        'IBLOCK_ID' => IBLOCK_ID,
        'IBLOCK_SECTION_ID' => $sectionId,
        'IBLOCK_SECTION' => [$sectionId],
        'NAME' => $p['title'],
        'CODE' => $p['slug'],
        'PREVIEW_TEXT' => '<p>' . htmlspecialcharsbx($p['short_description']) . '</p>',
        'PREVIEW_TEXT_TYPE' => 'html',
        'DETAIL_TEXT' => $p['description_html'],
        'DETAIL_TEXT_TYPE' => 'html',
    ];
    $el = new \CIBlockElement();
    if ($existing) {
        $id = (int) $existing['ID'];
        if (!$el->Update($id, $fields)) { throw new RuntimeException("{$p['slug']}: update: " . $el->LAST_ERROR); }
        $status = 'updated';
    } else {
        $fields['ACTIVE'] = $draft ? 'N' : 'Y';
        $fields['SORT'] = 500;
        $fields['PROPERTY_VALUES'] = [];
        $id = (int) $el->Add($fields);
        if (!$id) { throw new RuntimeException("{$p['slug']}: add: " . $el->LAST_ERROR); }
        $status = 'created';
    }

    // товар каталога без цены («Запросить стоимость»)
    if (!\CCatalogProduct::GetByID($id)) {
        \CCatalogProduct::Add(['ID' => $id, 'QUANTITY' => 0, 'MEASURE' => 796]);
    }

    \CIBlockElement::SetPropertyValuesEx($id, IBLOCK_ID, [
        'BRAND' => [$brandId],
        'PROIZVODITEL' => (int) $enumHobart['ID'],
        TYPE_PROP => $typeEnums[$p['type']],
        'SPECS' => $specs,
    ]);
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(IBLOCK_ID, $id);

    $fileId = $existing['DETAIL_PICTURE'] ?? null;
    if (!$fileId) {
        $fileId = saveImage($photo, $p['slug'] . '.' . strtolower(pathinfo($photo, PATHINFO_EXTENSION)), $p['image']['alt'], $uploadSubdir);
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
        throw new RuntimeException("{$p['slug']}: запись не сошлась (раздел {$chk['IBLOCK_SECTION_ID']}, привязок $cnt, фото {$chk['DETAIL_PICTURE']}, хар. $specCnt)");
    }

    $created['products'][$p['sku']] = [
        'element_id' => $id,
        'code' => $p['slug'],
        'file_id' => (int) $fileId,
        'status' => $status,
        'active' => $chk['ACTIVE'],
        'at' => date('c'),
    ] + (isset($created['products'][$p['sku']]) && $status === 'updated' ? ['first' => $created['products'][$p['sku']]['first'] ?? $created['products'][$p['sku']]['at']] : []);
    file_put_contents($createdFile, json_encode($created, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    echo "$status {$p['slug']} → ID=$id, file $fileId, ACTIVE={$chk['ACTIVE']}, хар. $specCnt\n";
}

if (!$dry) {
    clearCache();
    echo "Кэш очищен\n";
}
