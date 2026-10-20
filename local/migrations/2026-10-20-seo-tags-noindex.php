<?php
/**
 * Свойство NOINDEX у SEO-тегов: страница тега остаётся для пользователей (фильтр ведёт на неё),
 * но отдаёт noindex, не попадает в sitemap и не выводится ссылкой <a href> в фильтре.
 * Какие теги закрыты — local/deploy/catalog_restructure/seed_tags.php (NOINDEX_TAGS). Идемпотентна.
 */

call_user_func(static function () {

\CModule::IncludeModule('iblock');

$iblockId = (int)GetIBlockIDByCode('seotags');
if (!$iblockId) {
    throw new \RuntimeException('seotags: нет инфоблока — сначала 2026-10-13-seo-tags-iblock.php');
}

if (\CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'NOINDEX'])->Fetch()) {
    echo "seotags: свойство NOINDEX уже есть\n";
    return;
}

$id = (int)(new \CIBlockProperty())->Add([
    'IBLOCK_ID' => $iblockId,
    'CODE' => 'NOINDEX',
    'NAME' => 'Закрыть от индексации (noindex, нет в sitemap и в ссылках фильтра)',
    'PROPERTY_TYPE' => 'L',
    'LIST_TYPE' => 'C',
    'MULTIPLE' => 'N',
    'ACTIVE' => 'Y',
    'SORT' => 95,
    'VALUES' => ['n0' => ['VALUE' => 'Y', 'XML_ID' => 'Y', 'SORT' => 10]],
]);
if (!$id || !\CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $id, 'XML_ID' => 'Y'])->Fetch()) {
    throw new \RuntimeException('seotags: не создано свойство NOINDEX');
}
echo "seotags: свойство NOINDEX (ID=$id)\n";

});
return true;
