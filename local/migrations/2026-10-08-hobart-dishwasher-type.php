<?php
/**
 * Посудомоечное оборудование (Hobart, 9 моделей): фасет «Тип» для умного фильтра
 * (Фронтальная / Стаканомоечная / Купольная) и значение «Hobart» у списка PROIZVODITEL.
 *
 * Привязка к умному фильтру — на весь инфоблок (SECTION_ID=0), как у остальных свойств;
 * фильтр раздела показывает только свойства, у которых есть значения в этом разделе.
 * XML_ID человекочитаемые — ЧПУ фильтра вида
 * /product-category/posudomoechnoe-oborudovanie/f/tip_posudomoechnoj_mashiny-is-kupolnaya/.
 * Значения товарам ставит local/deploy/hobart_dishwashers/import.php.
 * Идемпотентна: свойство ищется по CODE, значения — по XML_ID / VALUE.
 */

call_user_func(static function () {

CModule::IncludeModule('iblock');

global $DB;

$iblockId = (int) GetIBlockIDByCode('catalog');
if (!$iblockId) {
    throw new \RuntimeException('Инфоблок "catalog" не найден.');
}

$code = 'TIP_POSUDOMOECHNOJ_MASHINY';
$existing = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code])->Fetch();
if ($existing) {
    $id = (int) $existing['ID'];
    echo "Свойство $code уже существует (ID=$id)\n";
} else {
    $prop = new \CIBlockProperty();   // PHP 8.4: только через new
    $id = (int) $prop->Add([
        'IBLOCK_ID' => $iblockId,
        'CODE' => $code,
        'NAME' => 'Тип',
        'PROPERTY_TYPE' => 'L',
        'LIST_TYPE' => 'C',
        'MULTIPLE' => 'N',
        'SORT' => 505,
        'ACTIVE' => 'Y',
    ]);
    if (!$id) {
        throw new \RuntimeException("Не создано свойство $code: " . $prop->LAST_ERROR);
    }
    // IBLOCK_ID в массиве обязателен, иначе привязка к умному фильтру тихо не создаётся
    (new \CIBlockProperty())->Update($id, ['IBLOCK_ID' => $iblockId, 'SMART_FILTER' => 'Y', 'DISPLAY_EXPANDED' => 'Y']);
    $sf = $DB->Query('SELECT 1 FROM b_iblock_section_property WHERE PROPERTY_ID = ' . $id . ' AND SMART_FILTER = "Y"')->Fetch();
    if (!$sf) {
        throw new \RuntimeException("Свойство $code: не включён умный фильтр.");
    }
    echo "Создано свойство $code «Тип» (ID=$id), умный фильтр\n";
}

$valueSort = 0;
foreach (['frontalnaya' => 'Фронтальная', 'stakanomoechnaya' => 'Стаканомоечная', 'kupolnaya' => 'Купольная'] as $xmlId => $value) {
    $valueSort += 10;
    if (\CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $id, 'XML_ID' => $xmlId])->Fetch()) {
        continue;
    }
    $eid = (new \CIBlockPropertyEnum())->Add(['PROPERTY_ID' => $id, 'VALUE' => $value, 'XML_ID' => $xmlId, 'SORT' => $valueSort, 'DEF' => 'N']);
    if (!$eid) {
        throw new \RuntimeException("$code: не создано значение «{$value}»");
    }
    echo "  $code: значение «{$value}» ($xmlId)\n";
}

// «Производитель» (список) — значение Hobart; ищем по тексту, т.к. у старых брендов XML_ID — хэши
$manufacturer = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'PROIZVODITEL'])->Fetch();
if (!$manufacturer) {
    throw new \RuntimeException('Нет свойства PROIZVODITEL.');
}
if (\CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $manufacturer['ID'], 'VALUE' => 'Hobart'])->Fetch()) {
    echo "PROIZVODITEL: значение «Hobart» уже есть\n";
} else {
    $eid = (new \CIBlockPropertyEnum())->Add(['PROPERTY_ID' => $manufacturer['ID'], 'VALUE' => 'Hobart', 'XML_ID' => 'hobart', 'SORT' => 500, 'DEF' => 'N']);
    if (!$eid) {
        throw new \RuntimeException('PROIZVODITEL: не создано значение «Hobart»');
    }
    echo "PROIZVODITEL: значение «Hobart» (hobart)\n";
}

});
return true;
