<?php
/**
 * Локальная пересборка: добирает для лендинга /prachechnaya/ то, чего нет в git (data.json от 14.10
 * ссылается на картинки из upload/iblock прода). Запускать ПОСЛЕ импорта Б24 (content_b24.json) и apply_photos:
 * в replay лендинг импортируется раньше, чем появляются 6 из 7 его проектов, поэтому импорт повторяется здесь.
 * Источник картинок — веб-архив (wp-content), файлы в data/landing_prachechnaya_fix/
 * (upload-iblock-<dir>-<name> — картинки категорий, которые import.php ждёт в upload/iblock).
 * Картинку ставит только если её нет; повторный запуск безопасен.
 *
 *   php local/bootstrap/landing_prachechnaya_fix.php
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = 'localhost';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

$dir = __DIR__ . '/data/landing_prachechnaya_fix';
foreach (glob($dir . '/upload-iblock-*') as $src) {
    [, , $sub, $name] = explode('-', basename($src), 4);
    $dst = $_SERVER['DOCUMENT_ROOT'] . "/upload/iblock/$sub/$name";
    if (!is_file($dst)) {
        @mkdir(dirname($dst), 0775, true);
        copy($src, $dst);
    }
}
passthru('php ' . escapeshellarg($_SERVER['DOCUMENT_ROOT'] . '/local/deploy/landing_prachechnaya/import.php'));

$ibId = static fn(string $code): int => (int)(\CIBlock::GetList([], ['CODE' => $code, 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'] ?? 0);

// [инфоблок, код элемента, файл, ставить DETAIL_PICTURE, доп. поля если пусто]
$items = [
    ['projects', 'mantera-supreme-seaside-5-2', 'mantera-supreme-seaside-5-2.webp', true, []],
    // статья без даты и картинки; дата — по папке картинки в WP (2022/04, e1649166459283)
    ['blog', 'oborudovanie-dlya-prachechnoj-tolon-stabilnoe-kachestvo-i-postavki-v-rossiyu', 'blog-tolon.jpg', true,
        ['ACTIVE_FROM' => '05.04.2022 12:00:00']],
    ['catalog', 'pyatnovyvodnoj-stol-pony-jolly', 'pony-jolly.jpg', true, []],
];
foreach (glob($dir . '/brand-*') as $path) {
    $items[] = ['brands', substr(pathinfo($path, PATHINFO_FILENAME), 6), basename($path), false, []];
}

foreach ($items as [$ibCode, $code, $file, $withDetail, $extra]) {
    $el = \CIBlockElement::GetList([], ['IBLOCK_ID' => $ibId($ibCode), 'CODE' => $code], false, false,
        ['ID', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'ACTIVE_FROM'])->Fetch();
    if (!$el) {
        echo "$ibCode/$code: нет элемента\n";
        continue;
    }
    $path = "$dir/$file";
    $fields = [];
    if (!$el['PREVIEW_PICTURE']) {
        $fields['PREVIEW_PICTURE'] = \CFile::MakeFileArray($path);
    }
    if ($withDetail && !$el['DETAIL_PICTURE']) {
        $fields['DETAIL_PICTURE'] = \CFile::MakeFileArray($path);
    }
    foreach ($extra as $k => $v) {
        if (empty($el[$k])) {
            $fields[$k] = $v;
        }
    }
    if (!$fields) {
        echo "$ibCode/$code: уже заполнено\n";
        continue;
    }
    $be = new \CIBlockElement();
    echo "$ibCode/$code: " . ($be->Update($el['ID'], $fields) ? 'OK' : 'ошибка ' . $be->LAST_ERROR) . "\n";
}

// Заголовок блока «Бренды» — в экспорте 14.10 пуст; текст из задачи Б24 #9199 (15.09): «Наши бренды»
$dirIb = $ibId('prachechnaya');
$dirEl = \CIBlockElement::GetList([], ['IBLOCK_ID' => $dirIb, 'CODE' => 'prachechnaya'], false, false, ['ID'])->Fetch();
$prop = \CIBlockElement::GetProperty($dirIb, $dirEl['ID'], [], ['CODE' => 'LAND_BLOCKS'])->Fetch();
$blocks = json_decode((string)$prop['VALUE'], true);
if (is_array($blocks) && isset($blocks['brands']) && trim((string)$blocks['brands']['title']) === '') {
    $blocks['brands']['title'] = 'Наши бренды';
    \CIBlockElement::SetPropertyValuesEx($dirEl['ID'], $dirIb,
        ['LAND_BLOCKS' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    echo "LAND_BLOCKS.brands.title: OK\n";
}
\CIBlock::clearIblockTagCache($dirIb);
