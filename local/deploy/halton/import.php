<?php
/**
 * Halton: 8 товаров (KCJ → «Вентилируемые потолки», 7 зонтов → «Вытяжные зонты»; карточка KVL
 * собрана вручную — docx не было, источники в products.json), фасеты фильтра VENT_*,
 * тексты/SEO/картинка разделов, текст и SEO страницы бренда Halton.
 * Данные — products.json (собирается build_data.py из docx владельца), картинки — папка --photos.
 * Разделы, свойство SPECS и значение Halton у PROIZVODITEL создаёт миграция
 * local/migrations/2026-09-29-halton-sections-props.php (запускать её первой).
 *
 * Запуск (в контейнере test3):
 *   php local/deploy/halton/import.php --photos=/tmp/halton_photos [--only=KCJ] [--dry] [--force-texts]
 *
 * Цены нет в источниках — не создаётся (карточка показывает «Запросить стоимость»).
 * Картинки: файл сохраняется штатно (CFile::SaveFile, описание файла = alt), ID ставится
 * элементу прямым SQL (см. «Известные грабли» в CLAUDE.md). На test3 — upload/import_test,
 * на проде — upload/iblock.
 * Идемпотентен: товар ищется по CODE; картинки загружаются, только если у товара их ещё нет;
 * тексты раздела/бренда пишутся, только если пусты (или с --force-texts).
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
$forceTexts = isset($opts['force-texts']);
$only = $opts['only'] ?? null;
$photosDir = rtrim($opts['photos'] ?? '/tmp/halton_photos', '/');

const IBLOCK_ID = 11;
const BRANDS_IBLOCK_ID = 14;

$isTest3 = is_link($_SERVER['DOCUMENT_ROOT'] . '/upload/iblock') || !is_writable($_SERVER['DOCUMENT_ROOT'] . '/upload/iblock');
$uploadSubdir = $isTest3 ? 'import_test' : 'iblock';

$data = json_decode(file_get_contents(__DIR__ . '/products.json'), true, 512, JSON_THROW_ON_ERROR);

$props = [];
$rs = \CIBlockProperty::GetList([], ['IBLOCK_ID' => IBLOCK_ID]);
while ($p = $rs->Fetch()) { $props[$p['CODE']] = (int) $p['ID']; }
foreach (['SPECS', 'GALLERY', 'BRAND', 'PROIZVODITEL'] as $code) {
    if (empty($props[$code])) { throw new RuntimeException("Нет свойства $code (запустите миграцию)."); }
}
$facetProps = array_filter($props, static fn($code) => strpos($code, 'VENT_') === 0, ARRAY_FILTER_USE_KEY);
if (count($facetProps) !== 6) { throw new RuntimeException('Нет свойств VENT_* (запустите миграцию 2026-09-29-halton-filter-props.php).'); }
$enum = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $props['PROIZVODITEL'], 'VALUE' => 'Halton'])->Fetch();
if (!$enum) { throw new RuntimeException('Нет значения Halton у PROIZVODITEL (запустите миграцию).'); }

$brand = \CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK_ID, 'CODE' => $data['brand']['code']], false, false, ['ID', 'PREVIEW_TEXT'])->Fetch();
if (!$brand) { throw new RuntimeException('Нет бренда Halton в инфоблоке брендов.'); }
$brandId = (int) $brand['ID'];

function saveImage(string $path, string $alt, string $subdir): int
{
    if (!is_file($path)) { throw new RuntimeException("Нет файла $path"); }
    $arr = \CFile::MakeFileArray($path);
    $arr['name'] = basename($path);
    $arr['description'] = $alt;
    $arr['MODULE_ID'] = 'iblock';
    $id = \CFile::SaveFile($arr, $subdir);
    if (!$id) { throw new RuntimeException("Файл $path не сохранён"); }
    return (int) $id;
}

function clearCache(): void
{
    foreach (['cache', 'managed_cache', 'stack_cache'] as $d) {
        foreach (glob($_SERVER['DOCUMENT_ROOT'] . "/bitrix/$d/*") ?: [] as $f) { \Bitrix\Main\IO\Directory::deleteDirectory($f); }
    }
}

// --- разделы ----------------------------------------------------------------
$sectionIds = [];
foreach ($data['sections'] as $code => $s) {
    $section = \CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $code], false, ['ID', 'DESCRIPTION', 'PICTURE'])->Fetch();
    if (!$section) { throw new RuntimeException("Нет раздела $code (запустите миграцию)."); }
    $sid = $sectionIds[$code] = (int) $section['ID'];
    if ($dry) { echo "[dry] раздел $code (ID=$sid)\n"; continue; }
    if ($forceTexts || trim((string) $section['DESCRIPTION']) === '') {
        $DB->Query("UPDATE b_iblock_section SET DESCRIPTION='" . $DB->ForSql($s['description_html']) . "', DESCRIPTION_TYPE='html', TIMESTAMP_X=NOW() WHERE ID=$sid");
        echo "Раздел $code: описание записано\n";
    }
    $tpl = new \Bitrix\Iblock\InheritedProperty\SectionTemplates(IBLOCK_ID, $sid);
    $tpl->set(['SECTION_META_TITLE' => $s['meta_title'], 'SECTION_META_DESCRIPTION' => $s['meta_description']]);
    (new \Bitrix\Iblock\InheritedProperty\SectionValues(IBLOCK_ID, $sid))->clearValues();
    if ($s['picture'] && !$section['PICTURE']) {
        $fid = saveImage("$photosDir/{$s['picture']}", $s['meta_title'], $uploadSubdir);
        $DB->Query("UPDATE b_iblock_section SET PICTURE=$fid, TIMESTAMP_X=NOW() WHERE ID=$sid");
        echo "Раздел $code: картинка (file $fid)\n";
    }
}

// --- товары -------------------------------------------------------------------
foreach ($data['products'] as $p) {
    if ($only && $only !== $p['folder']) { continue; }
    foreach ($p['images'] as $img) {
        if (!is_file("$photosDir/{$img['file']}")) { throw new RuntimeException("{$p['folder']}: нет фото {$img['file']}"); }
    }
    if ($dry) { echo "[dry] {$p['folder']} → {$p['slug']} ({$p['section']}), " . count($p['specs']) . ' хар., ' . count($p['images']) . " фото\n"; continue; }

    $fields = [
        'IBLOCK_ID' => IBLOCK_ID,
        'IBLOCK_SECTION_ID' => $sectionIds[$p['section']],
        'NAME' => $p['name'],
        'CODE' => $p['slug'],
        'ACTIVE' => $p['active'] ? 'Y' : 'N',
        'SORT' => $p['sort'],
        'PREVIEW_TEXT' => $p['preview_text'],
        'PREVIEW_TEXT_TYPE' => 'text',
        'DETAIL_TEXT' => $p['description_html'],
        'DETAIL_TEXT_TYPE' => 'html',
    ];
    $el = new \CIBlockElement();
    $existing = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $p['slug']], false, false, ['ID', 'DETAIL_PICTURE'])->Fetch();
    if ($existing) {
        $id = (int) $existing['ID'];
        if (!$el->Update($id, $fields)) { throw new RuntimeException("{$p['folder']}: update: " . $el->LAST_ERROR); }
        $status = 'updated';
    } else {
        $fields['PROPERTY_VALUES'] = [];
        $id = (int) $el->Add($fields);
        if (!$id) { throw new RuntimeException("{$p['folder']}: add: " . $el->LAST_ERROR); }
        $status = 'created';
    }

    // товар каталога без цены («Запросить стоимость»)
    if (!\CCatalogProduct::GetByID($id)) {
        \CCatalogProduct::Add(['ID' => $id, 'QUANTITY' => 0, 'MEASURE' => 796]);
    }

    $specs = [];
    foreach ($p['specs'] as $s) {
        $specs[] = ['VALUE' => $s['value'], 'DESCRIPTION' => $s['name']];
    }
    $values = [
        'BRAND' => [$brandId],
        'PROIZVODITEL' => (int) $enum['ID'],
        'SPECS' => $specs ?: false,
    ];
    // фасеты фильтра: все VENT_* (нет значения у товара — свойство очищается)
    foreach ($facetProps as $code => $propId) {
        $ids = [];
        foreach ($p['facets'][$code] ?? [] as $value) {
            $e = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propId, 'VALUE' => $value])->Fetch();
            if (!$e) { throw new RuntimeException("{$p['folder']}: у $code нет значения «{$value}» (миграция?)"); }
            $ids[] = (int) $e['ID'];
        }
        $values[$code] = $ids ?: false;
    }
    foreach (array_diff(array_keys($p['facets']), array_keys($facetProps)) as $code) {
        throw new RuntimeException("{$p['folder']}: неизвестное свойство фасета $code");
    }
    \CIBlockElement::SetPropertyValuesEx($id, IBLOCK_ID, $values);
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(IBLOCK_ID, $id);

    // картинки: 1-я → PREVIEW/DETAIL, остальные → GALLERY по порядку
    if (!$existing || !$existing['DETAIL_PICTURE']) {
        $fileIds = [];
        foreach ($p['images'] as $img) {
            $fileIds[] = saveImage("$photosDir/{$img['file']}", $img['alt'], $uploadSubdir);
        }
        $main = array_shift($fileIds);
        $DB->Query("UPDATE b_iblock_element SET PREVIEW_PICTURE=$main, DETAIL_PICTURE=$main, TIMESTAMP_X=NOW() WHERE ID=$id");
        $DB->Query("DELETE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID={$props['GALLERY']}");
        foreach ($fileIds as $fid) {
            $DB->Query("INSERT INTO b_iblock_element_property (IBLOCK_PROPERTY_ID, IBLOCK_ELEMENT_ID, VALUE, VALUE_TYPE, VALUE_NUM) VALUES ({$props['GALLERY']}, $id, '$fid', 'text', $fid)");
        }
    } else {
        // картинки уже есть — обновляем только alt (описание файла) по порядку галереи
        $fileIds = [(int) $existing['DETAIL_PICTURE']];
        $rsGal = $DB->Query("SELECT VALUE_NUM FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID={$props['GALLERY']} ORDER BY ID");
        while ($g = $rsGal->Fetch()) { $fileIds[] = (int) $g['VALUE_NUM']; }
        foreach ($p['images'] as $n => $img) {
            if (!empty($fileIds[$n])) {
                $DB->Query("UPDATE b_file SET DESCRIPTION='" . $DB->ForSql($img['alt']) . "' WHERE ID=" . $fileIds[$n]);
            }
        }
    }

    $tpl = new \Bitrix\Iblock\InheritedProperty\ElementTemplates(IBLOCK_ID, $id);
    $tpl->set(['ELEMENT_META_TITLE' => $p['seo']['title'], 'ELEMENT_META_DESCRIPTION' => $p['seo']['description']]);
    (new \Bitrix\Iblock\InheritedProperty\ElementValues(IBLOCK_ID, $id))->clearValues();

    echo "$status {$p['folder']} → ID=$id" . ($p['active'] ? '' : ' (черновик, ACTIVE=N)') . "\n";
}

// --- бренд --------------------------------------------------------------------
if (!$dry && !$only) {
    if ($forceTexts || trim((string) $brand['PREVIEW_TEXT']) === '') {
        $el = new \CIBlockElement();
        if (!$el->Update($brandId, ['PREVIEW_TEXT' => $data['brand']['text_html'], 'PREVIEW_TEXT_TYPE' => 'html'])) {
            throw new RuntimeException('Бренд: ' . $el->LAST_ERROR);
        }
        echo "Бренд Halton (ID=$brandId): текст записан\n";
        $brand['PREVIEW_TEXT'] = $data['brand']['text_html'];
    }
    // Ссылки на разделы с товарами бренда — дописываются к тексту (сам текст из docx не меняется)
    if (strpos((string) $brand['PREVIEW_TEXT'], '/product-category/vytyazhnye-zonty/') === false) {
        $links = "\n\n<p>Оборудование Halton в каталоге ПРОФЭКВИП: <a href=\"/product-category/ventiliruemye-potolki/\">вентилируемые потолки</a>"
            . " и <a href=\"/product-category/vytyazhnye-zonty/\">вытяжные зонты</a>.</p>";
        $el = new \CIBlockElement();
        if (!$el->Update($brandId, ['PREVIEW_TEXT' => rtrim($brand['PREVIEW_TEXT']) . $links, 'PREVIEW_TEXT_TYPE' => 'html'])) {
            throw new RuntimeException('Бренд: ' . $el->LAST_ERROR);
        }
        echo "Бренд Halton: добавлены ссылки на разделы\n";
    }
    $tpl = new \Bitrix\Iblock\InheritedProperty\ElementTemplates(BRANDS_IBLOCK_ID, $brandId);
    $tpl->set(['ELEMENT_META_TITLE' => $data['brand']['meta_title'], 'ELEMENT_META_DESCRIPTION' => $data['brand']['meta_description']]);
    (new \Bitrix\Iblock\InheritedProperty\ElementValues(BRANDS_IBLOCK_ID, $brandId))->clearValues();
    echo "Бренд Halton: SEO записано\n";
}

if (!$dry) {
    clearCache();
    echo "Кэш очищен\n";
}
