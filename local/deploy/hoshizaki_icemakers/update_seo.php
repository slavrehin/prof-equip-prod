<?php
/**
 * Принудительно обновляет SEO раздела «Льдогенераторы» (текст и meta — про все бренды, не
 * только Hoshizaki) и задаёт человекочитаемый XML_ID значению «Hoshizaki» свойства
 * PROIZVODITEL — от него зависит ЧПУ фильтра:
 *   /product-category/ldogeneratory/f/proizvoditel-is-hoshizaki/
 * (было .../proizvoditel-is-<md5-хэш>/). Индексируемость этой страницы задаёт правило
 * ldogeneratory__proizvoditel в seofilterrules — см. local/deploy/seed_seo_filter_rules.php.
 * Идемпотентен.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'test3.prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');
global $DB;

$section = \CIBlockSection::GetList([], ['IBLOCK_ID' => 11, 'CODE' => 'ldogeneratory'], false, ['ID'])->Fetch();
if (!$section) { throw new RuntimeException('Нет раздела ldogeneratory'); }
$sid = (int) $section['ID'];

$html = file_get_contents(__DIR__ . '/section_description.html');
$DB->Query("UPDATE b_iblock_section SET DESCRIPTION='" . $DB->ForSql($html) . "', DESCRIPTION_TYPE='html', TIMESTAMP_X=NOW() WHERE ID=$sid");
$tpl = new \Bitrix\Iblock\InheritedProperty\SectionTemplates(11, $sid);
$tpl->set([
    'SECTION_META_TITLE' => 'Льдогенераторы для баров, ресторанов и кафе — купить в ПРОФЭКВИП',
    'SECTION_META_DESCRIPTION' => 'Профессиональные льдогенераторы для баров, ресторанов, кафе и гостиниц: разная производительность и размер кубика, воздушное и водяное охлаждение. Подбор и заказ в ПРОФЭКВИП.',
]);
(new \Bitrix\Iblock\InheritedProperty\SectionValues(11, $sid))->clearValues();
echo "Раздел: описание и meta обновлены\n";

$prop = \CIBlockProperty::GetList([], ['IBLOCK_ID' => 11, 'CODE' => 'PROIZVODITEL'])->Fetch();
$enum = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $prop['ID'], 'VALUE' => 'Hoshizaki'])->Fetch();
if (!$enum) { throw new RuntimeException('Нет значения Hoshizaki у PROIZVODITEL'); }
$busy = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $prop['ID'], 'XML_ID' => 'hoshizaki'])->Fetch();
if ($busy && (int) $busy['ID'] !== (int) $enum['ID']) { throw new RuntimeException('XML_ID hoshizaki занят другим значением'); }
if ($enum['XML_ID'] !== 'hoshizaki') {
    $e = new \CIBlockPropertyEnum();
    $e->Update((int) $enum['ID'], ['XML_ID' => 'hoshizaki']);
    $chk = $DB->Query('SELECT XML_ID FROM b_iblock_property_enum WHERE ID=' . (int) $enum['ID'])->Fetch();
    if ($chk['XML_ID'] !== 'hoshizaki') { throw new RuntimeException('XML_ID не сохранился'); }
    echo "XML_ID значения Hoshizaki: {$enum['XML_ID']} → hoshizaki\n";
} else {
    echo "XML_ID уже hoshizaki\n";
}
foreach (['cache', 'managed_cache', 'stack_cache'] as $d) {
    foreach (glob($_SERVER['DOCUMENT_ROOT'] . "/bitrix/$d/*") ?: [] as $f) { \Bitrix\Main\IO\Directory::deleteDirectory($f); }
}
echo "Кэш очищен\n";
