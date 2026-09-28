<?php
/**
 * Возврат /prachechnaya/ на старую страницу (до лендинга) — данные элемента направления.
 * Код старой страницы (prachechnaya/index.php) возвращается коммитом; этот скрипт убирает то,
 * что старая страница тоже читает и чего до лендинга не было:
 *  - привязку PROJECTS (старая страница выводит по ней блок проектов; до лендинга было пусто);
 *  - SEO-шаблоны элемента (ELEMENT_PAGE_TITLE подменил бы H1 старой страницы через news.detail SET_TITLE).
 * Свойства LAND_* и CARD_* проектов не трогаются (старая страница их не читает) — чтобы снова
 * включить лендинг: вернуть новый prachechnaya/index.php и запустить import.php.
 *
 *   php local/deploy/landing_prachechnaya/disable.php [--dry]
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\CModule::IncludeModule('iblock');
global $DB;

$dry = in_array('--dry', $argv, true);
$ibId = (int)(\CIBlock::GetList([], ['CODE' => 'prachechnaya', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'] ?? 0);
$elId = $ibId ? (int)(\CIBlockElement::GetList([], ['IBLOCK_ID' => $ibId, 'CODE' => 'prachechnaya'], false, ['nTopCount' => 1], ['ID'])->Fetch()['ID'] ?? 0) : 0;
if (!$elId) {
    fwrite(STDERR, "Не найден элемент направления prachechnaya\n");
    exit(1);
}

$n = 0;
$rs = \CIBlockElement::GetProperty($ibId, $elId, [], ['CODE' => 'PROJECTS']);
while ($p = $rs->Fetch()) {
    if ((int)$p['VALUE']) {
        $n++;
    }
}
echo "PROJECTS: $n привязок" . ($dry ? '' : ' → очищено') . "\n";

$tpl = new \Bitrix\Iblock\InheritedProperty\ElementTemplates($ibId, $elId);
$own = [];
foreach ($tpl->findTemplates() as $code => $row) {
    if (($row['INHERITED'] ?? 'N') === 'N' && $row['TEMPLATE'] !== '') {
        $own[] = $code;
    }
}
echo "SEO-шаблоны элемента: " . ($own ? implode(', ', $own) : 'нет') . ($dry || !$own ? '' : ' → удалены') . "\n";

if ($dry) {
    echo "DRY: ничего не записано\n";
    exit(0);
}

\CIBlockElement::SetPropertyValuesEx($elId, $ibId, ['PROJECTS' => false]);
if ($own) {
    $tpl->delete();
    (new \Bitrix\Iblock\InheritedProperty\ElementValues($ibId, $elId))->clearValues();
}
\CIBlock::clearIblockTagCache($ibId);
BXClearCache(true, '/profequip/direction_landing/');

// проверка запросом к базе
$left = (int)$DB->Query("SELECT COUNT(*) C FROM b_iblock_iproperty WHERE IBLOCK_ID = $ibId AND ENTITY_TYPE = 'E' AND ENTITY_ID = $elId")->Fetch()['C'];
$proj = (int)$DB->Query("SELECT COUNT(*) C FROM b_iblock_element_property ep JOIN b_iblock_property p ON p.ID = ep.IBLOCK_PROPERTY_ID WHERE ep.IBLOCK_ELEMENT_ID = $elId AND p.CODE = 'PROJECTS'")->Fetch()['C'];
echo "Осталось: SEO-шаблонов $left, привязок PROJECTS $proj\n";
echo ($left || $proj) ? "ВНИМАНИЕ: не всё очищено\n" : "Готово\n";
