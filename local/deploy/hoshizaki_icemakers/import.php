<?php
/**
 * Импорт 17 льдогенераторов Hoshizaki IM в раздел «Барное оборудование → Льдогенераторы».
 * Данные — products.json рядом с файлом. Свойства и раздел создаёт миграция
 * local/migrations/2026-09-21-ice-makers-section-props.php (запускать её первой).
 *
 * Запуск (в контейнере test3):
 *   php local/deploy/hoshizaki_icemakers/import.php --photos=/tmp/hoshizaki_photos [--only=IM-21CPE] [--dry]
 *
 * Цена пишется в БАЗОВУЮ цену в EUR как есть (без НДС, без своей наценки): в рубли её
 * переводит штатный модуль валют, курс = ЦБ РФ × PROFEQUIP_CURRENCY_MARKUP (1.05),
 * см. profequip_UpdateCurrencyRatesFromCBR().
 *
 * Картинки: на test3 /upload/iblock read-only, поэтому там файлы сохраняются в upload/import_test
 * (на проде — в upload/iblock), а PREVIEW_PICTURE/DETAIL_PICTURE/GALLERY проставляются прямым SQL (см. CLAUDE.md).
 * Идемпотентен: товар ищется по CODE, у существующего обновляется всё, кроме дублей файлов.
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
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $opts[$m[1]] = $m[2] ?? true; }
}
$dry = isset($opts['dry']);
$only = $opts['only'] ?? null;
$photosDir = rtrim($opts['photos'] ?? '/tmp/hoshizaki_photos', '/');

const IBLOCK_ID = 11;
const BRANDS_IBLOCK_ID = 14;

$data = json_decode(file_get_contents(__DIR__ . '/products.json'), true);
$products = $data['products'];

// Особые имена фото: у 21CPE и 45CPE-U вторая картинка названа без слова «лед»
$iceOverride = ['IM-21CPE' => 'IM-21CPE 2.png', 'IM-45CPE-U' => 'IM-45CPE-U (2).png'];

// название характеристики => CODE свойства
$propMap = [
    'Бренд' => 'PROIZVODITEL',
    'Модель / артикул' => 'MODEL_ARTIKUL',
    'Серия' => 'SERIYA_LEDOGENERATORA',
    'Тип льда' => 'TIP_LDA',
    'Фракция льда' => 'FRAKTSIYA_LDA',
    'Тип охлаждения' => 'TIP_OHLAZHDENIYA',
    'Производительность, кг/24 ч' => 'PROIZVODITELNOST_KG_24_CH',
    'Ёмкость бункера, кг' => 'EMKOST_BUNKERA_KG',
    'Габариты Ш×Г×В, мм' => 'GABARITY_SH_G_V_MM',
    'Ножки' => 'NOZHKI',
    'Электропитание' => 'ELEKTROPITANIE',
    'Потребляемая мощность, кВт' => 'POTREBLYAEMAYA_MOSHHNOST_KVT',
    'Хладагент' => 'HLADAGENT',
    'УФ-обеззараживание' => 'UF_OBEZZARAZHIVANIE',
    'Встроенный дренажный насос' => 'VSTROENNYJ_DRENAZHNYJ_NASOS',
    'Материал внешнего корпуса' => 'MATERIAL_VNESHNEGO_KORPUSA',
    'Вес нетто, кг' => 'VES_NETTO_KG',
    'Вес брутто (в упаковке), кг' => 'VES_BRUTTO_KG',
];

$props = [];
$rs = \CIBlockProperty::GetList([], ['IBLOCK_ID' => IBLOCK_ID]);
while ($p = $rs->Fetch()) { $props[$p['CODE']] = (int) $p['ID']; }
foreach ($propMap as $name => $code) {
    if (empty($props[$code])) { throw new RuntimeException("Нет свойства $code (запустите миграцию)."); }
}
if (empty($props['GALLERY'])) { throw new RuntimeException('Нет свойства GALLERY.'); }

function enumId(int $propId, string $value): int
{
    $e = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propId, 'VALUE' => $value])->Fetch();
    if ($e) { return (int) $e['ID']; }
    $id = \CIBlockPropertyEnum::Add(['PROPERTY_ID' => $propId, 'VALUE' => $value, 'DEF' => 'N']);
    if (!$id) { throw new RuntimeException("Не создано значение «$value»"); }
    return (int) $id;
}

function saveImage(string $path, string $niceName): int
{
    if (!is_file($path)) { throw new RuntimeException("Нет файла $path"); }
    $arr = \CFile::MakeFileArray($path);
    $arr['name'] = $niceName;
    $arr['MODULE_ID'] = 'iblock';
    // на проде upload/iblock писабельный, на test3 — read-only (тогда upload/import_test)
    $dir = is_writable($_SERVER['DOCUMENT_ROOT'] . '/upload/iblock') ? 'iblock' : 'import_test';
    $id = \CFile::SaveFile($arr, $dir);
    if (!$id) { throw new RuntimeException("Файл $path не сохранён"); }
    return (int) $id;
}

$section = \CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => 'ldogeneratory'], false, ['ID', 'DESCRIPTION', 'PICTURE'])->Fetch();
if (!$section) { throw new RuntimeException('Нет раздела ldogeneratory (запустите миграцию).'); }
$sectionId = (int) $section['ID'];

$brand = \CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK_ID, 'NAME' => 'Hoshizaki'], false, false, ['ID'])->Fetch();
if (!$brand) { throw new RuntimeException('Нет бренда Hoshizaki в инфоблоке брендов.'); }

// --- раздел: описание, SEO, картинка (только если ещё не заполнено) --------
if (!$dry) {
    if (trim($section['DESCRIPTION']) === '') {
        $DB->Query("UPDATE b_iblock_section SET DESCRIPTION='" . $DB->ForSql(file_get_contents(__DIR__ . '/section_description.html')) . "', DESCRIPTION_TYPE='html', TIMESTAMP_X=NOW() WHERE ID=$sectionId");
        echo "Раздел: описание записано\n";
    }
    $tpl = new \Bitrix\Iblock\InheritedProperty\SectionTemplates(IBLOCK_ID, $sectionId);
    $tpl->set([
        'SECTION_META_TITLE' => 'Льдогенераторы для баров, ресторанов и кафе — купить в ПРОФЭКВИП',
        'SECTION_META_DESCRIPTION' => 'Профессиональные льдогенераторы для баров, ресторанов, кафе и гостиниц: разная производительность и размер кубика, воздушное и водяное охлаждение. Подбор и заказ в ПРОФЭКВИП.',
    ]);
    (new \Bitrix\Iblock\InheritedProperty\SectionValues(IBLOCK_ID, $sectionId))->clearValues();
    echo "Раздел: SEO-шаблоны записаны\n";
    if (!$section['PICTURE']) {
        $fid = saveImage("$photosDir/IM-21CPE.png", 'ldogeneratory.png');
        $DB->Query("UPDATE b_iblock_section SET PICTURE=$fid, TIMESTAMP_X=NOW() WHERE ID=$sectionId");
        echo "Раздел: картинка (file $fid)\n";
    }
}

$idx = 0;
$report = [];
foreach ($products as $p) {
    $idx++;
    $sku = $p['sku'];
    if ($only && $only !== $sku) { continue; }

    $mainFile = "$photosDir/$sku.png";
    $iceFile = "$photosDir/" . ($iceOverride[$sku] ?? "$sku лед.png");
    foreach ([$mainFile, $iceFile] as $f) {
        if (!is_file($f)) { throw new RuntimeException("$sku: нет фото $f"); }
    }
    if ($dry) { echo "[dry] $sku → $mainFile + $iceFile, {$p['price_eur_excl_vat']} EUR\n"; continue; }

    $fields = [
        'IBLOCK_ID' => IBLOCK_ID,
        'IBLOCK_SECTION_ID' => $sectionId,
        'NAME' => $p['title'],
        'CODE' => $p['slug'],
        'ACTIVE' => 'Y',
        'SORT' => 500 + $idx,
        'PREVIEW_TEXT' => $p['short_description'],
        'PREVIEW_TEXT_TYPE' => 'html',
        'DETAIL_TEXT' => $p['description_html'],
        'DETAIL_TEXT_TYPE' => 'html',
    ];
    $el = new \CIBlockElement();
    $existing = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $p['slug']], false, false, ['ID', 'DETAIL_PICTURE'])->Fetch();
    if ($existing) {
        $id = (int) $existing['ID'];
        if (!$el->Update($id, $fields)) { throw new RuntimeException("$sku: update: " . $el->LAST_ERROR); }
        $status = 'updated';
    } else {
        $fields['PROPERTY_VALUES'] = [];
        $id = (int) $el->Add($fields);
        if (!$id) { throw new RuntimeException("$sku: add: " . $el->LAST_ERROR); }
        $status = 'created';
    }

    // каталог + цена (EUR, как в прайсе)
    \CCatalogProduct::Add(['ID' => $id, 'QUANTITY' => 0, 'MEASURE' => 796]);
    $priceFields = ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => 1, 'PRICE' => $p['price_eur_excl_vat'], 'CURRENCY' => 'EUR'];
    $pr = \CPrice::GetList([], ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => 1])->Fetch();
    if ($pr) { \CPrice::Update((int) $pr['ID'], $priceFields); } else { \CPrice::Add($priceFields); }

    // характеристики
    $values = ['BRAND' => [(int) $brand['ID']]];
    foreach ($p['characteristics'] as $c) {
        if (!isset($propMap[$c['name']])) { throw new RuntimeException("$sku: неизвестная характеристика {$c['name']}"); }
        $code = $propMap[$c['name']];
        $values[$code] = enumId($props[$code], $c['value']);
    }
    \CIBlockElement::SetPropertyValuesEx($id, IBLOCK_ID, $values);

    // картинки — только если ещё нет (повторный запуск не плодит файлы)
    if (!$existing || !$existing['DETAIL_PICTURE']) {
        $mainId = saveImage($mainFile, $p['slug'] . '.png');
        $iceId = saveImage($iceFile, $p['slug'] . '-2.png');
        $DB->Query("UPDATE b_iblock_element SET PREVIEW_PICTURE=$mainId, DETAIL_PICTURE=$mainId, TIMESTAMP_X=NOW() WHERE ID=$id");
        $DB->Query("DELETE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID={$props['GALLERY']}");
        $DB->Query("INSERT INTO b_iblock_element_property (IBLOCK_PROPERTY_ID, IBLOCK_ELEMENT_ID, VALUE, VALUE_TYPE, VALUE_NUM) VALUES ({$props['GALLERY']}, $id, '$iceId', 'text', $iceId)");
    }

    // SEO (штатный API — сам сбрасывает кэш значений)
    $tpl = new \Bitrix\Iblock\InheritedProperty\ElementTemplates(IBLOCK_ID, $id);
    $tpl->set([
        'ELEMENT_META_TITLE' => $p['seo']['title'],
        'ELEMENT_META_DESCRIPTION' => $p['seo']['meta_description'],
    ]);
    (new \Bitrix\Iblock\InheritedProperty\ElementValues(IBLOCK_ID, $id))->clearValues();

    echo "$status $sku → ID=$id\n";
}

if (!$dry) {
    foreach (['cache', 'managed_cache', 'stack_cache'] as $d) {
        $dir = $_SERVER['DOCUMENT_ROOT'] . "/bitrix/$d";
        foreach (glob("$dir/*") ?: [] as $f) { \Bitrix\Main\IO\Directory::deleteDirectory($f); }
    }
    echo "Кэш очищен\n";
}
