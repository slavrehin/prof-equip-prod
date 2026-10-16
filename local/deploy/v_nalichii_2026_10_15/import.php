<?php
/**
 * «Прачечное оборудование в наличии» (ТЗ «В пути» 2026-10-15, правка клиента: раздел «В пути» НЕ создаётся,
 * все позиции ТЗ добавляются в раздел prachechnoe-oborudovanie-v-nalichii с шильдиком «В наличии» = STOCK_BADGE).
 * Данные — products.json (JSON из ТЗ, дословно) + wed45.json (новая карточка WED-45C ET2, собрана по каталогу Danube 2023:
 * ссылка ТЗ вела на другую модель — WEN-45C, решение владельца — новая карточка).
 *
 *  A. new_products (12) + WED-45C — новые карточки: основной раздел + «в наличии», цена EUR, шильдик, фото, тексты, свойства.
 *  B. Новая TWE 18 — уже существующая обычная карточка CODE=professionalnaya-stiralnaya-mashina-tolon-twe-18
 *     (решение владельца: третью TWE18 не создаём). Складская 524 (…-twe-18-zagruzka-18-kg) НЕ трогается.
 *  C. existing_products (кроме WED-45 → см. A): цена EUR, раздел «в наличии» (добавляется к имеющимся), шильдик;
 *     TOLON — новое основное фото, GALLERY очищается (старые ракурсы старого вида; файлы не удаляются).
 *
 * Запуск (в контейнере test3 / на хосте прода с SERVER_NAME=prof-equip.ru):
 *   php local/deploy/v_nalichii_2026_10_15/import.php --photos=/path/v_puti_photos/site --log=/path/rollback.json [--dry] [--only=CODE]
 * Идемпотентен: товары по CODE; фото новой карточки грузится, только если его нет; фото TOLON — только если
 * ORIGINAL_NAME основного фото ещё не новый файл. Значения свойств-списков ищутся среди существующих
 * (без учёта регистра/пробелов, плюс синонимы test3↔прод); новые варианты создаются только у числовых
 * характеристик (объём, мощность и т.п.), для справочных (Тип, Тип предприятия, Тип нагрева, Страна…) — остановка.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
// корень сайта: env SITE_ROOT (скрипт можно запускать из копии test3 против прода), иначе — по расположению файла
$_SERVER['DOCUMENT_ROOT'] = getenv('SITE_ROOT') ?: (($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3));
$_SERVER['SERVER_NAME'] = getenv('SERVER_NAME') ?: (($_SERVER['SERVER_NAME'] ?? '') ?: 'test3.prof-equip.ru');
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
$photosDir = rtrim($opts['photos'] ?? '', '/');
$logFile = $opts['log'] ?? '';
if (!$dry && ($photosDir === '' || $logFile === '')) { throw new RuntimeException('Укажите --photos=… и --log=…'); }

const IBLOCK_ID = 11;
const BRANDS_IBLOCK_ID = 14;
const STOCK_SECTION = 'prachechnoe-oborudovanie-v-nalichii';
const NEW_TWE18_CODE = 'professionalnaya-stiralnaya-mashina-tolon-twe-18';
const OLD_TWE18_CODE = 'professionalnaya-stiralnaya-mashina-tolon-twe-18-zagruzka-18-kg';

// фото из ТЗ → подготовленный квадратный JPEG (v_puti_photos/site)
const PHOTOS = [
    'DD-18, DD-22.png' => 'danube-dd-18-22-silver-et2.jpg',
    'DD-45.png' => 'danube-dd-45-silver-et2.jpg',
    'Сушильные_машины_ES_Smart_Line_18-23-36.png' => 'imesa-sl-es-smart-line.jpg',
    'Сушилки_HG.png' => 'oasis-hg-dryer.jpg',
    'OASISXF40.png' => 'oasis-xf40.jpg',
    'OASIS_SXTP200F_SXTP250F.png' => 'oasis-sxtp200f-250f.jpg',
    'TRD23, TRD28.png' => 'tolon-trd23-trd28-new.jpg',
    'TTD40.png' => 'tolon-ttd40-new.jpg',
    'TWE18, TWE24, TWE40.png' => 'tolon-twe18-24-40-new.jpg',
];

// «parent_category_hint» → CODE раздела
const SECTIONS = [
    'Сушильные машины' => 'sushilnye-mashiny',
    'Стиральные машины' => 'vysokoskorostnye-stiralnye-mashiny',
    'Высокоскоростные стиральные машины' => 'vysokoskorostnye-stiralnye-mashiny',
];
const TIP_TOVARA = [
    'sushilnye-mashiny' => 'Сушильные машины',
    'vysokoskorostnye-stiralnye-mashiny' => 'Высокоскоростные стиральные машины',
];

// attributes из JSON → CODE свойства ('dryer'/'washer' — где одно название ложится на разные свойства)
const ATTR_MAP = [
    'Страна' => 'COUNTRY',
    'Производитель' => 'PROIZVODITEL',
    'Тип Оборудования' => 'TIP_OBORUDOVANIYA',
    'Габаритные Размеры Ш*Г*В, Мм' => 'GABARITNYE_RAZMERY_SH_G_V_MM',
    'Вес, Кг' => 'VES_KG',
    'Тип Предприятия' => 'TIP_PREDPRIYATIYA',
    'Тип Нагрева' => 'TIP_NAGREVA',
    'Мощность Нагрева, Квт' => 'MOSHHNOST_KVT',
    'Мощность, Квт' => 'MOSHHNOST_KVT',
    'Загрузка, Кг' => 'ZAGRUZKA_KG',
    'Объём Барабана, Л' => 'OBYEM_BARABANA_L',
    'Скорость Сушки, Об/Мин' => 'CHASTOTA_VRASHENIYA',
    'Диаметр Барабана, Мм' => 'DIAMETR_BARABANA_MM',
    'Глубина Барабана, Мм' => 'GLUBINA_BARABANA_MM',
    'Диаметр Загрузочного Люка, Мм' => 'DIAMETR_ZAGRUZOCHNOGO_LYUKA',
    'Воздушный Поток, М³/Ч' => 'VOZDUSHNYJ_POTOK_ELEKTRO',
    'Диаметр Вытяжки, Мм' => 'VYVOD_VOZDUHA_DIAMETR_MM',
    'Уровень Шума, Дб' => 'UROVEN_SHUMA_DB',
    'Мощность Двигателя, Квт' => ['dryer' => 'RASHOD_ENERGII_DVIGATEL', 'washer' => 'MOSHHNOST_DVIGATELYA_KVT'],
    'Мощность Вентилятора, Квт' => 'RASHOD_ENERGII_VENTILYATOR',
    'Напряжение, В' => 'NAPRYAZHENIE_V',
    'Реверс Барабана' => 'REVERS_BARABANA',
    'Скорость При Стирке, Об/Мин' => 'SKOROST_PRI_STIRKE_OB_MIN',
    'Скорость При Отжиме, Об/Мин' => 'SKOROST_PRI_OTZHIME_OB_MIN',
    'G-Фактор' => 'G_FACTOR',
    // только в EXTRA
    'Производительность, Кг/Ч' => 'PROIZVODITELNOST_KG_CH',
    'Мощность Газового Нагрева, Квт' => 'MOSHHNOST_GAZ_KVT',
    'Мощность Парового Нагрева, Квт' => 'MOSHNOST_PAR_KVT',
];
// Через запятую — несколько значений
const MULTI_SPLIT = ['TIP_PREDPRIYATIYA', 'TIP_NAGREVA', 'PROIZVODITELNOST_KG_CH'];
// Справочные свойства: значение обязано существовать
const STRICT = ['COUNTRY', 'PROIZVODITEL', 'TIP_OBORUDOVANIYA', 'TIP_PREDPRIYATIYA', 'TIP_NAGREVA', 'REVERS_BARABANA', 'TIP_TOVARA', 'STOCK_BADGE'];
// Синонимы (значение JSON → варианты на test3 / проде)
const SYNONYMS = [
    'TIP_PREDPRIYATIYA' => [
        'прачечная в гостинице' => ['Прачечная гостиницы'], 'прачечная гостиницы' => ['Прачечная в гостинице'],
        'прачечная в больнице' => ['Прачечная больницы'], 'прачечная больницы' => ['Прачечная в больнице'],
    ],
    'TIP_NAGREVA' => ['комбинированный' => ['Электрический/паровой', 'комбинированный – электричество/пар']],
    'NAPRYAZHENIE_V' => ['380–415' => ['380']],
    'REVERS_BARABANA' => ['есть' => ['стандартная комплектация']],
];

// Характеристики, которые в attributes ТЗ не вынесены, но есть в short_description/tech_specs той же модели
// (у Danube DD в attributes только 5 полей — без них фильтры «Тип нагрева» и т.п. на этих товарах пусты)
const EXTRA = [
    'professionalnaya-sushilnaya-mashina-danube-dd-18-silver-et2-zagruzka-18-kg' => [
        'Тип Оборудования' => 'Сушильное оборудование', 'Тип Нагрева' => 'электрический, газовый, паровой',
        'Габаритные Размеры Ш*Г*В, Мм' => '785*1125*1694', 'Вес, Кг' => '187', 'Мощность Нагрева, Квт' => '18',
        'Мощность Газового Нагрева, Квт' => '20,5', 'Мощность Парового Нагрева, Квт' => '30',
        'Производительность, Кг/Ч' => '28,3, 31,9', 'Диаметр Барабана, Мм' => '750', 'Глубина Барабана, Мм' => '746',
        'Диаметр Загрузочного Люка, Мм' => '574', 'Воздушный Поток, М³/Ч' => '850', 'Диаметр Вытяжки, Мм' => '200',
        'Уровень Шума, Дб' => '64', 'Реверс Барабана' => 'есть',
    ],
    'professionalnaya-sushilnaya-mashina-danube-dd-22-silver-et2-zagruzka-22-kg' => [
        'Тип Оборудования' => 'Сушильное оборудование', 'Тип Нагрева' => 'электрический, газовый, паровой',
        'Габаритные Размеры Ш*Г*В, Мм' => '890*1255*1812', 'Вес, Кг' => '210', 'Мощность Нагрева, Квт' => '24',
        'Мощность Газового Нагрева, Квт' => '31', 'Мощность Парового Нагрева, Квт' => '49,2',
        'Производительность, Кг/Ч' => '34, 40', 'Диаметр Барабана, Мм' => '835', 'Глубина Барабана, Мм' => '800',
        'Диаметр Загрузочного Люка, Мм' => '574', 'Воздушный Поток, М³/Ч' => '1200', 'Диаметр Вытяжки, Мм' => '200',
        'Уровень Шума, Дб' => '65', 'Реверс Барабана' => 'есть',
    ],
    'professionalnaya-sushilnaya-mashina-danube-dd-45-silver-et2-zagruzka-45-kg' => [
        'Тип Оборудования' => 'Сушильное оборудование', 'Тип Нагрева' => 'электрический, газовый, паровой',
        'Габаритные Размеры Ш*Г*В, Мм' => '1270*1106*2380', 'Вес, Кг' => '395', 'Мощность Нагрева, Квт' => '54',
        'Мощность Газового Нагрева, Квт' => '60', 'Мощность Парового Нагрева, Квт' => '91',
        'Производительность, Кг/Ч' => '80,4, 84,4', 'Диаметр Барабана, Мм' => '1225', 'Глубина Барабана, Мм' => '760',
        'Диаметр Загрузочного Люка, Мм' => '802', 'Воздушный Поток, М³/Ч' => '3000', 'Диаметр Вытяжки, Мм' => '300',
        'Уровень Шума, Дб' => '67', 'Реверс Барабана' => 'есть',
    ],
    // IMESA ES Smart: реверс в стандартной комплектации (по описанию из ТЗ)
    'mashina-sushilnaya-imesa-sl-es-18-e-c-zagruzka-18-kg' => ['Реверс Барабана' => 'есть'],
    'mashina-sushilnaya-imesa-sl-es-23-e-c-zagruzka-23-kg' => ['Реверс Барабана' => 'есть'],
    'mashina-sushilnaya-imesa-sl-es-36-e-c-zagruzka-36-kg' => ['Реверс Барабана' => 'есть'],
];

$isTest3 = !is_writable($_SERVER['DOCUMENT_ROOT'] . '/upload/iblock');
$uploadSubdir = $isTest3 ? 'import_test' : 'iblock';

function norm(string $v): string
{
    return mb_strtolower(str_replace([' ', "\u{00A0}", '×'], ['', '', '*'], trim($v)));
}

$props = [];
$rs = \CIBlockProperty::GetList([], ['IBLOCK_ID' => IBLOCK_ID]);
while ($p = $rs->Fetch()) { $props[$p['CODE']] = $p; }

$enumCache = [];
$newEnums = [];
/** ID варианта списка (или число для свойства N) */
function propValue(string $code, string $value, bool $dry)
{
    global $props, $enumCache, $newEnums;
    $p = $props[$code] ?? null;
    if (!$p) { throw new RuntimeException("Нет свойства $code"); }
    $value = trim($value);
    if ($p['PROPERTY_TYPE'] === 'N') { return (float) str_replace(',', '.', $value); }
    if ($p['PROPERTY_TYPE'] !== 'L') { return $value; }
    if (!isset($enumCache[$code])) {
        $enumCache[$code] = [];
        $re = \CIBlockPropertyEnum::GetList(['SORT' => 'ASC', 'ID' => 'ASC'], ['PROPERTY_ID' => $p['ID']]);
        while ($e = $re->Fetch()) { $enumCache[$code][norm($e['VALUE'])] ??= (int) $e['ID']; }
    }
    $candidates = array_merge([$value], SYNONYMS[$code][mb_strtolower($value)] ?? []);
    foreach ($candidates as $c) {
        if (isset($enumCache[$code][norm($c)])) { return $enumCache[$code][norm($c)]; }
    }
    if (in_array($code, STRICT, true)) { throw new RuntimeException("$code: нет значения «{$value}» среди существующих — нужно решение"); }
    $newEnums[] = "$code = $value";
    if ($dry) { return 0; }
    $xml = class_exists('CatalogStandard') ? \CatalogStandard::slug($value) : md5($code . $value);
    $id = (int) (new \CIBlockPropertyEnum())->Add(['PROPERTY_ID' => $p['ID'], 'VALUE' => $value, 'XML_ID' => $xml ?: md5($value), 'SORT' => 500]);
    if (!$id) { throw new RuntimeException("$code: не создано значение «{$value}»"); }
    return $enumCache[$code][norm($value)] = $id;
}

function findByCode(string $code): ?array
{
    $r = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $code], false, false,
        ['ID', 'NAME', 'ACTIVE', 'IBLOCK_SECTION_ID', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'])->Fetch();
    return $r ?: null;
}

function sectionId(string $code): int
{
    $s = \CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $code], false, ['ID'])->Fetch();
    if (!$s) { throw new RuntimeException("Нет раздела $code"); }
    return (int) $s['ID'];
}

function groups(int $id): array
{
    global $DB;
    $out = [];
    $r = $DB->Query("SELECT IBLOCK_SECTION_ID FROM b_iblock_section_element WHERE IBLOCK_ELEMENT_ID=$id AND ADDITIONAL_PROPERTY_ID IS NULL ORDER BY IBLOCK_SECTION_ID");
    while ($g = $r->Fetch()) { $out[] = (int) $g['IBLOCK_SECTION_ID']; }
    return $out;
}

function saveImage(string $path, string $name, string $alt, string $subdir): int
{
    if (!is_file($path)) { throw new RuntimeException("Нет файла $path"); }
    $arr = \CFile::MakeFileArray($path);
    $arr['name'] = $name;
    $arr['description'] = $alt;
    $arr['MODULE_ID'] = 'iblock';
    $id = (int) \CFile::SaveFile($arr, $subdir);
    if (!$id) { throw new RuntimeException("Файл $path не сохранён"); }
    return $id;
}

function setPrice(int $id, float $eur): array
{
    if (!\CCatalogProduct::GetByID($id)) {
        \CCatalogProduct::Add(['ID' => $id, 'QUANTITY' => 0, 'MEASURE' => 796]);
    }
    $fields = ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => 1, 'PRICE' => $eur, 'CURRENCY' => 'EUR'];
    $pr = \CPrice::GetList([], ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => 1])->Fetch();
    if ($pr) { \CPrice::Update((int) $pr['ID'], $fields); } else { \CPrice::Add($fields); }
    return $pr ? ['PRICE' => (float) $pr['PRICE'], 'CURRENCY' => $pr['CURRENCY']] : [];
}

/** Добавить раздел к привязкам, не меняя основной раздел */
function addSection(int $id, int $sectionId): void
{
    global $DB;
    $cur = groups($id);
    if (in_array($sectionId, $cur, true)) { return; }
    $main = (int) $DB->Query("SELECT IBLOCK_SECTION_ID FROM b_iblock_element WHERE ID=$id")->Fetch()['IBLOCK_SECTION_ID'];
    \CIBlockElement::SetElementSection($id, array_merge($cur, [$sectionId]), false, 0, $main ?: null);
    $DB->Query("UPDATE b_iblock_element SET IBLOCK_SECTION_ID=$main WHERE ID=$id AND $main > 0");
}

function ul(array $items): string
{
    return "<ul>\n" . implode("\n", array_map(static fn($s) => "\t<li>" . htmlspecialcharsbx($s) . '</li>', $items)) . "\n</ul>";
}

$stockSection = sectionId(STOCK_SECTION);
$badgeEnum = propValue('STOCK_BADGE', 'Показывать', $dry);
$brands = [];
$rb = \CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK_ID, 'CODE' => ['danube', 'imesa', 'oasis', 'tolon']], false, false, ['ID', 'CODE']);
while ($b = $rb->Fetch()) { $brands[$b['CODE']] = (int) $b['ID']; }
if (count($brands) !== 4) { throw new RuntimeException('Не найдены бренды: ' . json_encode($brands)); }

$data = json_decode(file_get_contents(__DIR__ . '/products.json'), true, 512, JSON_THROW_ON_ERROR);
$wed45 = json_decode(file_get_contents(__DIR__ . '/wed45.json'), true, 512, JSON_THROW_ON_ERROR);
$newProducts = $data['new_products'];
$newProducts[] = $wed45;

$log = is_file($logFile) ? json_decode(file_get_contents($logFile), true, 512, JSON_THROW_ON_ERROR) : [];
$log += ['site' => $_SERVER['SERVER_NAME'], 'created' => [], 'changed' => []];
$saveLog = static function () use (&$log, $logFile, $dry) {
    if (!$dry) { file_put_contents($logFile, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)); }
};
$report = [];

// ---------------- A. Новые карточки ----------------
foreach ($newProducts as $p) {
    $code = $p['suggested_slug'];
    if ($only && $only !== $code) { continue; }
    $sectionCode = SECTIONS[$p['parent_category_hint']] ?? null;
    if (!$sectionCode) { throw new RuntimeException("$code: неизвестный раздел {$p['parent_category_hint']}"); }
    $mainSection = sectionId($sectionCode);
    $tipTovara = TIP_TOVARA[$sectionCode];
    $kind = $sectionCode === 'sushilnye-mashiny' ? 'dryer' : 'washer';
    // WED-45C: раздел и «Тип» — как у соседней модели серии (на проде WED лежат в «Стирально-отжимных машинах»,
    // на test3 после реструктуризации — в «Высокоскоростных»)
    if (!empty($p['image_from_code'])) {
        $sib = findByCode($p['image_from_code']);
        $mainSection = (int) $sib['IBLOCK_SECTION_ID'];
        $t = \CIBlockElement::GetProperty(IBLOCK_ID, (int) $sib['ID'], [], ['CODE' => 'TIP_TOVARA'])->Fetch();
        if (!$mainSection || empty($t['VALUE_ENUM'])) { throw new RuntimeException("$code: у {$p['image_from_code']} нет раздела/«Типа»"); }
        $tipTovara = $t['VALUE_ENUM'];
    }
    $brandCode = strtolower($p['brand']);

    // свойства
    $values = [
        'BRAND' => [$brands[$brandCode]],
        'TIP_TOVARA' => propValue('TIP_TOVARA', $tipTovara, $dry),
        'MODEL' => [propValue('MODEL', $p['model'], $dry)],
        'STOCK_BADGE' => $badgeEnum,
    ];
    $attrs = $p['attributes'] + (EXTRA[$code] ?? []);
    foreach ($attrs as $name => $value) {
        $map = ATTR_MAP[$name] ?? null;
        if ($map === null) { throw new RuntimeException("$code: нет соответствия для характеристики «{$name}»"); }
        $propCode = is_array($map) ? $map[$kind] : $map;
        // разделитель — запятая с пробелом («28,3, 31,9» → 28,3 и 31,9; десятичная запятая без пробела не режется)
        $parts = in_array($propCode, MULTI_SPLIT, true) ? preg_split('/,\s+/u', $value) : [$value];
        $ids = array_map(static fn($v) => propValue($propCode, $v, $dry), $parts);
        $values[$propCode] = $props[$propCode]['MULTIPLE'] === 'Y' ? $ids : $ids[0];
    }

    $preview = ul($p['short_description']);
    $detail = '<h2>' . htmlspecialcharsbx($p['title']) . "</h2>\n"
        . implode("\n", array_map(static fn($s) => '<p>' . htmlspecialcharsbx($s) . '</p>', $p['description']));
    if (!empty($p['tech_specs'])) {
        $detail .= "\n<h2>Технические характеристики</h2>\n" . ul($p['tech_specs']);
    }

    $photoName = $p['image_file'] ?? null;
    $photoPath = $photoName ? "$photosDir/" . PHOTOS[$photoName] : null;
    $existing = findByCode($code);
    if ($dry) {
        echo "[dry] A " . ($existing ? "update ID={$existing['ID']}" : 'create') . " $code → section $mainSection + " . STOCK_SECTION
            . ", {$p['price_eur']} EUR, свойств " . count($values) . ', фото ' . ($photoName ?? ('копия у ' . $p['image_from_code'])) . "\n";
        continue;
    }

    $fields = [
        'IBLOCK_ID' => IBLOCK_ID,
        'IBLOCK_SECTION_ID' => $mainSection,
        'IBLOCK_SECTION' => [$mainSection, $stockSection],
        'NAME' => $p['title'],
        'CODE' => $code,
        'PREVIEW_TEXT' => $preview,
        'PREVIEW_TEXT_TYPE' => 'html',
        'DETAIL_TEXT' => $detail,
        'DETAIL_TEXT_TYPE' => 'html',
    ];
    $el = new \CIBlockElement();
    if ($existing) {
        $id = (int) $existing['ID'];
        if (!$el->Update($id, $fields)) { throw new RuntimeException("$code: update: " . $el->LAST_ERROR); }
        $status = 'updated';
    } else {
        $fields += ['ACTIVE' => 'Y', 'SORT' => 500];
        $id = (int) $el->Add($fields);
        if (!$id) { throw new RuntimeException("$code: add: " . $el->LAST_ERROR); }
        $status = 'created';
        $log['created'][$code] = $id;
        $saveLog();
    }
    setPrice($id, (float) $p['price_eur']);
    \CIBlockElement::SetPropertyValuesEx($id, IBLOCK_ID, $values);

    $fileId = (int) ($existing['DETAIL_PICTURE'] ?? 0);
    if (!$fileId) {
        if ($photoPath) {
            $fileId = saveImage($photoPath, "$code.jpg", $p['title'], $uploadSubdir);
        } else {
            // WED-45C: фото серии WED — копия файла карточки-образца (не общий ID: удаление одной карточки не должно убить фото другой)
            $src = findByCode($p['image_from_code']);
            $srcPath = $_SERVER['DOCUMENT_ROOT'] . \CFile::GetPath((int) $src['DETAIL_PICTURE']);
            $fileId = saveImage($srcPath, "$code." . pathinfo($srcPath, PATHINFO_EXTENSION), $p['title'], $uploadSubdir);
        }
        $DB->Query("UPDATE b_iblock_element SET PREVIEW_PICTURE=$fileId, DETAIL_PICTURE=$fileId, TIMESTAMP_X=NOW() WHERE ID=$id");
    }

    $tpl = new \Bitrix\Iblock\InheritedProperty\ElementTemplates(IBLOCK_ID, $id);
    $tpl->set([
        'ELEMENT_META_TITLE' => $p['title'] . ' купить в ПРОФЭКВИП',
        'ELEMENT_META_DESCRIPTION' => 'Купить ' . $p['title'] . ' по выгодной цене в компании Профэквип. Купить с доставкой по России. Заказывайте на сайте или по телефону.',
    ]);
    (new \Bitrix\Iblock\InheritedProperty\ElementValues(IBLOCK_ID, $id))->clearValues();
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(IBLOCK_ID, $id);

    // проверка по базе
    $chk = $DB->Query("SELECT IBLOCK_SECTION_ID, DETAIL_PICTURE FROM b_iblock_element WHERE ID=$id")->Fetch();
    $g = groups($id);
    sort($g);
    $want = [$mainSection, $stockSection];
    sort($want);
    if ((int) $chk['IBLOCK_SECTION_ID'] !== $mainSection || $g !== $want || (int) $chk['DETAIL_PICTURE'] !== $fileId) {
        throw new RuntimeException("$code: запись не сошлась (раздел {$chk['IBLOCK_SECTION_ID']}, привязки " . implode(',', $g) . ", фото {$chk['DETAIL_PICTURE']})");
    }
    $report[] = "A $status $code → ID=$id, file $fileId";
}

// ---------------- B + C. Существующие карточки ----------------
$existingList = [['code' => NEW_TWE18_CODE, 'price_eur' => $data['clone_products'][0]['price_eur'], 'replace_photo' => $data['clone_products'][0]['image_file']]];
foreach ($data['existing_products'] as $p) {
    if ($p['model'] === 'WED-45') { continue; }   // ссылка вела на WEN-45C — вместо неё новая карточка WED-45C (A)
    $existingList[] = ['code' => basename(parse_url($p['url'], PHP_URL_PATH)), 'price_eur' => $p['price_eur'], 'replace_photo' => $p['replace_photo']];
}
$galleryId = (int) $props['GALLERY']['ID'];
$badgePropId = (int) $props['STOCK_BADGE']['ID'];

foreach ($existingList as $p) {
    $code = $p['code'];
    if ($only && $only !== $code) { continue; }
    if ($code === OLD_TWE18_CODE) { throw new RuntimeException('Складская TWE18 не должна меняться'); }
    $e = findByCode($code);
    if (!$e) { throw new RuntimeException("Нет товара $code"); }
    $id = (int) $e['ID'];
    $newFile = $p['replace_photo'] ? PHOTOS[$p['replace_photo']] : null;
    $curOrig = $e['DETAIL_PICTURE'] ? (\CFile::GetFileArray((int) $e['DETAIL_PICTURE'])['ORIGINAL_NAME'] ?? '') : '';
    $photoDone = $newFile && $curOrig === "$code.jpg";
    if ($dry) {
        echo "[dry] C ID=$id $code: {$p['price_eur']} EUR, +" . STOCK_SECTION . ' (сейчас ' . implode(',', groups($id)) . ')'
            . ($newFile ? ", фото → $newFile" . ($photoDone ? ' (уже)' : '') : '') . "\n";
        continue;
    }

    if (!isset($log['changed'][$code])) {
        $pr = \CPrice::GetList([], ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => 1])->Fetch();
        $gal = [];
        $rg = $DB->Query("SELECT VALUE_NUM FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID=$galleryId ORDER BY ID");
        while ($r = $rg->Fetch()) { $gal[] = (int) $r['VALUE_NUM']; }
        $badge = $DB->Query("SELECT VALUE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID=$badgePropId")->Fetch();
        $log['changed'][$code] = [
            'id' => $id,
            'price' => $pr ? ['PRICE' => (float) $pr['PRICE'], 'CURRENCY' => $pr['CURRENCY']] : null,
            'sections' => groups($id),
            'main_section' => (int) $e['IBLOCK_SECTION_ID'],
            'stock_badge' => $badge ? (int) $badge['VALUE'] : null,
            'preview' => (int) $e['PREVIEW_PICTURE'],
            'detail' => (int) $e['DETAIL_PICTURE'],
            'gallery' => $gal,
        ];
        $saveLog();
    }

    setPrice($id, (float) $p['price_eur']);
    addSection($id, $stockSection);
    \CIBlockElement::SetPropertyValuesEx($id, IBLOCK_ID, ['STOCK_BADGE' => $badgeEnum]);

    $note = '';
    if ($newFile && !$photoDone) {
        $fileId = saveImage("$photosDir/$newFile", "$code.jpg", '', $uploadSubdir);
        $DB->Query("UPDATE b_iblock_element SET PREVIEW_PICTURE=$fileId, DETAIL_PICTURE=$fileId, TIMESTAMP_X=NOW() WHERE ID=$id");
        // в GALLERY лежали доп. ракурсы старого вида — убираются (файлы остаются); основное фото шаблон показывает сам,
        // дублировать его в GALLERY нельзя — в карточке будет две одинаковые миниатюры
        $DB->Query("DELETE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID=$galleryId");
        $note = ", фото file $fileId";
    }
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(IBLOCK_ID, $id);

    $chk = $DB->Query("SELECT IBLOCK_SECTION_ID FROM b_iblock_element WHERE ID=$id")->Fetch();
    if ((int) $chk['IBLOCK_SECTION_ID'] !== (int) $log['changed'][$code]['main_section'] || !in_array($stockSection, groups($id), true)) {
        throw new RuntimeException("$code: разделы не сошлись");
    }
    $report[] = "C ID=$id $code: {$p['price_eur']} EUR, разделы " . implode(',', groups($id)) . $note;
}

if ($newEnums) { echo ($dry ? '[dry] будут созданы' : 'Созданы') . " значения списков:\n  " . implode("\n  ", array_unique($newEnums)) . "\n"; }
echo implode("\n", $report), "\n";
if (!$dry) {
    foreach (['cache', 'managed_cache', 'stack_cache'] as $d) {
        foreach (glob($_SERVER['DOCUMENT_ROOT'] . "/bitrix/$d/*") ?: [] as $f) { \Bitrix\Main\IO\Directory::deleteDirectory($f); }
    }
    echo "Кэш очищен. Откат: $logFile\n";
}
