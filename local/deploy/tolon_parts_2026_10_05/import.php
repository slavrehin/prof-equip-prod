<?php
/**
 * 3 запчасти из «остатки_остатков.docx» (2026-10-05):
 *   TH0110040307 — пневматический дисковый затвор TOLON TWE110 → «Запасные части TOLON»;
 *   304576       — устройство для установки замков Clipper MAQAGR 50 (Valmet, сервисный инструмент)
 *                  → «Запасные части для прачечного оборудования» (без подраздела);
 *   TH0250150036 — панель кнопки аварийной остановки TOLON → «Запасные части TOLON».
 *                  На test3 этот артикул уже был карточкой «Информационная лампа» (ID 2448,
 *                  без фото, на проде не было) — по решению владельца она переписывается в панель
 *                  (поиск и по старому CODE из $oldCodes).
 *
 * Формат — как у остальных запчастей TOLON: NAME «English / Русское для TOLON …», артикул в
 * свойстве и CODE, короткое описание — <ul> с <strong>-подписями, описание — абзацы через
 * <br><br>, цена 0 RUB (как у всех запчастей). Характеристики, для которых нет свойств
 * (тип привода, ширина ленты, замки, шаг), — только строками в коротком описании и в тексте.
 * Бренд Valmet (элемент инфоблока брендов + значение PROIZVODITEL) создаётся, если его нет.
 *
 * Запуск:
 *   docker compose exec web php /var/www/html/local/deploy/tolon_parts_2026_10_05/import.php --photos=/tmp/tolon_photos [--dry]   # test3
 *   php local/deploy/tolon_parts_2026_10_05/import.php --photos=/var/www/prof-equip-test/tolon_photos [--dry]                      # прод
 * Идемпотентен: товар ищется по CODE; фото загружаются, только если у товара их ещё нет.
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
$photosDir = rtrim($opts['photos'] ?? '/tmp/tolon_photos', '/');

const IBLOCK_ID = 11;
const BRANDS_IBLOCK_ID = 14;

$isTest3 = is_link($_SERVER['DOCUMENT_ROOT'] . '/upload/iblock') || !is_writable($_SERVER['DOCUMENT_ROOT'] . '/upload/iblock');
$uploadSubdir = $isTest3 ? 'import_test' : 'iblock';

function ul(array $rows): string
{
    $li = [];
    foreach ($rows as $k => $v) { $li[] = '<li><strong>' . $k . ':</strong> ' . $v . '</li>'; }
    return "<ul>\n" . implode("\n", $li) . "\n</ul>";
}

$products = [
    [
        'sku' => 'TH0110040307',
        'photo' => 'ПНЕВМАТИЧЕСКИЙ ДИСКОВЫЙ ЗАТВОР ДЛЯ TOLON TWE110, арт. TH0110040307.png',
        'section' => 'zapasnye-chasti-tolon',
        'code' => 'pneumatic_butterfly_valve_th0110040307',
        'name' => 'Pneumatic Butterfly Valve / Пневматический дисковый затвор для TOLON TWE110',
        'brand' => 'tolon',
        'props' => [
            'PROIZVODITEL' => 'TOLON',
            'MODELI_OBORUDOVANIYA' => 'TOLON TWE110',
            'ORIGINAL_ZAPCHASTI' => 'Pneumatic Butterfly Valve',
            'ARTIKUL_ZAPCHASTI' => 'TH0110040307',
            'TIP_ZAPCHASTI' => 'Пневматический дисковый затвор',
        ],
        'preview' => ul([
            'Производитель' => 'TOLON',
            'Производитель запчастей' => 'TOLON',
            'Модели оборудования' => 'TOLON TWE110',
            'Оригинальное наименование запчасти' => 'Pneumatic Butterfly Valve',
            'Артикул запчасти' => 'TH0110040307',
            'Тип запчасти' => 'Пневматический дисковый затвор',
            'Тип привода' => 'Пневматический',
            'Назначение' => 'Управление потоком рабочей среды',
        ]),
        'detail' => [
            'Пневматический дисковый затвор предназначен для управления потоком рабочей среды в промышленной стиральной машине TOLON TWE110. Он обеспечивает автоматическое открытие и закрытие трубопровода по команде системы управления, поддерживая стабильную работу оборудования и корректное выполнение технологических процессов.',
            'Оригинальный пневматический дисковый затвор разработан специально для оборудования TOLON и полностью соответствует требованиям производителя. Конструкция сочетает дисковый запорный механизм и пневматический привод, обеспечивающий быстрое, точное и надежное срабатывание даже при интенсивной эксплуатации в условиях профессиональной прачечной.',
            'Компания ПРОФЭКВИП поможет подобрать оригинальные запчасти TOLON для оборудования прачечной, определить совместимость комплектующих с конкретной моделью оборудования и обеспечить поставку необходимых деталей для надежной и бесперебойной эксплуатации профессионального прачечного оборудования.',
        ],
        'seo_title' => 'Пневматический дисковый затвор для TOLON TWE110 (TH0110040307) купить в ПРОФЭКВИП',
        'seo_description' => 'Оригинальный пневматический дисковый затвор Pneumatic Butterfly Valve, арт. TH0110040307, для стиральной машины TOLON TWE110. Подбор и поставка.',
    ],
    [
        'sku' => '304576',
        'photo' => 'УСТРОЙСТВО ДЛЯ УСТАНОВКИ ЗАМКОВ CLIPPER MAQAGR 50.png',
        'section' => 'zapasnye-chasti-dlya-prachechnogo-oborudovaniya',
        'code' => 'clipper_lacer_machine_maqagr_50_304576',
        'name' => 'Clipper Lacer Machine MAQAGR 50 / Устройство для установки замков Clipper MAQAGR 50',
        'brand' => 'valmet',
        'props' => [
            'PROIZVODITEL' => 'Valmet',
            'ORIGINAL_ZAPCHASTI' => 'Clipper Lacer Machine MAQAGR 50',
            'ARTIKUL_ZAPCHASTI' => '304576',
            'TIP_ZAPCHASTI' => 'Монтажный инструмент',
        ],
        'preview' => ul([
            'Производитель' => 'Valmet',
            'Оригинальное наименование' => 'Clipper Lacer Machine MAQAGR 50',
            'Артикул' => '304576',
            'Тип изделия' => 'Монтажный инструмент',
            'Тип привода' => 'Ручной',
            'Назначение' => 'Установка замков Clipper на конвейерные ленты',
            'Ширина ленты' => 'до 50,8 мм (2")',
            'Совместимые замки' => 'Clipper №25',
            'Шаг замков' => '1,7 мм',
        ]),
        'detail' => [
            'Ручное устройство Clipper Lacer Machine MAQAGR 50 предназначено для установки замков Clipper на конвейерные ленты шириной до 50,8 мм (2"). Инструмент обеспечивает точное позиционирование замков и их надежную фиксацию, позволяя быстро выполнять монтаж и замену соединений при обслуживании оборудования.',
            'Прочная металлическая конструкция рассчитана на регулярное использование в сервисных мастерских и на промышленных предприятиях. Ручной привод не требует подключения к источникам питания, обеспечивает удобство эксплуатации и высокую точность установки замков.',
            'Компания ПРОФЭКВИП поставляет оригинальные расходные материалы, сервисный инструмент и комплектующие Valmet для обслуживания профессионального прачечного оборудования.',
        ],
        'seo_title' => 'Устройство для установки замков Clipper MAQAGR 50 (304576) купить в ПРОФЭКВИП',
        'seo_description' => 'Ручное устройство Clipper Lacer Machine MAQAGR 50 (Valmet, арт. 304576) для установки замков Clipper №25 на конвейерные ленты шириной до 50,8 мм.',
    ],
    [
        'sku' => 'TH0250150036',
        'photo' => 'ПАНЕЛЬ КНОПКИ АВАРИЙНОЙ ОСТАНОВКИ ДЛЯ TOLON, арт. TH0250150036.png',
        'section' => 'zapasnye-chasti-tolon',
        'code' => 'emergency_stop_panel_th0250150036',
        'old_codes' => ['emergency_stop_indicator_lamp_th0250150036'],
        'name' => 'Emergency Stop Panel / Панель кнопки аварийной остановки для TOLON TWE60, TOLON TWE110, TOLON TFI6026, TOLON TFI6032, TOLON TBW63, TOLON TBW120',
        'brand' => 'tolon',
        'props' => [
            'PROIZVODITEL' => 'TOLON',
            'MODELI_OBORUDOVANIYA' => 'TOLON TWE60, TOLON TWE110, TOLON TFI6026, TOLON TFI6032, TOLON TBW63, TOLON TBW120',
            'ORIGINAL_ZAPCHASTI' => 'Emergency Stop Panel',
            'ARTIKUL_ZAPCHASTI' => 'TH0250150036',
            'TIP_ZAPCHASTI' => 'Панель (табличка) аварийной остановки',
        ],
        'preview' => ul([
            'Производитель' => 'TOLON',
            'Производитель запчастей' => 'TOLON',
            'Модели оборудования' => 'TOLON TWE60, TOLON TWE110, TOLON TFI6026, TOLON TFI6032, TOLON TBW63, TOLON TBW120',
            'Оригинальное наименование запчасти' => 'Emergency Stop Panel',
            'Артикул запчасти' => 'TH0250150036',
            'Тип запчасти' => 'Панель (табличка) аварийной остановки',
            'Назначение' => 'Маркировка и установка кнопки аварийной остановки',
        ]),
        'detail' => [
            'Панель кнопки аварийной остановки предназначена для установки и обозначения аварийной кнопки на промышленном оборудовании TOLON. Она обеспечивает правильное расположение элемента управления, делает кнопку хорошо заметной для оператора и способствует быстрому срабатыванию в экстренной ситуации.',
            'Оригинальная панель разработана специально для оборудования TOLON и полностью соответствует требованиям производителя. Деталь отличается высокой износостойкостью, устойчивостью к механическим воздействиям и сохраняет четкость маркировки при длительной эксплуатации в условиях профессиональной прачечной.',
            'Компания ПРОФЭКВИП поможет подобрать оригинальные запчасти и комплектующие TOLON, определить их совместимость с конкретной моделью оборудования и обеспечить поставку необходимых деталей для надежной эксплуатации профессионального прачечного оборудования.',
        ],
        'seo_title' => 'Панель кнопки аварийной остановки TOLON (TH0250150036) купить в ПРОФЭКВИП',
        'seo_description' => 'Оригинальная панель кнопки аварийной остановки Emergency Stop Panel, арт. TH0250150036, для TOLON TWE60, TWE110, TFI6026, TFI6032, TBW63, TBW120.',
    ],
];

$props = [];
$rs = \CIBlockProperty::GetList([], ['IBLOCK_ID' => IBLOCK_ID]);
while ($p = $rs->Fetch()) { $props[$p['CODE']] = (int) $p['ID']; }
foreach (['BRAND', 'PROIZVODITEL', 'MODELI_OBORUDOVANIYA', 'ORIGINAL_ZAPCHASTI', 'ARTIKUL_ZAPCHASTI', 'TIP_ZAPCHASTI'] as $code) {
    if (empty($props[$code])) { throw new RuntimeException("Нет свойства $code."); }
}

function slug(string $text): string
{
    return trim(mb_substr(\CUtil::translit($text, 'ru', ['replace_space' => '-', 'replace_other' => '-', 'change_case' => 'L', 'max_len' => 50]), 0, 50), '-');
}

// Значение списка по тексту; новое создаётся с человекочитаемым XML_ID (он идёт в ЧПУ умного фильтра)
function enumId(int $propId, string $value, bool $dry): int
{
    $e = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propId, 'VALUE' => $value])->Fetch();
    if ($e) { return (int) $e['ID']; }
    if ($dry) { echo "  [dry] новое значение «{$value}»\n"; return 0; }
    $xml = slug($value) ?: md5($value);
    for ($i = 2; \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propId, 'XML_ID' => $xml])->Fetch(); $i++) {
        $xml = slug($value) . "-$i";
    }
    $id = (new \CIBlockPropertyEnum())->Add(['PROPERTY_ID' => $propId, 'VALUE' => $value, 'XML_ID' => $xml, 'DEF' => 'N']);
    if (!$id) { throw new RuntimeException("Не создано значение «$value»"); }
    echo "  новое значение «{$value}» (XML_ID=$xml)\n";
    return (int) $id;
}

function saveImage(string $path, string $name, string $alt, string $subdir): int
{
    $arr = \CFile::MakeFileArray($path);
    $arr['name'] = $name;
    $arr['description'] = $alt;
    $arr['MODULE_ID'] = 'iblock';
    $id = \CFile::SaveFile($arr, $subdir);
    if (!$id) { throw new RuntimeException("Файл $path не сохранён"); }
    return (int) $id;
}

// --- бренд Valmet ----------------------------------------------------------------
$brandIds = [];
foreach (['tolon' => 'Tolon', 'valmet' => 'Valmet'] as $code => $name) {
    $b = \CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK_ID, 'CODE' => $code], false, false, ['ID'])->Fetch();
    if (!$b && $code === 'valmet') {
        if ($dry) { echo "[dry] будет создан бренд Valmet\n"; $brandIds[$code] = 0; continue; }
        $bid = (new \CIBlockElement())->Add(['IBLOCK_ID' => BRANDS_IBLOCK_ID, 'NAME' => $name, 'CODE' => $code, 'ACTIVE' => 'Y', 'SORT' => 500]);
        if (!$bid) { throw new RuntimeException('Бренд Valmet не создан'); }
        echo "Создан бренд Valmet (ID=$bid)\n";
        $b = ['ID' => $bid];
    }
    if (!$b) { throw new RuntimeException("Нет бренда $name"); }
    $brandIds[$code] = (int) $b['ID'];
}

// --- товары --------------------------------------------------------------------------
foreach ($products as $p) {
    $photo = "$photosDir/{$p['photo']}";
    if (!is_file($photo)) { throw new RuntimeException("{$p['sku']}: нет фото $photo"); }
    if (mb_strlen($p['seo_description']) > 160) { throw new RuntimeException("{$p['sku']}: description > 160"); }

    $section = \CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $p['section']], false, ['ID'])->Fetch();
    if (!$section) { throw new RuntimeException("Нет раздела {$p['section']}"); }

    $existing = null;
    foreach (array_merge([$p['code']], $p['old_codes'] ?? []) as $c) {
        $existing = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $c], false, false, ['ID', 'CODE', 'DETAIL_PICTURE'])->Fetch();
        if ($existing) { break; }
    }
    if ($dry) {
        echo "[dry] {$p['sku']} → " . ($existing ? "update ID={$existing['ID']} ({$existing['CODE']})" : 'create') . " {$p['code']} в {$p['section']}\n";
        foreach ($p['props'] as $code => $v) { enumId($props[$code], $v, true); }
        continue;
    }

    $fields = [
        'IBLOCK_ID' => IBLOCK_ID,
        'IBLOCK_SECTION_ID' => (int) $section['ID'],
        'NAME' => $p['name'],
        'CODE' => $p['code'],
        'ACTIVE' => 'Y',
        'SORT' => 500,
        'PREVIEW_TEXT' => $p['preview'],
        'PREVIEW_TEXT_TYPE' => 'html',
        'DETAIL_TEXT' => implode("<br><br>\n", $p['detail']),
        'DETAIL_TEXT_TYPE' => 'html',
    ];
    $el = new \CIBlockElement();
    if ($existing) {
        $id = (int) $existing['ID'];
        if (!$el->Update($id, $fields)) { throw new RuntimeException("{$p['sku']}: update: " . $el->LAST_ERROR); }
        $status = 'updated';
    } else {
        $fields['PROPERTY_VALUES'] = [];
        $id = (int) $el->Add($fields);
        if (!$id) { throw new RuntimeException("{$p['sku']}: add: " . $el->LAST_ERROR); }
        $status = 'created';
    }

    // товар каталога, цена 0 RUB — как у остальных запчастей
    if (!\CCatalogProduct::GetByID($id)) {
        \CCatalogProduct::Add(['ID' => $id, 'QUANTITY' => 0, 'MEASURE' => 796]);
    }
    $priceFields = ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => 1, 'PRICE' => 0, 'CURRENCY' => 'RUB'];
    $pr = \CPrice::GetList([], ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => 1])->Fetch();
    if ($pr) { \CPrice::Update((int) $pr['ID'], $priceFields); } else { \CPrice::Add($priceFields); }

    // свойства: перечисленные — значения, остальные «запчастные» — очистить (на случай перезаписи)
    $values = ['BRAND' => [$brandIds[$p['brand']]]];
    foreach (['PROIZVODITEL', 'MODELI_OBORUDOVANIYA', 'ORIGINAL_ZAPCHASTI', 'ARTIKUL_ZAPCHASTI', 'TIP_ZAPCHASTI'] as $code) {
        $values[$code] = isset($p['props'][$code]) ? enumId($props[$code], $p['props'][$code], false) : false;
    }
    \CIBlockElement::SetPropertyValuesEx($id, IBLOCK_ID, $values);

    // фото — только если ещё нет (повторный запуск не плодит файлы); ID ставится SQL-ом (см. CLAUDE.md)
    if (!$existing || !$existing['DETAIL_PICTURE']) {
        $fid = saveImage($photo, $p['code'] . '.png', $p['name'], $uploadSubdir);
        $DB->Query("UPDATE b_iblock_element SET PREVIEW_PICTURE=$fid, DETAIL_PICTURE=$fid, TIMESTAMP_X=NOW() WHERE ID=$id");
    }

    $tpl = new \Bitrix\Iblock\InheritedProperty\ElementTemplates(IBLOCK_ID, $id);
    $tpl->set([
        'ELEMENT_META_TITLE' => $p['seo_title'],
        'ELEMENT_META_DESCRIPTION' => $p['seo_description'],
        'ELEMENT_PREVIEW_PICTURE_FILE_ALT' => $p['name'],
        'ELEMENT_PREVIEW_PICTURE_FILE_TITLE' => $p['name'],
        'ELEMENT_DETAIL_PICTURE_FILE_ALT' => $p['name'],
        'ELEMENT_DETAIL_PICTURE_FILE_TITLE' => $p['name'],
    ]);
    (new \Bitrix\Iblock\InheritedProperty\ElementValues(IBLOCK_ID, $id))->clearValues();

    echo "$status {$p['sku']} → ID=$id /product/{$p['code']}/\n";
}

if (!$dry) {
    foreach (['cache', 'managed_cache', 'stack_cache'] as $d) {
        foreach (glob($_SERVER['DOCUMENT_ROOT'] . "/bitrix/$d/*") ?: [] as $f) { \Bitrix\Main\IO\Directory::deleteDirectory($f); }
    }
    echo "Кэш очищен\n";
}
