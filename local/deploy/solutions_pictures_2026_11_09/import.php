<?php
/**
 * Фото 6 «Готовых решений» без картинок (блок «Готовые решения» на направлениях, /gotovye-resheniya/), 2026-11-09.
 * Bitrix-оригиналы /upload утеряны; берём обложки (og:image) WP-версий страниц из веб-архива (окт–ноя 2025),
 * файлы — из archive/wayback/images/wp-content/uploads/2017/02/.
 *
 * Запуск (локально, на dev и на проде, из корня сайта, от www-data):
 *   php local/deploy/solutions_pictures_2026_11_09/import.php [--dry]
 * Идемпотентен: ставит PREVIEW_PICTURE/DETAIL_PICTURE только если пусто.
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
$ib = (int) \CIBlock::GetList([], ['CODE' => 'solution', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$be = new \CIBlockElement();
foreach (glob(__DIR__ . '/img/*') as $path) {
    $code = pathinfo($path, PATHINFO_FILENAME);
    $e = \CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, '=CODE' => $code, 'CHECK_PERMISSIONS' => 'N'], false, ['nTopCount' => 1],
        ['ID', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'])->Fetch();
    if (!$e) { echo "$code: нет элемента\n"; continue; }
    $f = [];
    if (!$e['PREVIEW_PICTURE']) { $f['PREVIEW_PICTURE'] = \CFile::MakeFileArray($path); }
    if (!$e['DETAIL_PICTURE']) { $f['DETAIL_PICTURE'] = \CFile::MakeFileArray($path); }
    if (!$f) { echo "$code: уже есть\n"; continue; }
    if ($dry) { echo "$code: будет " . implode('+', array_keys($f)) . "\n"; continue; }
    echo "$code: " . ($be->Update($e['ID'], $f) ? 'фото OK' : 'ошибка ' . $be->LAST_ERROR) . "\n";
}
if (!$dry) { \CIBlock::clearIblockTagCache($ib); BXClearCache(true); }
