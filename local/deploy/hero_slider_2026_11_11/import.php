<?php
/**
 * Новые слайды героя на главной (инфоблок herohome, элемент herohome, свойство HERO_SLIDER_IMAGES), 2026-11-11.
 * Источник: Яндекс.Диск «Фото для слайдера на главную страницу ПРОФЭКВИП» → «Затемненная версия (на сайт)»,
 * PNG сконвертированы в JPEG q82 (img/). Порядок = буллеты HERO_BULLET_TEXTS:
 * отелей, номерного фонда, прачечных, объектов питания, мед. учреждений.
 *
 * Запуск (локально, на dev и на проде, из корня сайта, от www-data):
 *   php local/deploy/hero_slider_2026_11_11/import.php [--dry]
 * Идемпотентен: если в свойстве уже ровно эти файлы (по ORIGINAL_NAME) — ничего не делает.
 * Старые файлы перед удалением копируются в /upload/backup_hero_slider/<дата-время>/.
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
$ib = (int) \CIBlock::GetList([], ['CODE' => 'herohome', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$el = \CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, '=CODE' => 'herohome', 'CHECK_PERMISSIONS' => 'N'], false, ['nTopCount' => 1], ['ID'])->Fetch();
if (!$ib || !$el) { echo "нет инфоблока/элемента herohome\n"; exit(1); }
$id = (int) $el['ID'];

$new = glob(__DIR__ . '/img/*.jpg');
sort($new, SORT_NATURAL);
$newNames = array_map('basename', $new);

$old = []; // valueId => fileId
$r = \CIBlockElement::GetProperty($ib, $id, ['sort' => 'asc', 'id' => 'asc'], ['CODE' => 'HERO_SLIDER_IMAGES']);
while ($p = $r->Fetch()) {
    if ($p['VALUE']) { $old[$p['PROPERTY_VALUE_ID']] = (int) $p['VALUE']; }
}
$oldNames = [];
foreach ($old as $fid) { $oldNames[] = (\CFile::GetFileArray($fid)['ORIGINAL_NAME'] ?? '?'); }
echo 'сейчас: ' . implode(', ', $oldNames) . "\n";
if ($oldNames === $newNames) { echo "уже новые слайды, ничего не делаю\n"; exit(0); }
echo 'будет:  ' . implode(', ', $newNames) . "\n";
if ($dry) { exit(0); }

// бэкап старых файлов
$bak = $_SERVER['DOCUMENT_ROOT'] . '/upload/backup_hero_slider/' . date('Ymd_His');
foreach ($old as $fid) {
    $f = \CFile::GetFileArray($fid);
    $src = $f ? $_SERVER['DOCUMENT_ROOT'] . $f['SRC'] : '';
    if ($src && is_file($src)) {
        @mkdir($bak, 0775, true);
        copy($src, $bak . '/' . $fid . '_' . basename($src));
    }
}

// удалить старые значения (с файлами), затем записать новые
$del = [];
foreach ($old as $valueId => $fid) { $del[$valueId] = ['VALUE' => ['del' => 'Y', 'MODULE_ID' => 'iblock']]; }
if ($del) { \CIBlockElement::SetPropertyValueCode($id, 'HERO_SLIDER_IMAGES', $del); }
$vals = [];
foreach ($new as $i => $path) { $vals['n' . $i] = ['VALUE' => \CFile::MakeFileArray($path)]; }
\CIBlockElement::SetPropertyValueCode($id, 'HERO_SLIDER_IMAGES', $vals);

\CIBlock::clearIblockTagCache($ib);
BXClearCache(true);

$check = [];
$r = \CIBlockElement::GetProperty($ib, $id, ['sort' => 'asc', 'id' => 'asc'], ['CODE' => 'HERO_SLIDER_IMAGES']);
while ($p = $r->Fetch()) { if ($p['VALUE']) { $f = \CFile::GetFileArray($p['VALUE']); $check[] = $f['ORIGINAL_NAME'] . ' → ' . $f['SRC']; } }
echo "готово:\n  " . implode("\n  ", $check) . "\n" . (is_dir($bak) ? "бэкап старых: $bak\n" : '');
