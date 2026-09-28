<?php
/**
 * Экспорт текущего содержимого лендинга «Прачечная» (/prachechnaya/) с test3 — для переноса на прод
 * (import.php рядом). Сид-миграция 2026-09-18-landing-prachechnaya-seed.php даёт только стартовый
 * контент из макета; всё, что потом правилось в админке test3, переносится этой парой скриптов.
 *
 * Запуск (в контейнере test3):
 *   php local/deploy/landing_prachechnaya/export.php
 *
 * Пишет data.json и копирует загруженные через редактор картинки (upload/landing/...) в files/.
 * Картинки, указывающие на файлы из upload/iblock (клоны строк b_file), не копируются — на проде
 * эти файлы уже есть, импорт заводит на них свою строку b_file.
 * Связи (проекты, статьи, новости, бренды) выгружаются по CODE — ID на test3 и проде расходятся.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'test3.prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\CModule::IncludeModule('iblock');
global $DB;

const DIRECTION_CODE = 'prachechnaya';
const IMAGE_KEYS = ['pic', 'pic_mob', 'featured_pic'];
const LINK_PROPS = ['PROJECTS', 'BRANDS', 'LAND_MAT_BLOG', 'LAND_MAT_NEWS'];
const CARD_PROPS = ['CARD_META', 'CARD_TEXT', 'CARD_SCOPE'];

$ib = \CIBlock::GetList([], ['CODE' => DIRECTION_CODE, 'CHECK_PERMISSIONS' => 'N'])->Fetch();
$el = $ib ? \CIBlockElement::GetList([], ['IBLOCK_ID' => $ib['ID'], 'CODE' => DIRECTION_CODE], false, ['nTopCount' => 1], ['ID'])->Fetch() : null;
if (!$el) {
    fwrite(STDERR, "Не найден элемент направления " . DIRECTION_CODE . "\n");
    exit(1);
}
$ibId = (int)$ib['ID'];
$elId = (int)$el['ID'];

$out = ['direction' => DIRECTION_CODE, 'exported' => date('c'), 'props' => [], 'links' => [], 'seo' => [], 'cards' => [], 'files' => []];

// JSON-свойства лендинга (ProfequipLandingCards) — как есть
$fileIds = [];
$rs = $DB->Query("SELECT CODE FROM b_iblock_property WHERE IBLOCK_ID = $ibId AND CODE LIKE 'LAND\\_%' AND USER_TYPE = 'ProfequipLandingCards'");
while ($p = $rs->Fetch()) {
    $v = \CIBlockElement::GetProperty($ibId, $elId, [], ['CODE' => $p['CODE']])->Fetch();
    $raw = (string)($v['VALUE'] ?? ''); // НЕ '~VALUE' — у GetProperty()->Fetch() его нет
    $data = $raw === '' ? [] : json_decode($raw, true);
    if (!is_array($data)) {
        fwrite(STDERR, "{$p['CODE']}: не JSON, пропускаю\n");
        continue;
    }
    array_walk_recursive($data, static function ($val, $key) use (&$fileIds) {
        if (in_array($key, IMAGE_KEYS, true) && (int)$val > 0) {
            $fileIds[(int)$val] = true;
        }
    });
    $out['props'][$p['CODE']] = $data;
}

// Привязки к элементам — по коду инфоблока + CODE элемента
$linkedIds = [];
foreach (LINK_PROPS as $code) {
    $rs = \CIBlockElement::GetProperty($ibId, $elId, ['value_id' => 'asc'], ['CODE' => $code]);
    $out['links'][$code] = [];
    while ($p = $rs->Fetch()) {
        if (!(int)$p['VALUE']) {
            continue;
        }
        $e = \CIBlockElement::GetList([], ['ID' => (int)$p['VALUE']], false, false, ['ID', 'CODE', 'IBLOCK_ID', 'IBLOCK_CODE'])->Fetch();
        if (!$e || $e['CODE'] === '') {
            fwrite(STDERR, "$code: элемент {$p['VALUE']} без CODE, пропускаю\n");
            continue;
        }
        $out['links'][$code][] = ['iblock' => $e['IBLOCK_CODE'], 'code' => $e['CODE']];
        if ($code === 'PROJECTS') {
            $linkedIds[] = [(int)$e['IBLOCK_ID'], (int)$e['ID'], $e['CODE']];
        }
    }
}

// Карточки проектов на лендинге (свойства CARD_* в инфоблоке projects)
foreach ($linkedIds as [$pIb, $pId, $pCode]) {
    foreach (CARD_PROPS as $code) {
        $rs = \CIBlockElement::GetProperty($pIb, $pId, ['value_id' => 'asc'], ['CODE' => $code]);
        $vals = [];
        while ($p = $rs->Fetch()) {
            $v = is_array($p['VALUE']) ? ($p['VALUE']['TEXT'] ?? '') : (string)$p['VALUE'];
            if ($v !== '') {
                $vals[] = $v;
            }
        }
        $out['cards'][$pCode][$code] = $vals;
    }
}

// SEO-шаблоны, заданные на самом элементе (не унаследованные)
$tpl = new \Bitrix\Iblock\InheritedProperty\ElementTemplates($ibId, $elId);
foreach ($tpl->findTemplates() as $code => $row) {
    if (($row['INHERITED'] ?? 'N') === 'N' && $row['TEMPLATE'] !== '') {
        $out['seo'][$code] = $row['TEMPLATE'];
    }
}

// Файлы картинок
$filesDir = __DIR__ . '/files';
foreach (array_keys($fileIds) as $id) {
    $f = $DB->Query('SELECT * FROM b_file WHERE ID = ' . $id)->Fetch();
    if (!$f) {
        fwrite(STDERR, "b_file $id не найден\n");
        continue;
    }
    $row = array_intersect_key($f, array_flip(['MODULE_ID', 'HEIGHT', 'WIDTH', 'FILE_SIZE', 'CONTENT_TYPE', 'SUBDIR', 'FILE_NAME', 'ORIGINAL_NAME', 'DESCRIPTION']));
    $row['bundled'] = strpos($f['SUBDIR'], 'landing/') === 0;
    if ($row['bundled']) {
        $src = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . $f['SUBDIR'] . '/' . $f['FILE_NAME'];
        $dst = $filesDir . '/' . $f['SUBDIR'] . '/' . $f['FILE_NAME'];
        if (!is_dir(dirname($dst))) {
            mkdir(dirname($dst), 0775, true);
        }
        if (!copy($src, $dst)) {
            fwrite(STDERR, "не скопирован $src\n");
            exit(1);
        }
    }
    $out['files'][$id] = $row;
}

file_put_contents(__DIR__ . '/data.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n");
printf("props: %s\nlinks: %s\ncards: %d\nseo: %s\nfiles: %d (в бандле %d)\n",
    implode(', ', array_keys($out['props'])),
    implode(', ', array_map(static fn($k, $v) => "$k=" . count($v), array_keys($out['links']), $out['links'])),
    count($out['cards']), implode(', ', array_keys($out['seo'])),
    count($out['files']), count(array_filter($out['files'], static fn($f) => $f['bundled'])));
