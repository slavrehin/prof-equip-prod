<?php
/**
 * Стандарт характеристик, шаг 6: признаки, нужные посадочным подборкам, которых не было в данных.
 *  - «Назначение» = «МОПы» стиральным машинам «для стирки МОПов» (по названию) — подборка
 *    /product-category/vysokoskorostnye-stiralnye-mashiny/dlya-stirki-mopov/ (запрос «машина для стирки мопов»).
 * Идемпотентна.
 */

call_user_func(static function () {

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/deploy/catalog_restructure/lib.php';
\CModule::IncludeModule('iblock');

$iblockId = CatalogMigrate::iblockId();
$prop = CatalogMigrate::property('NAZNACHENIE');
$mopId = CatalogMigrate::enumIdFor($prop, 'МОПы', 50);
$current = CatalogMigrate::values('NAZNACHENIE');

$n = 0;
$rs = \CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, '%NAME' => 'МОП'], false, false, ['ID', 'NAME']);
while ($e = $rs->Fetch()) {
    if (!preg_match('/\bМОП/u', $e['NAME'])) {
        continue;
    }
    $values = $current[(int)$e['ID']] ?? [];
    if (in_array('МОПы', $values, true)) {
        continue;
    }
    $ids = array_map(static fn($v) => CatalogMigrate::enumIdFor($prop, $v), $values);
    $ids[] = $mopId;
    \CIBlockElement::SetPropertyValuesEx((int)$e['ID'], $iblockId, ['NAZNACHENIE' => $ids]);
    $n++;
}
echo "«Назначение: МОПы» проставлено товарам: $n\n";
CatalogMigrate::clearCache();

});
return true;
