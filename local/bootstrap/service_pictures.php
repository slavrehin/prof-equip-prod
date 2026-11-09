<?php
/**
 * Локальная пересборка: картинки элементов инфоблока «Услуги» (services) для выпадающего меню
 * «Услуги» в шапке (шаблон news.list/directions_header берёт PREVIEW_PICTURE).
 * Оригиналы прода не сохранились; в макете дизайнера (about.html) в этом меню стоят заглушки
 * header-catalog/1-5, которые уже заняты меню «Каталог», поэтому берём тематические картинки
 * из вёрстки local/layout/dist/assets/img/.
 * Ставит картинку только элементу без PREVIEW_PICTURE (с --force — заменяет); повторный запуск безопасен.
 *
 *   php local/bootstrap/service_pictures.php [--force]
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
    'konsalting'                              => "$img/consulting/img@2x.webp",
    'montazhnye-i-puskonaladochnye-raboty'    => "$img/service/img4@2x.webp",
    'kompleksnoe-osnashhenie-otelej'          => "$img/equipment-about/2@2x.webp",
    'postavka-aksessuarov-i-zapasnyh-chastej' => "$img/service/img3@2x.webp",
    'prodazha-oborudovaniya'                  => "$img/sale-equipment/1@2x.webp",
    'servisnoe-obsluzhivanie'                 => "$img/service/img@2x.webp",
    'tehnologicheskoe-proektirovanie'         => "$img/service/img2@2x.webp",
];
$iblockId = (int)\CIBlock::GetList([], ['CODE' => 'services', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
if (!$iblockId) { echo "нет инфоблока services\n"; exit(1); }

$el = new \CIBlockElement();
foreach ($map as $code => $file) {
    $e = \CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code], false, false, ['ID', 'PREVIEW_PICTURE'])->Fetch();
    if (!$e) { echo "skip $code: элемент не найден\n"; continue; }
    if ($e['PREVIEW_PICTURE'] && !$force) { echo "keep $code: картинка уже есть\n"; continue; }
    if (!is_file($file)) { echo "skip $code: нет файла $file\n"; continue; }
    $arFile = \CFile::MakeFileArray($file);
    $arFile['MODULE_ID'] = 'iblock';
    if ($e['PREVIEW_PICTURE']) { $arFile['del'] = 'Y'; $arFile['old_file'] = $e['PREVIEW_PICTURE']; }
    $ok = $el->Update($e['ID'], ['PREVIEW_PICTURE' => $arFile], false, false, false);
    echo ($ok ? 'set ' : 'FAIL ') . $code . ($ok ? '' : ': ' . $el->LAST_ERROR) . "\n";
}
\CIBlock::clearIblockTagCache($iblockId);
BXClearCache(true, '/');
