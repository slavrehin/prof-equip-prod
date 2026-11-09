<?php
/**
 * Логотипы брендов, добранные 2026-11-09 (инфоблок 14, свойство LOGO) — для брендов, у которых
 * логотипа не было после пересборки (оригиналы с прода в /upload потеряны, в Wayback их нет).
 *
 * Источники файлов в img/<CODE>.*:
 *  - vondom.svg      — официальный SVG с cdn.vondom.com (logo-vondom-black.svg);
 *  - manutti.svg     — symbol #logo из шапки manutti.com (снимок Wayback), цвет #111;
 *  - prismafood.png  — скриншот логотипа с prismafood.com (белый на чёрной плашке → тёмный на прозрачном);
 *  - jipa.png        — aligroup.com/brand/jipa (дистрибьютор), обрезаны поля;
 *  - hansk.png       — Hans K, страница бренда на archello.com;
 *  - ole-lighting.jpg — Olé Lighting, страница бренда на archello.com.
 *
 * Запуск (локально в контейнере, на dev и на проде — из корня сайта):
 *   php local/deploy/brand_logos_2026_11_09/import.php [--dry]
 *
 * Идемпотентен: логотип ставится только бренду, у которого LOGO пуст или файл LOGO отсутствует.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

const BRANDS_IBLOCK_ID = 14;
$dry = in_array('--dry', $argv, true);
$stats = ['set' => [], 'had' => [], 'no_brand' => []];

foreach (glob(__DIR__ . '/img/*.{png,jpg,svg}', GLOB_BRACE) as $path) {
    $code = pathinfo($path, PATHINFO_FILENAME);
    $b = CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK_ID, '=CODE' => $code], false, false,
        ['ID', 'NAME', 'PROPERTY_LOGO'])->Fetch();
    if (!$b) { $stats['no_brand'][] = $code; continue; }
    $logo = $b['PROPERTY_LOGO_VALUE'] ?? null;
    if ($logo && ($p = CFile::GetPath($logo)) && is_file($_SERVER['DOCUMENT_ROOT'] . $p)) { $stats['had'][] = $code; continue; }
    $stats['set'][] = $code;
    if (!$dry) {
        $file = CFile::MakeFileArray($path);
        $file['MODULE_ID'] = 'iblock';
        $file['description'] = $b['NAME'];
        CIBlockElement::SetPropertyValuesEx($b['ID'], BRANDS_IBLOCK_ID, ['LOGO' => $file]);
    }
}
if (!$dry) { CIBlock::clearIblockTagCache(BRANDS_IBLOCK_ID); BXClearCache(true, '/s1/bitrix/news.list/'); }
echo json_encode($stats, JSON_UNESCAPED_UNICODE), "\n";
