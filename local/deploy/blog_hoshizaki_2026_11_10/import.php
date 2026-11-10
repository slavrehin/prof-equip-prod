<?php
/**
 * Статья блога «Льдогенераторы Hoshizaki: от классического кубика до идеальной сферы»,
 * Б24 #9569 «статья Hoshizaki» (2026-11-10). Текст — «Hoshizaki_PROFEQUIP_article (28).docx» из чата задачи,
 * 8 фото — вложения задачи (PNG → JPEG, img/).
 *
 * Что делает:
 *  - копирует img/*.jpg в /upload/blog/hoshizaki-ldogeneratory/ (только недостающие);
 *  - создаёт элемент инфоблока blog: текст article.html (с интерактивным блоком «Ключевые преимущества»
 *    .blog-advantages — его CSS/JS лежат в шаблоне news.detail/blog-detail), обложка cover.jpg,
 *    «Наш ассортимент» (CATALOG_PRODUCT_1) — льдогенераторы Hoshizaki серии IM из каталога, по CODE;
 *  - SEO title/description.
 * Идемпотентен: статья с этим CODE уже есть → ничего не меняет; --update → перезаписать текст и свойства
 * (только если хотите затереть правки из админки).
 *
 * Запуск (локально, на dev и на проде, из корня сайта, от www-data):
 *   php local/deploy/blog_hoshizaki_2026_11_10/import.php [--dry] [--update]
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
$update = in_array('--update', $argv, true);
$ib = (int) \CIBlock::GetList([], ['CODE' => 'blog', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
if (!$ib) { echo "нет инфоблока blog\n"; exit(1); }
$code = 'ldogeneratory-hoshizaki-ot-kubika-do-sfery';
$name = 'Льдогенераторы Hoshizaki: от классического кубика до идеальной сферы';
$lead = 'Как форма льда влияет на напитки, какие технологии предлагает Hoshizaki и как выбрать оборудование '
    . 'для бара, ресторана или гостиницы.';
$activeFrom = '10.11.2026 00:00:00'; // без времени: дата «в будущем» даёт 404 на детальной

// 1. Картинки в /upload (не в git — исходники в img/)
$dstDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/blog/hoshizaki-ldogeneratory';
$copied = 0;
foreach (glob(__DIR__ . '/img/*.jpg') as $src) {
    if (basename($src) === 'cover.jpg') continue;
    $dst = $dstDir . '/' . basename($src);
    if (is_file($dst)) continue;
    if (!$dry) {
        if (!is_dir($dstDir)) mkdir($dstDir, 0775, true);
        copy($src, $dst) || exit("не скопировалось: $dst\n");
    }
    $copied++;
}
echo ($dry ? '[dry] ' : '') . "картинок в /upload: +$copied\n";

// 2. Товары «Наш ассортимент»: по CODE (ID на окружениях могут разойтись)
$productCodes = ['hoshizaki-im-21cpe', 'hoshizaki-im-30cpe', 'hoshizaki-im-30cpe-u', 'hoshizaki-im-45cpe',
    'hoshizaki-im-45cpe-f', 'hoshizaki-im-45cpe-e'];
$map = [];
$r = \CIBlockElement::GetList([], ['IBLOCK_ID' => 11, '=CODE' => $productCodes, 'ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N'],
    false, false, ['ID', 'CODE']);
while ($p = $r->Fetch()) $map[$p['CODE']] = (int) $p['ID'];
$productIds = [];
foreach ($productCodes as $c) if (isset($map[$c])) $productIds[] = $map[$c];
echo 'товаров найдено: ' . count($productIds) . ' из ' . count($productCodes) . "\n";

$text = file_get_contents(__DIR__ . '/article.html');
$props = [
    'CATALOG_PRODUCT_1' => $productIds ?: false,
    'TITLE_CATALOG_1' => 'Льдогенераторы Hoshizaki серии IM в каталоге',
];
$fields = [
    'NAME' => $name,
    'PREVIEW_TEXT_TYPE' => 'text', 'PREVIEW_TEXT' => $lead,
    'DETAIL_TEXT_TYPE' => 'html', 'DETAIL_TEXT' => $text,
    'IPROPERTY_TEMPLATES' => [
        'ELEMENT_META_TITLE' => 'Льдогенераторы Hoshizaki: виды льда, технологии и выбор оборудования — ПРОФЭКВИП',
        'ELEMENT_META_DESCRIPTION' => 'Кубиковый, крупный, сферический, серповидный и чешуйчатый лёд: обзор льдогенераторов '
            . 'Hoshizaki серий IM, IM-LM, IM-Q, KM, FM и DCM. Новинки IM CUBE, хладагент R290 и советы по выбору.',
        'ELEMENT_PREVIEW_PICTURE_FILE_ALT' => 'Стенд Hoshizaki на выставке HDE Shanghai',
        'ELEMENT_DETAIL_PICTURE_FILE_ALT' => 'Стенд Hoshizaki на выставке HDE Shanghai',
    ],
];

$be = new \CIBlockElement();
$e = \CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, '=CODE' => $code, 'CHECK_PERMISSIONS' => 'N'], false,
    ['nTopCount' => 1], ['ID'])->Fetch();
if ($e) {
    if (!$update) { echo "$code: уже есть #{$e['ID']} (--update — перезаписать текст)\n"; exit(0); }
    if ($dry) { echo "[dry] $code: будет обновлена #{$e['ID']}\n"; exit(0); }
    if (!$be->Update($e['ID'], $fields)) { echo "ошибка: {$be->LAST_ERROR}\n"; exit(1); }
    \CIBlockElement::SetPropertyValuesEx($e['ID'], $ib, $props);
    $id = (int) $e['ID'];
    echo "$code: обновлена #$id\n";
} else {
    if ($dry) { echo "[dry] $code: будет создана (инфоблок $ib)\n"; exit(0); }
    $cover = __DIR__ . '/img/cover.jpg';
    $id = $be->Add($fields + [
        'IBLOCK_ID' => $ib, 'CODE' => $code, 'ACTIVE' => 'Y', 'SORT' => 500, 'ACTIVE_FROM' => $activeFrom,
        'PREVIEW_PICTURE' => \CFile::MakeFileArray($cover), 'DETAIL_PICTURE' => \CFile::MakeFileArray($cover),
        'PROPERTY_VALUES' => $props,
    ]);
    if (!$id) { echo "$code: ошибка {$be->LAST_ERROR}\n"; exit(1); }
    echo "$code: создана #$id\n";
}
\CIBlock::clearIblockTagCache($ib);
BXClearCache(true);
