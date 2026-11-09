<?php
/**
 * Одиночные страницы и блоки главной (data/pages.json, собирается archive/extract/extract_pages.py):
 * контакты, первый экран, направления, преимущества, «О компании», «Партнёрам». Перезаписывает значения.
 * Запуск: docker compose exec -T web php /var/www/html/local/bootstrap/import_pages.php
 */
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('iblock');

$d = json_decode(file_get_contents(__DIR__ . '/data/pages.json'), true);
$ib = fn($c) => (int) CIBlock::GetList([], ['CODE' => $c, 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$html = fn($t) => ['VALUE' => ['TYPE' => 'HTML', 'TEXT' => $t]];

function put($ibId, $code, $name, array $fields = [], array $props = [], $sort = 500)
{
    $el = new CIBlockElement();
    $row = CIBlockElement::GetList([], ['IBLOCK_ID' => $ibId, '=CODE' => $code, 'CHECK_PERMISSIONS' => 'N'], false, false, ['ID'])->Fetch();
    $f = $fields + ['IBLOCK_ID' => $ibId, 'CODE' => $code, 'NAME' => $name, 'ACTIVE' => 'Y', 'SORT' => $sort];
    if ($row) {
        $el->Update($row['ID'], $f);
        $id = (int) $row['ID'];
    } else {
        $id = (int) $el->Add($f);
        if (!$id) throw new RuntimeException("$code: " . $el->LAST_ERROR);
    }
    if ($props) CIBlockElement::SetPropertyValuesEx($id, $ibId, $props);
    echo "  $code -> $id\n";
    return $id;
}

// контакты: первый — основной (MAIN = «Да»), он же элемент main, созданный bootstrap.php
$cid = $ib('contacts');
$main = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $cid, 'CODE' => 'MAIN'])->Fetch()['ID'];
foreach ($d['contacts'] as $i => $c) {
    put($cid, $i === 0 ? 'main' : 'office-' . $i, $c['name'], [], [
        'MAIN' => $i === 0 ? $main : false, 'PHONE' => $c['phones'][0] ?? '', 'EMAIL' => $c['emails'][0] ?? '',
        'ADDRESS' => $html($c['address']), 'TG' => $i === 0 ? 'https://t.me/prof_equip' : '',
    ], ($i + 1) * 100);
}

$h = $d['herohome'];
put($ib('herohome'), 'herohome', 'Первый экран', [], [
    'HERO_TITLE' => $h['title'], 'HERO_BULLET_TEXTS' => $h['bullets'], 'ABOUT' => $html($h['about']),
]);

foreach ($d['directionshome'] as $i => $c) {
    put($ib('directionshome'), trim($c['link'], '/') ?: 'dir-' . $i, $c['name'],
        ['PREVIEW_TEXT' => $c['text'], 'PREVIEW_TEXT_TYPE' => 'text'],
        ['LINK' => $c['link'], 'LINK_TEXT' => $c['link_text']], ($i + 1) * 100);
}
foreach ($d['advantages'] as $i => $c) {
    put($ib('advantages'), 'adv-' . ($i + 1), $c['name'], ['PREVIEW_TEXT' => $c['text'], 'PREVIEW_TEXT_TYPE' => 'html'], [], ($i + 1) * 100);
}

$blocks = [];
foreach ($d['aboutpage'] as $i => $b) $blocks['n' . $i] = ['VALUE' => ['TYPE' => 'HTML', 'TEXT' => $b['html']], 'DESCRIPTION' => $b['title']];
put($ib('aboutpage'), 'about', 'О компании', [], ['TEXT_BLOCKS' => $blocks]);

$tabs = [];
foreach ($d['partneram']['tabs'] as $i => $t) $tabs['n' . $i] = ['VALUE' => ['TYPE' => 'HTML', 'TEXT' => $t['html']], 'DESCRIPTION' => $t['title']];
put($ib('partneram'), 'partneram', 'Партнерам',
    ['PREVIEW_TEXT' => $d['partneram']['preview'], 'PREVIEW_TEXT_TYPE' => 'html', 'DETAIL_TEXT' => $d['partneram']['preview'], 'DETAIL_TEXT_TYPE' => 'html'],
    ['TABS' => $tabs]);
echo "ГОТОВО\n";
