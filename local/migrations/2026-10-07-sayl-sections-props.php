<?php
/**
 * SAYL: подразделы витрин/буфетных станций внутри «Линии раздачи» и свойство-список
 * «Линейка» (LINEYKA) для умного фильтра.
 *
 * Старые карточки-«линии» SAYL (4 шт.) заменяются отдельными моделями (29 шт.); подразделы
 * служат целями 301-редиректов со старых URL. Товар может лежать в нескольких подразделах
 * (MAXISELF — холодильные и тепловые и т.п.), распределение — в local/deploy/sayl/build_data.py.
 *
 * Бренд SAYL (инфоблок брендов) и значение SAYL у PROIZVODITEL уже есть (2026-09-30).
 * Данные (товары, фото, SEO, редиректы) — local/deploy/sayl/import.php.
 * Идемпотентна: разделы ищутся по CODE, свойство — по CODE, значения — по XML_ID.
 */

// Тело обёрнуто в call_user_func: локальная переменная $name иначе затирает
// переменную $name раннера run.php (см. local/migrations/README.md).
call_user_func(static function () {

CModule::IncludeModule('iblock');

global $DB;

$iblockId = (int) GetIBlockIDByCode('catalog');
if (!$iblockId) {
    throw new \RuntimeException('Инфоблок "catalog" не найден.');
}

// --- 1. Подразделы «Линий раздачи» ---------------------------------------------

$parent = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'linii-razdachi'], false, ['ID'])->Fetch();
if (!$parent) {
    throw new \RuntimeException('Раздел linii-razdachi не найден.');
}

$sections = [
    'bufetnye-stantsii' => 'Буфетные станции',
    'holodilnye-vitriny' => 'Холодильные витрины',
    'teplovye-vitriny' => 'Тепловые витрины',
    'neytralnye-vitriny' => 'Нейтральные витрины',
    'nastolnye-vitriny' => 'Настольные витрины',
    'vitriny-dlya-sushi' => 'Витрины для суши',
];
$sort = 500;
foreach ($sections as $code => $name) {
    $sort += 10;
    $section = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code], false, ['ID'])->Fetch();
    if ($section) {
        echo "Раздел $code уже существует (ID={$section['ID']}), пропускаю.\n";
        continue;
    }
    $obj = new \CIBlockSection();
    $sectionId = $obj->Add([
        'IBLOCK_ID' => $iblockId,
        'IBLOCK_SECTION_ID' => (int) $parent['ID'],
        'NAME' => $name,
        'CODE' => $code,
        'ACTIVE' => 'Y',
        'SORT' => $sort,
    ]);
    if (!$sectionId) {
        throw new \RuntimeException("Раздел $code не создан: " . $obj->LAST_ERROR);
    }
    $check = $DB->Query('SELECT IBLOCK_SECTION_ID FROM b_iblock_section WHERE ID = ' . (int) $sectionId)->Fetch();
    if (!$check || (int) $check['IBLOCK_SECTION_ID'] !== (int) $parent['ID']) {
        throw new \RuntimeException("Раздел $code не найден под linii-razdachi после Add().");
    }
    echo "Создан раздел «{$name}» (ID=$sectionId)\n";
}

// --- 2. Свойство «Линейка» (список, умный фильтр) -----------------------------------

// XML_ID — ЧПУ значения в умном фильтре; порядок = порядок в фильтре
$values = [
    'buffet-line' => 'Buffet Line',
    'gn1-1-line' => 'GN1-1 Line',
    'integra-line' => 'Integra Line',
    'neutra-line' => 'Neutra Line',
    'pak-line' => 'PAK Line',
    'sobremostrador-line' => 'Sobremostrador Line',
    'sushi-line' => 'Sushi Line',
];

$existing = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'LINEYKA'])->Fetch();
if ($existing) {
    $id = (int) $existing['ID'];
    echo "Свойство LINEYKA уже существует (ID=$id), пропускаю.\n";
} else {
    $prop = new \CIBlockProperty();   // PHP 8.4: только через new
    $id = (int) $prop->Add([
        'IBLOCK_ID' => $iblockId,
        'CODE' => 'LINEYKA',
        'NAME' => 'Линейка',
        'PROPERTY_TYPE' => 'L',
        'LIST_TYPE' => 'C',
        'MULTIPLE' => 'N',
        'SORT' => 520,
        'ACTIVE' => 'Y',
        'FILTRABLE' => 'Y',
    ]);
    if (!$id) {
        throw new \RuntimeException('Не создано свойство LINEYKA: ' . $prop->LAST_ERROR);
    }
    (new \CIBlockProperty())->Update($id, ['IBLOCK_ID' => $iblockId, 'SMART_FILTER' => 'Y', 'DISPLAY_EXPANDED' => 'Y']);
    $sf = $DB->Query('SELECT 1 FROM b_iblock_section_property WHERE PROPERTY_ID = ' . $id . ' AND SMART_FILTER = "Y"')->Fetch();
    if (!$sf) {
        throw new \RuntimeException('Свойство LINEYKA: не включён умный фильтр.');
    }
    echo "Создано свойство LINEYKA «Линейка» (ID=$id), умный фильтр\n";
}

$valueSort = 0;
foreach ($values as $xmlId => $value) {
    $valueSort += 10;
    if (\CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $id, 'XML_ID' => $xmlId])->Fetch()) {
        continue;
    }
    $eid = (new \CIBlockPropertyEnum())->Add(['PROPERTY_ID' => $id, 'VALUE' => $value, 'XML_ID' => $xmlId, 'SORT' => $valueSort, 'DEF' => 'N']);
    if (!$eid) {
        throw new \RuntimeException("Не создано значение $value у LINEYKA.");
    }
    echo "  значение «{$value}» (ID=$eid)\n";
}

});
return true;
