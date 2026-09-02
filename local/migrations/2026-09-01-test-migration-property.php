<?php
/**
 * Тестовая миграция для проверки цикла: dev (test3) -> push -> merge -> deploy.sh -> прод.
 * Создаёт заведомо безобидное строковое свойство инфоблока каталога (IBLOCK_ID=11).
 * Безопасно накатывать и откатывать, влияния на витрину не оказывает.
 */

$iblockId = 11;
$propertyCode = 'TEST_MIGRATION_PROPERTY';

$exists = \CIBlockProperty::GetList([], [
    'IBLOCK_ID' => $iblockId,
    'CODE' => $propertyCode,
])->Fetch();

if ($exists) {
    echo "Свойство $propertyCode уже существует (ID={$exists['ID']}), пропускаю.\n";
} else {
    $prop = new \CIBlockProperty();
    $id = $prop->Add([
        'IBLOCK_ID' => $iblockId,
        'NAME' => 'Тестовое свойство (миграции)',
        'CODE' => $propertyCode,
        'PROPERTY_TYPE' => 'S',
        'ROW_COUNT' => 1,
        'COL_COUNT' => 30,
        'MULTIPLE' => 'N',
        'IS_REQUIRED' => 'N',
        'ACTIVE' => 'Y',
        'SORT' => 999,
    ]);

    if (!$id) {
        throw new \RuntimeException('Не создано свойство ' . $propertyCode . ': ' . $prop->LAST_ERROR);
    }

    // Проверяем результат запросом к базе, а не доверяем коду возврата Add().
    global $DB;
    $idEsc = (int)$id;
    $check = $DB->Query("SELECT ID FROM b_iblock_property WHERE ID = $idEsc AND IBLOCK_ID = $iblockId")->Fetch();
    if (!$check) {
        throw new \RuntimeException('Свойство ' . $propertyCode . ' не найдено в базе после Add() (ID=' . $id . ')');
    }

    echo "Создано свойство $propertyCode (ID=$id) в инфоблоке $iblockId.\n";
}
