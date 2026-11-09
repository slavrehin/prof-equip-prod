<?php
/**
 * Локальная пересборка: логотипы брендов (инфоблок 14, свойство LOGO).
 *
 * Все шаблоны выводят логотип только из свойства LOGO: «Бренды, задействованные в проекте»
 * (project-detail/result_modifier), brands-list, brands_slider. Файлы LOGO были в /upload прода,
 * который потерян, — заполнено было лишь у 15 брендов, остальные выводились пустой рамкой с названием.
 *
 * Источники (только для брендов с пустым LOGO; повторный запуск безопасен):
 *  1) data/brand_logos/<CODE>.(jpg|png) — логотипы старого WP из веб-архива
 *     (wp-content/uploads/…/<Brand>_logo_300x100.jpg, иначе 150x150), подобраны по названию/коду бренда;
 *  2) иначе — копия PREVIEW_PICTURE бренда (её ставили landing_*_fix.php и apply_photos).
 *
 *   php local/bootstrap/brand_logos.php [--dry]
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = 'localhost';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

const BRANDS_IBLOCK = 14;
$dry = in_array('--dry', $argv, true);
$dir = __DIR__ . '/data/brand_logos';

$stats = ['wp' => 0, 'preview' => 0, 'none' => 0, 'had' => 0];
$res = CIBlockElement::GetList(['NAME' => 'ASC'], ['IBLOCK_ID' => BRANDS_IBLOCK], false, false,
    ['ID', 'CODE', 'NAME', 'PREVIEW_PICTURE', 'PROPERTY_LOGO']);
while ($b = $res->Fetch()) {
    if (!empty($b['PROPERTY_LOGO_VALUE']) && CFile::GetPath($b['PROPERTY_LOGO_VALUE'])) { $stats['had']++; continue; }

    $file = null; $src = '';
    foreach (['png', 'jpg', 'svg'] as $ext) {
        if ($b['CODE'] !== '' && is_file("$dir/{$b['CODE']}.$ext")) { $file = CFile::MakeFileArray("$dir/{$b['CODE']}.$ext"); $src = 'wp'; break; }
    }
    if (!$file && $b['PREVIEW_PICTURE'] && ($path = CFile::GetPath($b['PREVIEW_PICTURE']))
        && is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
        $file = CFile::MakeFileArray($_SERVER['DOCUMENT_ROOT'] . $path);
        $src = 'preview';
    }
    if (!$file) { $stats['none']++; echo "  нет логотипа: {$b['NAME']}\n"; continue; }

    $stats[$src]++;
    if ($dry) { continue; }
    $file['MODULE_ID'] = 'iblock';
    CIBlockElement::SetPropertyValuesEx($b['ID'], BRANDS_IBLOCK, ['LOGO' => $file]);
}
if (!$dry) {
    \CIBlock::clearIblockTagCache(BRANDS_IBLOCK);
    \CIBlock::clearIblockTagCache(8);
    BXClearCache(true, '/');
}
echo json_encode($stats), "\n";
