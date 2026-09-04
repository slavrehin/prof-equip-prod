<?php
/**
 * Включает показ свойства "Тип" (CODE=TIP_ZAPCHASTI, инфоблок "Каталог") в умном
 * фильтре. На test3 это свойство уже участвует в умном фильтре (использовано при
 * заполнении товаров-крепежа/кабеля 2026-09-04); на проде то же свойство
 * существует (см. CODE — ID на проде и test3 разный, поэтому матчим по CODE), но
 * без записи в умном фильтре. Идемпотентно: проверяет b_iblock_section_property
 * перед созданием.
 */

global $DB;

CModule::IncludeModule('iblock');

$iblockId = (int)\CIBlock::GetList([], ['CODE' => 'catalog'])->Fetch()['ID'];
if (!$iblockId) {
    throw new \RuntimeException('Инфоблок catalog не найден');
}

$prop = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'TIP_ZAPCHASTI'])->Fetch();
if (!$prop) {
    throw new \RuntimeException('Свойство TIP_ZAPCHASTI не найдено в инфоблоке ' . $iblockId);
}
$propertyId = (int)$prop['ID'];

$exists = $DB->Query(
    "SELECT PROPERTY_ID FROM b_iblock_section_property WHERE IBLOCK_ID = " . $iblockId
    . " AND SECTION_ID = 0 AND PROPERTY_ID = " . $propertyId
)->Fetch();

if ($exists) {
    echo "Свойство TIP_ZAPCHASTI (ID=$propertyId) уже в умном фильтре, пропускаю.\n";
} else {
    $property = new \CIBlockProperty();
    $result = $property->Update($propertyId, [
        'IBLOCK_ID' => $iblockId, // обязателен, иначе привязка к умному фильтру тихо не создаётся
        'SMART_FILTER' => 'Y',
    ]);
    if (!$result) {
        throw new \RuntimeException('Не удалось включить умный фильтр для TIP_ZAPCHASTI: ' . $property->LAST_ERROR);
    }

    $check = $DB->Query(
        "SELECT PROPERTY_ID FROM b_iblock_section_property WHERE IBLOCK_ID = " . $iblockId
        . " AND SECTION_ID = 0 AND PROPERTY_ID = " . $propertyId
    )->Fetch();
    if (!$check) {
        throw new \RuntimeException('Запись в b_iblock_section_property не найдена после Update() (PROPERTY_ID=' . $propertyId . ')');
    }

    echo "Включён умный фильтр для TIP_ZAPCHASTI (ID=$propertyId, IBLOCK_ID=$iblockId).\n";
}
