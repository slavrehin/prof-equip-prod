<?php
/**
 * Блог (инфоблок blog): реальные даты публикации и недостающие обложки (2026-11-10).
 *
 * Проблема: import_content.php брал дату из первого <time> WP-страницы — это дата последнего поста
 * в сайдбаре, поэтому у 49 статей стояло 15.01.2026, у 71 — 27.01.2026, у 57 (снимки с Битрикс-прода)
 * даты не было вовсе; SORT у всех 500, а /blog/ сортировался по SORT → порядок «случайный».
 * 57 статей из Битрикс-снимков ссылались на /upload/iblock/... (потерян), поэтому были без картинки.
 *
 * dates.tsv  — CODE → article:published_time из WP-снимков web.archive.org (для всех статей).
 * covers/    — <CODE>.<ext>: og:image этих статей из WP-эпохи (archive/wayback/images + id_-снимки).
 *
 * Запуск (локально, на dev и на проде, из корня сайта, от www-data):
 *   php local/deploy/blog_dates_covers_2026_11_10/import.php [--dry] [--force-covers]
 * Идемпотентен: дату меняет только если отличается; обложку ставит только статьям без картинки
 * (--force-covers — заменить и существующие у статей из covers/).
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
$forceCovers = in_array('--force-covers', $argv, true);
$ib = (int) \CIBlock::GetList([], ['CODE' => 'blog', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
if (!$ib) { echo "нет инфоблока blog\n"; exit(1); }

$dates = [];
foreach (file(__DIR__ . '/dates.tsv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    [$code, $dt] = explode("\t", $line);
    $dates[$code] = $dt; // Y-m-d H:i:s
}
$covers = [];
foreach (glob(__DIR__ . '/covers/*') as $f) {
    $covers[pathinfo($f, PATHINFO_FILENAME)] = $f;
}

$be = new \CIBlockElement();
$st = ['date' => 0, 'cover' => 0, 'same' => 0, 'nodata' => 0, 'err' => 0];
$res = \CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, 'CHECK_PERMISSIONS' => 'N'], false, false,
    ['ID', 'CODE', 'ACTIVE_FROM', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']);
while ($e = $res->Fetch()) {
    $code = $e['CODE'];
    $upd = [];
    if (isset($dates[$code])) {
        $want = \ConvertTimeStamp(strtotime($dates[$code]), 'FULL');
        if (!$e['ACTIVE_FROM'] || MakeTimeStamp($e['ACTIVE_FROM']) !== MakeTimeStamp($want)) {
            $upd['ACTIVE_FROM'] = $want;
        }
    }
    $noPic = !$e['PREVIEW_PICTURE'] && !$e['DETAIL_PICTURE'];
    if (isset($covers[$code]) && ($noPic || $forceCovers)) {
        $upd['PREVIEW_PICTURE'] = \CFile::MakeFileArray($covers[$code]);
        $upd['DETAIL_PICTURE'] = \CFile::MakeFileArray($covers[$code]);
    }
    if (!isset($dates[$code]) && !isset($covers[$code])) { $st['nodata']++; echo "нет данных: $code\n"; continue; }
    if (!$upd) { $st['same']++; continue; }
    $what = implode(',', array_map(fn($k) => $k === 'ACTIVE_FROM' ? 'дата ' . $upd[$k] : $k, array_keys($upd)));
    if ($dry) { echo "[dry] #{$e['ID']} $code: $what\n"; }
    elseif ($be->Update($e['ID'], $upd)) { echo "#{$e['ID']} $code: $what\n"; }
    else { $st['err']++; echo "ОШИБКА #{$e['ID']} $code: {$be->LAST_ERROR}\n"; continue; }
    if (isset($upd['ACTIVE_FROM'])) $st['date']++;
    if (isset($upd['PREVIEW_PICTURE'])) $st['cover']++;
}
echo json_encode($st, JSON_UNESCAPED_UNICODE) . ($dry ? " (dry)" : '') . "\n";
if (!$dry) {
    \CIBlock::clearIblockTagCache($ib);
    BXClearCache(true);
}
