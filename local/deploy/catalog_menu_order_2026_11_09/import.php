<?php
/**
 * Порядок корневых разделов каталога (меню «Каталог» в шапке и плитки), 2026-11-09.
 * Прачка → Кухня → Текстиль → Мебель → З/ч; Химия (в списке заказчика не было) — последней.
 * Меню (catalog.section.list) сортирует по LEFT_MARGIN, который Битрикс пересчитывает по SORT, NAME.
 *
 * Запуск (локально, на dev и на проде, из корня сайта, от www-data):
 *   php local/deploy/catalog_menu_order_2026_11_09/import.php [--dry]
 * Идемпотентен.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

$dry = in_array('--dry', $argv, true);
$order = [
    'prachechnoe-oborudovanie'           => 100,
    'oborudovanie-professionalnoj-kuhni' => 200,
    'professionalnyj-tekstil'            => 300,
    'mebel'                              => 400,
    'zapasnye-chasti'                    => 500,
    'professionalnaya-himiya'            => 600,
];
$sec = new \CIBlockSection();
$r = \CIBlockSection::GetList([], ['IBLOCK_ID' => 11, 'DEPTH_LEVEL' => 1, 'CHECK_PERMISSIONS' => 'N'], false, ['ID', 'CODE', 'NAME', 'SORT']);
while ($s = $r->Fetch()) {
    if (!isset($order[$s['CODE']]) || (int) $s['SORT'] === $order[$s['CODE']]) { continue; }
    echo "{$s['CODE']}: {$s['SORT']} -> {$order[$s['CODE']]}\n";
    if (!$dry && !$sec->Update($s['ID'], ['SORT' => $order[$s['CODE']]])) { echo "  ERR {$sec->LAST_ERROR}\n"; }
}
if (!$dry) { \CIBlock::clearIblockTagCache(11); BXClearCache(true); }
$r = \CIBlockSection::GetList(['LEFT_MARGIN' => 'ASC'], ['IBLOCK_ID' => 11, 'DEPTH_LEVEL' => 1, 'CHECK_PERMISSIONS' => 'N'], false, ['CODE', 'SORT']);
while ($s = $r->Fetch()) { echo "  {$s['SORT']}  {$s['CODE']}\n"; }
