<?php
/**
 * Сквозной фасет «Тип» (TIP_TOVARA) для умного фильтра страниц брендов /brends/<код>/:
 * Пароконвектоматы, Сковороды, Решетки, Рамки и т.д. — тип товара, выведенный из его
 * разделов (см. local/php_interface/include/product_type.php).
 *
 * Привязка к умному фильтру — на весь инфоблок (SECTION_ID=0); в фильтрах разделов
 * каталога свойство скрыто шаблоном фильтра (HIDE_PROPERTY_CODES по умолчанию),
 * показывается только на страницах брендов. SORT=1 — первым в фильтре.
 * Значения товарам ставит local/deploy/backfill_tip_tovara.php (варианты списка
 * создаются там же по мере надобности, XML_ID — транслит, ЧПУ /f/tip_tovara-is-<slug>/).
 * Идемпотентна: свойство ищется по CODE.
 */

call_user_func(static function () {

CModule::IncludeModule('iblock');

global $DB;

$iblockId = (int) GetIBlockIDByCode('catalog');
if (!$iblockId) {
    throw new \RuntimeException('Инфоблок "catalog" не найден.');
}

$code = 'TIP_TOVARA';
$existing = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code])->Fetch();
if ($existing) {
    echo "Свойство $code уже существует (ID={$existing['ID']})\n";
    return;
}

$prop = new \CIBlockProperty();   // PHP 8.4: только через new
$id = (int) $prop->Add([
    'IBLOCK_ID' => $iblockId,
    'CODE' => $code,
    'NAME' => 'Тип',
    'PROPERTY_TYPE' => 'L',
    'LIST_TYPE' => 'C',
    'MULTIPLE' => 'N',
    'SORT' => 1,
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

});
return true;
