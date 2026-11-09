<?php
/**
 * «Готовые решения» (iblock solution): восстановление контента детальных страниц, 2026-11-09.
 *
 * Было после восстановления: PREVIEW_TEXT — обрезанный тизер «…», DETAIL_TEXT у 6 страниц — только вступление
 * (нет спецификаций), у 9 — сырая WP-вёрстка (vc_row) с битыми /wp-content картинками; GALLERY и TEXT_BLOCKS пусты.
 *
 * data.json собран из веб-архива (build_from_wayback.py: снимки страниц из web.archive.org id_):
 *  - 8 страниц — из Bitrix-снимков апр–июл 2026 (точный прод: PREVIEW_TEXT, TEXT_BLOCKS, DETAIL_TEXT);
 *  - 7 страниц — из последних WP-снимков (та же разбивка: вступление / блоки / спецификация).
 * Фото и схемы внутри страниц (/upload/medialibrary, wp-content/uploads/2017/02) в архиве почти не сохранились:
 * найдены только 2 (img/<code>/). Отсутствующие картинки и подписи «Схема расположения оборудования:» убраны.
 * GALLERY: копия DETAIL_PICTURE (обложка) + найденные фото, чтобы на странице было хоть одно фото.
 *
 * Запуск (локально, dev, прод; из корня сайта, от www-data):
 *   php local/deploy/solutions_content_2026_11_09/import.php [--dry] [--force-gallery]
 * Идемпотентен: тексты перезаписываются тем же; GALLERY ставится, только если пуста (или --force-gallery).
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
$forceGallery = in_array('--force-gallery', $argv, true);
$ib = (int) \CIBlock::GetList([], ['CODE' => 'solution', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$data = json_decode(file_get_contents(__DIR__ . '/data.json'), true);
$be = new \CIBlockElement();
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/solutions';

foreach ($data as $code => $d) {
    $e = \CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, '=CODE' => $code, 'CHECK_PERMISSIONS' => 'N'], false, ['nTopCount' => 1],
        ['ID', 'PREVIEW_TEXT', 'DETAIL_TEXT', 'DETAIL_PICTURE'])->Fetch();
    if (!$e) { echo "$code: нет элемента\n"; continue; }

    // инлайн-картинки -> /upload/solutions/<code>/<file>
    $srcDir = __DIR__ . "/img/$code";
    $fix = function (string $html) use ($code, $srcDir, $uploadDir, $dry) {
        return preg_replace_callback('/\{IMG:([^}]+)\}/', function ($m) use ($code, $srcDir, $uploadDir, $dry) {
            $dst = "$uploadDir/$code/{$m[1]}";
            if (!$dry && !is_file($dst)) {
                @mkdir(dirname($dst), 0775, true);
                copy("$srcDir/{$m[1]}", $dst);
            }
            return "/upload/solutions/$code/{$m[1]}";
        }, $html);
    };
    $preview = $fix($d['preview']);
    $detail = $fix($d['detail']);
    $blocks = [];
    foreach ($d['blocks'] as $i => [$title, $html]) {
        $blocks['n' . $i] = ['VALUE' => ['TYPE' => 'HTML', 'TEXT' => $fix($html)], 'DESCRIPTION' => $title];
    }

    $curGallery = [];
    $r = \CIBlockElement::GetProperty($ib, $e['ID'], [], ['CODE' => 'GALLERY']);
    while ($p = $r->Fetch()) { if ($p['VALUE']) { $curGallery[] = $p['VALUE']; } }

    $gallery = null;
    if (!$curGallery || $forceGallery) {
        $gallery = [];
        if ($e['DETAIL_PICTURE']) { // обложка первым слайдом
            $gallery['n0'] = ['VALUE' => \CFile::MakeFileArray($_SERVER['DOCUMENT_ROOT'] . \CFile::GetPath($e['DETAIL_PICTURE']))];
        }
        foreach ($d['gallery'] as $i => $f) {
            $gallery['n' . ($i + 1)] = ['VALUE' => \CFile::MakeFileArray("$srcDir/$f")];
        }
    }

    printf("%s: PREVIEW %d->%d, DETAIL %d->%d, TEXT_BLOCKS %d, GALLERY %s\n", $code,
        mb_strlen($e['PREVIEW_TEXT']), mb_strlen($preview), mb_strlen($e['DETAIL_TEXT']), mb_strlen($detail),
        count($blocks), $gallery === null ? 'есть (' . count($curGallery) . '), не трогаю'
            : count($gallery) . ' (обложка + ' . count($d['gallery']) . ' фото)');
    if ($dry) { continue; }

    if (!$be->Update($e['ID'], [
        'PREVIEW_TEXT' => $preview, 'PREVIEW_TEXT_TYPE' => 'html',
        'DETAIL_TEXT' => $detail, 'DETAIL_TEXT_TYPE' => 'html',
    ])) {
        echo "  ошибка: {$be->LAST_ERROR}\n";
        continue;
    }
    \CIBlockElement::SetPropertyValuesEx($e['ID'], $ib, ['TEXT_BLOCKS' => $blocks ?: false]);
    if ($gallery !== null) {
        if ($curGallery) { // --force-gallery: удалить старые
            $del = [];
            foreach ($curGallery as $fid) { $del[$fid] = ['VALUE' => ['del' => 'Y']]; }
            \CIBlockElement::SetPropertyValueCode($e['ID'], 'GALLERY', $del);
        }
        \CIBlockElement::SetPropertyValueCode($e['ID'], 'GALLERY', $gallery);
    }
}
if (!$dry) { \CIBlock::clearIblockTagCache($ib); BXClearCache(true); }
