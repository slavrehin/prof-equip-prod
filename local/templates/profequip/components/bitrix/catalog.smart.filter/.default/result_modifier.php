<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */

// Свойства, которые не показываем в этом фильтре (по CODE). По умолчанию —
// сквозной «Тип» товара (TIP_TOVARA): он нужен только на страницах брендов
// (local/components/custom/news.with.filter/templates/brands/), где фильтр
// передаёт свой список HIDE_PROPERTY_CODES, а в разделах каталога дублировал бы
// сам раздел. См. local/php_interface/include/product_type.php.
$hiddenCodes = $arParams['HIDE_PROPERTY_CODES'] ?? ['TIP_TOVARA'];
if (is_array($hiddenCodes) && $hiddenCodes) {
    foreach ($arResult['ITEMS'] as $key => $arItem) {
        if (isset($arItem['CODE']) && in_array($arItem['CODE'], $hiddenCodes, true)) {
            unset($arResult['ITEMS'][$key]);
        }
    }
}

// Переименование свойств только в этом фильтре: CODE => подпись. На страницах
// брендов так отличаем узкие «Тип» разделов от сквозного «Тип» товара.
$renamedCodes = $arParams['RENAME_PROPERTY_CODES'] ?? [];
if (is_array($renamedCodes) && $renamedCodes) {
    foreach ($arResult['ITEMS'] as $key => $arItem) {
        if (isset($arItem['CODE'], $renamedCodes[$arItem['CODE']])) {
            $arResult['ITEMS'][$key]['NAME'] = $renamedCodes[$arItem['CODE']];
        }
    }
}
