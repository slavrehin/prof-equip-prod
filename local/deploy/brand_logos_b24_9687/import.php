<?php
/**
 * Логотипы брендов из Б24 #9687, пункт чек-листа «Добавить логотипы» (инфоблок 14, свойство LOGO).
 * Файлы прислал контент-менеджер; это официальные логотипы, поэтому они ЗАМЕНЯЮТ прежние
 * (у Tolon, Ole Lighting, Norr11 стояли картинки из WP/archello).
 *
 * Сопоставление вложений Б24 → img/<CODE>.* (имена вложений не всегда совпадают с содержимым):
 *   78528a71-….png → tolon;  «Ling.png» на самом деле Pasmo → pasmo;  «Ling (2).png» → ling;
 *   из двух GGF взят «GGF (1).png» (без «s.r.l»);  остальные — по имени файла.
 * Все уменьшены до 900 px по ширине, непрозрачные PNG переведены в JPG.
 *
 * Плюс описание Ole Lighting из «Ole Lighting.docx» (ole-lighting.html) — ставится, только если текст пуст.
 *
 * Запуск (локально в контейнере, на dev и на проде — из корня сайта):
 *   php local/deploy/brand_logos_b24_9687/import.php [--dry]
 *
 * Идемпотентен: у поставленного файла описание «b24#9687 <CODE>»; повторный запуск его не трогает.
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
const MARK = 'b24#9687';
$dry = in_array('--dry', $argv, true);
$stats = ['set' => [], 'replaced' => [], 'already' => [], 'no_brand' => [], 'text' => []];

foreach (glob(__DIR__ . '/img/*.{png,jpg}', GLOB_BRACE) as $path) {
    $code = pathinfo($path, PATHINFO_FILENAME);
    $b = CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK_ID, '=CODE' => $code], false, false,
        ['ID', 'NAME', 'PROPERTY_LOGO'])->Fetch();
    if (!$b) { $stats['no_brand'][] = $code; continue; }
    $old = (int) ($b['PROPERTY_LOGO_VALUE'] ?? 0);
    $oldFile = $old ? CFile::GetFileArray($old) : null;
    if ($oldFile && $oldFile['DESCRIPTION'] === MARK . ' ' . $code
        && is_file($_SERVER['DOCUMENT_ROOT'] . $oldFile['SRC'])) { $stats['already'][] = $code; continue; }
    $stats[$oldFile ? 'replaced' : 'set'][] = $code;
    if ($dry) { continue; }
    $file = CFile::MakeFileArray($path);
    $file['MODULE_ID'] = 'iblock';
    $file['description'] = MARK . ' ' . $code;
    // LOGO одиночное (MULTIPLE=N) — SetPropertyValuesEx заменяет значение
    CIBlockElement::SetPropertyValuesEx($b['ID'], BRANDS_IBLOCK_ID, ['LOGO' => $file]);
}

// описание Ole Lighting — только если пусто
$ole = CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK_ID, '=CODE' => 'ole-lighting'], false, false,
    ['ID', 'DETAIL_TEXT', 'PREVIEW_TEXT'])->Fetch();
if ($ole && trim(strip_tags((string) $ole['DETAIL_TEXT'] . $ole['PREVIEW_TEXT'])) === '') {
    $stats['text'][] = 'ole-lighting';
    if (!$dry) {
        $html = file_get_contents(__DIR__ . '/ole-lighting.html');
        (new CIBlockElement())->Update($ole['ID'], [
            'PREVIEW_TEXT' => $html, 'PREVIEW_TEXT_TYPE' => 'html',
            'DETAIL_TEXT' => $html, 'DETAIL_TEXT_TYPE' => 'html',
        ]);
    }
}

if (!$dry) { CIBlock::clearIblockTagCache(BRANDS_IBLOCK_ID); BXClearCache(true, '/s1/bitrix/news.list/'); BXClearCache(true, '/s1/bitrix/news.detail/'); }
echo json_encode($stats, JSON_UNESCAPED_UNICODE), "\n";
