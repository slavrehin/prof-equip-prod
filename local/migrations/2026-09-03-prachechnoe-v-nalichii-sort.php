<?php
/**
 * Товары раздела "Прачечное оборудование в наличии"
 * (prachechnoe-oborudovanie-v-nalichii) должны показываться первыми на
 * странице родительской категории "Прачечное оборудование"
 * (/product-category/prachechnoe-oborudovanie/) — та страница сортирует
 * товары по стандартному полю SORT ("Сортировка" в админке) по возрастанию.
 * Раздел ищем по CODE, а не по ID — ID раздела на test3 и на проде разные.
 *
 * Каждому товару подраздела присваивается уникальное отрицательное значение
 * SORT (в диапазоне видимо ниже любого реального значения, которое сейчас
 * встречается в инфоблоке каталога, см. проверку ниже) — так они гарантированно
 * оказываются выше всех остальных товаров категории независимо от их SORT.
 * Относительный порядок между собой сохраняется как при текущей сортировке
 * (SORT по возрастанию, при равенстве — ID по убыванию).
 */

CModule::IncludeModule('iblock');

global $DB;

$iblockId = (int) GetIBlockIDByCode('catalog');
if (!$iblockId) {
    throw new \RuntimeException('Инфоблок "catalog" не найден.');
}

$section = \CIBlockSection::GetList([], [
    'IBLOCK_ID' => $iblockId,
    'CODE' => 'prachechnoe-oborudovanie-v-nalichii',
], false, ['ID', 'NAME'])->Fetch();

if (!$section) {
    throw new \RuntimeException('Раздел prachechnoe-oborudovanie-v-nalichii не найден.');
}

$sectionId = (int) $section['ID'];

// Диапазон -1000..-901 гарантированно ниже любого текущего SORT в инфоблоке
// (минимум на момент написания — 0), запас на случай появления новых
// товаров подраздела в будущем.
$baseSort = -1000;
$maxItems = 100;

$rsElements = \CIBlockElement::GetList(
    ['SORT' => 'ASC', 'ID' => 'DESC'],
    [
        'IBLOCK_ID' => $iblockId,
        'SECTION_ID' => $sectionId,
        'INCLUDE_SUBSECTIONS' => 'N',
    ],
    false,
    false,
    ['ID', 'NAME', 'SORT']
);

$elements = [];
while ($el = $rsElements->Fetch()) {
    $elements[] = $el;
}

if (!$elements) {
    throw new \RuntimeException('В разделе prachechnoe-oborudovanie-v-nalichii (ID=' . $sectionId . ') нет товаров.');
}

if (count($elements) > $maxItems) {
    throw new \RuntimeException('В разделе больше ' . $maxItems . ' товаров — увеличьте диапазон baseSort перед запуском.');
}

$index = 0;
foreach ($elements as $el) {
    $elementId = (int) $el['ID'];
    $newSort = $baseSort + $index;

    if ((int) $el['SORT'] === $newSort) {
        echo "Товар {$elementId} ({$el['NAME']}) уже имеет SORT={$newSort}, пропускаю.\n";
        $index++;
        continue;
    }

    $element = new \CIBlockElement();
    $updated = $element->Update($elementId, ['SORT' => $newSort]);

    if (!$updated) {
        throw new \RuntimeException("Не удалось обновить SORT товара {$elementId}: " . $element->LAST_ERROR);
    }

    $check = $DB->Query('SELECT SORT FROM b_iblock_element WHERE ID = ' . $elementId)->Fetch();
    if (!$check || (int) $check['SORT'] !== $newSort) {
        throw new \RuntimeException("SORT товара {$elementId} не подтверждён запросом к базе после Update().");
    }

    echo "Товар {$elementId} ({$el['NAME']}): SORT " . $el['SORT'] . ' -> ' . $newSort . "\n";
    $index++;
}

echo "Готово: {$index} товаров раздела prachechnoe-oborudovanie-v-nalichii выведены в начало родительской категории.\n";
