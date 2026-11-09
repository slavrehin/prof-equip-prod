<?php
/**
 * Локальная пересборка: картинки корневых разделов каталога для выпадающего меню «Каталог»
 * (шаблон catalog.section.list/section_head берёт поле PICTURE раздела).
 * Оригиналы прода (upload/iblock/54d/e20g..., e3b/hg30..., ...) не сохранились ни в архиве, ни в
 * веб-архиве, поэтому берём исходные картинки дизайнера из вёрстки local/layout/dist/assets/img/.
 * Ставит картинку только разделу без PICTURE (с --force — заменяет); повторный запуск безопасен.
 *
 *   php local/bootstrap/section_pictures.php [--force]
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = 'localhost';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

$force = in_array('--force', $argv, true);
$img = $_SERVER['DOCUMENT_ROOT'] . '/local/layout/dist/assets/img';
$map = [
    'prachechnoe-oborudovanie'           => "$img/header-catalog/1.webp",
    'professionalnyj-tekstil'            => "$img/header-catalog/2.webp",
    'zapasnye-chasti'                    => "$img/header-catalog/3.webp",
    'professionalnaya-himiya'            => "$img/header-catalog/4.webp",
    'oborudovanie-professionalnoj-kuhni' => "$img/header-catalog/5.webp",
    'mebel'                              => "$img/furniture-about/img.webp",
];
$iblockId = 11;

$bs = new \CIBlockSection();
foreach ($map as $code => $file) {
    $s = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code, 'DEPTH_LEVEL' => 1], false, ['ID', 'PICTURE'])->Fetch();
    if (!$s) { echo "skip $code: раздел не найден\n"; continue; }
    if ($s['PICTURE'] && !$force) { echo "keep $code: картинка уже есть\n"; continue; }
    if (!is_file($file)) { echo "skip $code: нет файла $file\n"; continue; }
    $arFile = \CFile::MakeFileArray($file);
    $arFile['MODULE_ID'] = 'iblock';
    if ($s['PICTURE']) { $arFile['del'] = 'Y'; $arFile['old_file'] = $s['PICTURE']; }
    $ok = $bs->Update($s['ID'], ['PICTURE' => $arFile], false, false, false);
    echo ($ok ? 'set ' : 'FAIL ') . "$code" . ($ok ? '' : ': ' . $bs->LAST_ERROR) . "\n";
}
\CIBlock::clearIblockTagCache($iblockId);
BXClearCache(true, '/');
