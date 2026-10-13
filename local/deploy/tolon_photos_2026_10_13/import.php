<?php
/**
 * TOLON: новые фото с tolon.com (2026-10-13) для карточек оборудования + новая карточка TWE 18.
 *
 * - Карточкам из $PHOTOS (поиск по CODE) ставится новое основное фото (PREVIEW/DETAIL) и галерея
 *   (свойство GALLERY целиком заменяется). Старые ID картинок пишутся в rollback-файл (--log=).
 * - TWE 18 «в наличии» (CODE professionalnaya-stiralnaya-mashina-tolon-twe-18-zagruzka-18-kg,
 *   модель предыдущего поколения) НЕ меняется. Рядом создаётся обычная карточка $NEW_CODE:
 *   копия текстов/свойств, без STOCK_BADGE, без раздела «в наличии», без цены, без строк
 *   «Наличие/Примечание/Цена» в анонсе; фото — новые.
 * - Модели, которых нет на tolon.com (TBW16–70, TTD20, TFI3218/6015/6020/6025/8030, TI5), не трогаются.
 *
 * Запуск (test3, в контейнере):
 *   php local/deploy/tolon_photos_2026_10_13/import.php --photos=/tmp/tolon_site --log=/tmp/tolon_rollback.json [--dry]
 * Прод: SERVER_NAME=prof-equip.ru php ... (файлы → upload/iblock).
 * Идемпотентен: если у карточки основное фото уже из этого набора (ORIGINAL_NAME) — пропуск.
 * Картинки ставятся прямым SQL (см. «Известные грабли» в CLAUDE.md).
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'test3.prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\CModule::IncludeModule('iblock');
\CModule::IncludeModule('catalog');
global $DB;

$opts = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) { $opts[$m[1]] = $m[2] ?? true; }
}
$dry = isset($opts['dry']);
$photosDir = rtrim($opts['photos'] ?? '/tmp/tolon_site', '/');
$logFile = $opts['log'] ?? null;
if (!$dry && !$logFile) { throw new RuntimeException('Укажите --log=путь.json для отката'); }

const IBLOCK_ID = 11;
$isTest3 = !is_writable($_SERVER['DOCUMENT_ROOT'] . '/upload/iblock');
$uploadSubdir = $isTest3 ? 'import_test' : 'iblock';

$SRC_TWE18 = 'professionalnaya-stiralnaya-mashina-tolon-twe-18-zagruzka-18-kg';
$NEW_CODE = 'professionalnaya-stiralnaya-mashina-tolon-twe-18';
$NEW_NAME = 'Профессиональная стиральная машина TOLON TWE 18, загрузка 18 кг';
$NEW_SECTION = 'vysokoskorostnye-stiralnye-mashiny';

$TWE = ['twe-10-40-main', 'twe-10-40-panel', 'twe-10-40-dozator', 'twe-10-40-baraban'];
$TTD = ['ttd-main', 'ttd-zagruzka', 'ttd-vygruzka', 'ttd-panel'];
$TFI60 = ['tfi60-main', 'tfi60-panel', 'tfi60-lenty', 'tfi60-podacha', 'tfi60-ventilyator'];
// CODE → [фото: 1-е основное, остальные в галерею] (файлы tolon-<имя>.jpg)
$PHOTOS = [
    'professionalnaya-stiralnaya-mashina-tolon-twe-10-zagruzka-10-kg' => $TWE,
    'professionalnaya-stiralnaya-mashina-tolon-twe-15-zagruzka-15-kg' => $TWE,
    $NEW_CODE => $TWE,
    'professionalnaya-stiralnaya-mashina-tolon-twe-24-zagruzka-24-kg' => $TWE,
    'professionalnaya-stiralnaya-mashina-tolon-twe-28-zagruzka-28-kg' => $TWE,
    'professionalnaya-stiralnaya-mashina-tolon-twe-40-zagruzka-40-kg' => $TWE,
    'professionalnaya-stiralnaya-mashina-tolon-twe-60-zagruzka-60-kg' => ['twe60-main', 'twe60-panel', 'twe60-privod'],
    'professionalnaya-stiralnaya-mashina-tolon-twe-110-zagruzka-110-kg' => ['twe110-main', 'twe110-panel', 'twe110-privod', 'twe110-dozator'],
    'professionalnaya-sushilnaya-mashina-tolon-trd11-zagruzka-10-5-kg-12-kg' => ['trd11-16-main'],
    'professionalnaya-sushilnaya-mashina-tolon-trd16-zagruzka-16-5-kg-18-kg' => ['trd11-16-main'],
    'professionalnaya-sushilnaya-mashina-tolon-trd23' => ['trd23-33-main'],
    'professionalnaya-sushilnaya-mashina-tolon-trd28-zagruzka-27-5-kg-31-kg' => ['trd23-33-main'],
    'professionalnaya-sushilnaya-mashina-tolon-trd33-zagruzka-33-kg-37-kg' => ['trd23-33-main'],
    'professionalnaya-sushilnaya-mashina-tolon-trds11-zagruzka-21-kg-24-kg' => ['trds-main'],
    'professionalnaya-sushilnaya-mashina-tolon-trds16-zagruzka-33-kg-36-kg' => ['trds-main'],
    'professionalnaya-sushilnaya-mashina-tolon-ttd28' => $TTD,
    'professionalnaya-sushilnaya-mashina-tolon-ttd40-zagruzka-40-kg' => $TTD,
    'professionalnaya-sushilnaya-mashina-tolon-ttd60-zagruzka-60-kg' => $TTD,
    'professionalnaya-sushilnaya-mashina-tolon-ttd110-zagruzka-110-kg' => $TTD,
    'professionalnyj-gladilnyj-kalandr-tolon-tfi3516' => ['tfi35-main'],
    'professionalnyj-gladilnyj-kalandr-tolon-tfi3521' => ['tfi35-main'],
    'professionalnyj-gladilnyj-kalandr-tolon-tfi6026-dlina-vala-2600-mm' => $TFI60,
    'professionalnyj-gladilnyj-kalandr-tolon-tfi6032-dlina-vala-3200-mm' => $TFI60,
    'professionalnyj-skladyvatel-polotenets-s-funktsiej-shtabelirovaniya-tolon-ttf200' => ['ttf200-main', 'ttf200-vid2', 'ttf200-vid3'],
];
$ALL_NAMES = array_unique(array_merge(...array_values($PHOTOS)));
foreach ($ALL_NAMES as $n) {
    if (!is_file("$photosDir/tolon-$n.jpg")) { throw new RuntimeException("Нет файла $photosDir/tolon-$n.jpg"); }
}

$props = [];
$rs = \CIBlockProperty::GetList([], ['IBLOCK_ID' => IBLOCK_ID]);
while ($p = $rs->Fetch()) { $props[$p['CODE']] = $p; }
$galleryId = (int) $props['GALLERY']['ID'];

function findByCode(string $code): ?array
{
    $r = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $code], false, false,
        ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'])->Fetch();
    return $r ?: null;
}

$log = ['date' => date('c'), 'env' => $_SERVER['SERVER_NAME'], 'created' => null, 'photos' => []];

// --- 1. Новая карточка TWE 18 --------------------------------------------------------------
$src = findByCode($SRC_TWE18);
if (!$src) { throw new RuntimeException("Нет исходной карточки $SRC_TWE18"); }
if (findByCode($NEW_CODE)) {
    echo "TWE 18 (новая) уже есть — пропуск создания\n";
} elseif ($dry) {
    echo "[dry] создать $NEW_CODE (копия ID={$src['ID']})\n";
} else {
    $full = $DB->Query("SELECT * FROM b_iblock_element WHERE ID=" . (int) $src['ID'])->Fetch();
    // анонс без строк про наличие/предыдущее поколение/цену складского экземпляра
    $preview = preg_replace('#\s*<li[^>]*>(?:(?!</li>).)*?(Наличие:|Примечание:|Цена:)(?:(?!</li>).)*</li>#su', '', $full['PREVIEW_TEXT']);
    if (preg_match('/наличи|поколени|999/ui', $preview)) { throw new RuntimeException('Анонс TWE 18 не очистился'); }
    $section = \CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $NEW_SECTION], false, ['ID'])->Fetch();
    if (!$section) { throw new RuntimeException("Нет раздела $NEW_SECTION"); }

    $values = [];
    $rp = \CIBlockElement::GetProperty(IBLOCK_ID, $src['ID'], [], []);
    while ($p = $rp->Fetch()) {
        if (in_array($p['CODE'], ['STOCK_BADGE', 'GALLERY'], true) || $p['VALUE'] === null || $p['VALUE'] === '' || $p['VALUE'] === false) { continue; }
        $v = $p['PROPERTY_TYPE'] === 'L' ? (int) $p['VALUE'] : $p['VALUE'];
        if ($p['DESCRIPTION'] !== null && $p['DESCRIPTION'] !== '') { $v = ['VALUE' => $v, 'DESCRIPTION' => $p['DESCRIPTION']]; }
        $values[$p['CODE']][] = $v;
    }
    $el = new \CIBlockElement();
    $newId = (int) $el->Add([
        'IBLOCK_ID' => IBLOCK_ID,
        'IBLOCK_SECTION_ID' => (int) $section['ID'],
        'NAME' => $NEW_NAME,
        'CODE' => $NEW_CODE,
        'ACTIVE' => 'Y',
        'SORT' => 500,
        'PREVIEW_TEXT' => $preview,
        'PREVIEW_TEXT_TYPE' => $full['PREVIEW_TEXT_TYPE'],
        'DETAIL_TEXT' => $full['DETAIL_TEXT'],
        'DETAIL_TEXT_TYPE' => $full['DETAIL_TEXT_TYPE'],
        'PROPERTY_VALUES' => $values,
    ]);
    if (!$newId) { throw new RuntimeException('Add TWE 18: ' . $el->LAST_ERROR); }
    // товар каталога без цены («Запросить стоимость»), как у остальных TWE
    if (!\CCatalogProduct::GetByID($newId)) {
        \CCatalogProduct::Add(['ID' => $newId, 'QUANTITY' => 0, 'MEASURE' => 796]);
    }
    $tpl = new \Bitrix\Iblock\InheritedProperty\ElementTemplates(IBLOCK_ID, $newId);
    $tpl->set([
        'ELEMENT_META_TITLE' => "$NEW_NAME купить в ПРОФЭКВИП",
        'ELEMENT_META_DESCRIPTION' => "Купить $NEW_NAME по выгодной цене в компании Профэквип. Купить с доставкой по России. Заказывайте на сайте или по телефону.",
    ]);
    (new \Bitrix\Iblock\InheritedProperty\ElementValues(IBLOCK_ID, $newId))->clearValues();
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(IBLOCK_ID, $newId);
    $log['created'] = $newId;
    echo "Создана карточка TWE 18: ID=$newId ($NEW_CODE), свойств: " . count($values) . "\n";
}

// --- 2. Фото ---------------------------------------------------------------------------------
$saved = []; // имя → file ID (один файл на имя, общий для карточек с одинаковым фото — как было и раньше)
$saveImage = static function (string $name, string $alt) use (&$saved, $photosDir, $uploadSubdir): int {
    if (isset($saved[$name])) { return $saved[$name]; }
    $arr = \CFile::MakeFileArray("$photosDir/tolon-$name.jpg");
    $arr['name'] = "tolon-$name.jpg";
    $arr['description'] = $alt;
    $arr['MODULE_ID'] = 'iblock';
    $id = (int) \CFile::SaveFile($arr, $uploadSubdir);
    if (!$id) { throw new RuntimeException("Файл tolon-$name.jpg не сохранён"); }
    return $saved[$name] = $id;
};

foreach ($PHOTOS as $code => $names) {
    $e = findByCode($code);
    if (!$e) {
        if ($dry && $code === $NEW_CODE) { echo "[dry] $code: будет создана\n"; continue; }
        throw new RuntimeException("Нет карточки $code");
    }
    $id = (int) $e['ID'];
    if ($code === $SRC_TWE18) { throw new RuntimeException('TWE 18 «в наличии» не трогаем'); }
    $cur = $e['DETAIL_PICTURE'] ? \CFile::GetByID($e['DETAIL_PICTURE'])->Fetch() : null;
    if ($cur && $cur['ORIGINAL_NAME'] === "tolon-{$names[0]}.jpg") { echo "$id {$e['NAME']}: уже обновлена — пропуск\n"; continue; }
    if ($dry) { echo "[dry] $id {$e['NAME']}: " . implode(', ', $names) . "\n"; continue; }

    $oldGallery = [];
    $rg = $DB->Query("SELECT VALUE_NUM FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID=$galleryId ORDER BY ID");
    while ($g = $rg->Fetch()) { $oldGallery[] = (int) $g['VALUE_NUM']; }
    $log['photos'][$id] = ['code' => $code, 'preview' => (int) $e['PREVIEW_PICTURE'], 'detail' => (int) $e['DETAIL_PICTURE'], 'gallery' => $oldGallery];

    $fileIds = [];
    foreach ($names as $name) {
        // описание файла пустое: файл общий для серии, alt шаблон берёт из названия товара
        $fileIds[] = $saveImage($name, '');
    }
    $main = array_shift($fileIds);
    $DB->Query("UPDATE b_iblock_element SET PREVIEW_PICTURE=$main, DETAIL_PICTURE=$main, TIMESTAMP_X=NOW() WHERE ID=$id");
    $DB->Query("DELETE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID=$galleryId");
    foreach ($fileIds as $fid) {
        $DB->Query("INSERT INTO b_iblock_element_property (IBLOCK_PROPERTY_ID, IBLOCK_ELEMENT_ID, VALUE, VALUE_TYPE, VALUE_NUM) VALUES ($galleryId, $id, '$fid', 'text', $fid)");
    }
    echo "$id {$e['NAME']}: основное file $main, галерея " . count($fileIds) . "\n";
    if ($logFile) { file_put_contents($logFile, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); }
}

if (!$dry) {
    if ($logFile) { file_put_contents($logFile, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); }
    \CIBlock::clearIblockTagCache(IBLOCK_ID);
    foreach (['cache', 'managed_cache', 'stack_cache'] as $d) {
        foreach (glob($_SERVER['DOCUMENT_ROOT'] . "/bitrix/$d/*") ?: [] as $f) { \Bitrix\Main\IO\Directory::deleteDirectory($f); }
    }
    echo "Кэш очищен. Откат: $logFile\n";
}
