<?php
/**
 * Шильдик "В наличии" на карточке товара (см. каталог, блок как на лендинге
 * "Оборудование в наличии") — управляется свойством элемента, а не жёстко
 * зашитым списком ID, чтобы редактор мог включать/выключать шильдик для
 * любого товара из админки (стандартная форма редактирования элемента).
 *
 * Свойство STOCK_BADGE: тип "Список" с отображением "Флажки" и одним
 * значением — стандартный приём Bitrix для булева флага на элементе:
 * в форме редактирования это один чекбокс, а не текстовое поле.
 *
 * Товарам раздела prachechnoe-oborudovanie-v-nalichii (см. также
 * 2026-09-03-prachechnoe-v-nalichii-sort.php — тот же раздел, тот же способ
 * поиска по CODE, а не по ID) флаг проставляется сразу, чтобы шильдик был
 * виден без ручной работы в админке на каждый из них.
 */

CModule::IncludeModule('iblock');

global $DB;

$iblockId = (int) GetIBlockIDByCode('catalog');
if (!$iblockId) {
    throw new \RuntimeException('Инфоблок "catalog" не найден.');
}

// --- 1. Свойство STOCK_BADGE ---------------------------------------------

$propertyId = (int) (\CIBlockProperty::GetList([], [
    'IBLOCK_ID' => $iblockId,
    'CODE' => 'STOCK_BADGE',
])->Fetch()['ID'] ?? 0);

if (!$propertyId) {
    $property = new \CIBlockProperty();
    $propertyId = $property->Add([
        'IBLOCK_ID' => $iblockId,
        'CODE' => 'STOCK_BADGE',
        'NAME' => 'Шильдик «В наличии»',
        'PROPERTY_TYPE' => 'L',
        'LIST_TYPE' => 'C',
        'MULTIPLE' => 'N',
        'IS_REQUIRED' => 'N',
        'SORT' => 50,
        'ACTIVE' => 'Y',
    ]);
    if (!$propertyId) {
        throw new \RuntimeException('Не создано свойство STOCK_BADGE: ' . $property->LAST_ERROR);
    }

    $check = $DB->Query('SELECT ID FROM b_iblock_property WHERE ID = ' . (int) $propertyId)->Fetch();
    if (!$check) {
        throw new \RuntimeException('Свойство STOCK_BADGE не найдено в базе после Add().');
    }
    echo "Создано свойство STOCK_BADGE (ID=$propertyId)\n";
} else {
    echo "Свойство STOCK_BADGE уже существует (ID=$propertyId), пропускаю создание.\n";
}

// --- 2. Значение списка (единственный чекбокс) ---------------------------

$enumId = (int) (\CIBlockPropertyEnum::GetList([], [
    'PROPERTY_ID' => $propertyId,
    'VALUE' => 'Показывать',
])->Fetch()['ID'] ?? 0);

if (!$enumId) {
    $enumId = \CIBlockPropertyEnum::Add([
        'PROPERTY_ID' => $propertyId,
        'VALUE' => 'Показывать',
        'DEF' => 'N',
        'SORT' => 100,
    ]);
    if (!$enumId) {
        throw new \RuntimeException('Не создано значение "Показывать" для STOCK_BADGE.');
    }

    $check = $DB->Query('SELECT ID FROM b_iblock_property_enum WHERE ID = ' . (int) $enumId)->Fetch();
    if (!$check) {
        throw new \RuntimeException('Значение "Показывать" не найдено в базе после Add().');
    }
    echo "Создано значение \"Показывать\" для STOCK_BADGE (ID=$enumId)\n";
} else {
    echo "Значение \"Показывать\" для STOCK_BADGE уже существует (ID=$enumId), пропускаю создание.\n";
}

// --- 3. Включаем флаг товарам раздела prachechnoe-oborudovanie-v-nalichii -

$section = \CIBlockSection::GetList([], [
    'IBLOCK_ID' => $iblockId,
    'CODE' => 'prachechnoe-oborudovanie-v-nalichii',
], false, ['ID', 'NAME'])->Fetch();

if (!$section) {
    throw new \RuntimeException('Раздел prachechnoe-oborudovanie-v-nalichii не найден.');
}

$sectionId = (int) $section['ID'];

$rsElements = \CIBlockElement::GetList(
    [],
    [
        'IBLOCK_ID' => $iblockId,
        'SECTION_ID' => $sectionId,
        'INCLUDE_SUBSECTIONS' => 'N',
    ],
    false,
    false,
    ['ID', 'NAME']
);

$updated = 0;
$skipped = 0;
while ($el = $rsElements->Fetch()) {
    $elementId = (int) $el['ID'];

    // CIBlockElement::GetProperty() не возвращает ID значения списка
    // отдельным полем (VALUE_ENUM в его выборке — это текст, не ID) — раз
    // значение у STOCK_BADGE ровно одно ("Показывать"), непустого VALUE
    // достаточно, чтобы считать шильдик уже включённым.
    $current = \CIBlockElement::GetProperty(
        $iblockId,
        $elementId,
        [],
        ['CODE' => 'STOCK_BADGE']
    )->Fetch();

    if ($current && !empty($current['VALUE'])) {
        echo "Товар {$elementId} ({$el['NAME']}) уже со шильдиком, пропускаю.\n";
        $skipped++;
        continue;
    }

    \CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, ['STOCK_BADGE' => $enumId]);

    $check = $DB->Query('
        SELECT VALUE_ENUM FROM b_iblock_element_property
        WHERE IBLOCK_ELEMENT_ID = ' . $elementId . '
          AND IBLOCK_PROPERTY_ID = ' . $propertyId . '
    ')->Fetch();
    if (!$check || (int) $check['VALUE_ENUM'] !== $enumId) {
        throw new \RuntimeException("Шильдик STOCK_BADGE товара {$elementId} не подтверждён запросом к базе после SetPropertyValuesEx().");
    }

    echo "Товар {$elementId} ({$el['NAME']}): шильдик «В наличии» включён.\n";
    $updated++;
}

echo "Готово: {$updated} товаров обновлено, {$skipped} уже были со шильдиком.\n";
