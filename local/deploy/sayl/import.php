<?php
/**
 * SAYL: 29 моделей витрин и буфетных станций (7 линеек) вместо 4 старых карточек-«линий».
 * Данные — products.json (собирается build_data.py из docx владельца), картинки — папка --photos.
 * Разделы и свойство LINEYKA создаёт миграция local/migrations/2026-10-07-sayl-sections-props.php
 * (запускать её первой).
 *
 * Запуск (в контейнере test3 / на хосте прода с SERVER_NAME=prof-equip.ru):
 *   php local/deploy/sayl/import.php --photos=/path/sayl_photos [--only=sayl-qbo] [--dry]
 *   php local/deploy/sayl/import.php --retire --backup-dir=/path [--dry]   # старые «линии»: бэкап → ACTIVE=N → 301
 *
 * Цены и артикулов нет в источниках — не создаются (карточка показывает «Запросить стоимость»).
 * Картинки: файл сохраняется штатно (CFile::SaveFile, описание файла = alt), ID ставится
 * элементу прямым SQL (см. «Известные грабли» в CLAUDE.md). На test3 — upload/import_test,
 * на проде — upload/iblock.
 * Идемпотентен: товар ищется по CODE и обновляется; картинки загружаются, только если у товара
 * их ещё нет (иначе обновляется только alt); редирект ищется по FROM.
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
$only = $opts['only'] ?? null;
$photosDir = rtrim($opts['photos'] ?? '/tmp/sayl_photos', '/');

const IBLOCK_ID = 11;
const BRANDS_IBLOCK_ID = 14;

// Старые карточки-«линии» → цель 301 (раздел новой структуры; согласовано в плане 2026-10-02)
const RETIRE = [
    'innovatsionnaya-modulnaya-liniya-razdachi-sayl-buffet-line' => '/product-category/bufetnye-stantsii/',
    'innovatsionnaya-modulnaya-liniya-razdachi-sayl-integra-line' => '/product-category/holodilnye-vitriny/',
    'innovatsionnaya-modulnaya-liniya-razdachi-sayl-neutra-line' => '/product-category/neytralnye-vitriny/',
    'innovatsionnaya-nastolnaya-vitrinnaya-sistema-sayl-sobremostrador-line' => '/product-category/nastolnye-vitriny/',
];

// Мета и картинка подразделов (картинка — главное фото товара-представителя)
const SECTION_META = [
    'bufetnye-stantsii' => ['Буфетные станции для гостиниц и ресторанов — купить в ПРОФЭКВИП',
        'Буфетные станции SAYL (Испания) серии Buffet Line: островные, круговые, мобильные тепловые и с обслуживанием персоналом. Подбор и комплексное оснащение.',
        'sayl-buffet-island.jpg'],
    'holodilnye-vitriny' => ['Холодильные витрины для ресторанов и кафе — купить в ПРОФЭКВИП',
        'Холодильные витрины SAYL (Испания): настольные, встраиваемые, напольные, для суши и морепродуктов. Подбор оборудования и комплексное оснащение.',
        'sayl-pak-curvada.jpg'],
    'teplovye-vitriny' => ['Тепловые витрины для ресторанов и буфетов — купить в ПРОФЭКВИП',
        'Тепловые и универсальные (холод/тепло) витрины и буфетные станции SAYL (Испания) для подачи горячих блюд. Подбор оборудования и комплексное оснащение.',
        'sayl-maxiself.jpg'],
    'neytralnye-vitriny' => ['Нейтральные витрины для кафе и пекарен — купить в ПРОФЭКВИП',
        'Нейтральные витрины SAYL (Испания) серии Neutra Line для выпечки, десертов и закусок без температурного режима. Подбор и комплексное оснащение.',
        'sayl-neutra-recta.jpg'],
    'nastolnye-vitriny' => ['Настольные витрины для кафе и кондитерских — купить в ПРОФЭКВИП',
        'Настольные охлаждаемые витрины SAYL (Испания) серии Sobremostrador Line: QBO, VELA, TOWER, CRYSTAL BOX. Подбор и комплексное оснащение.',
        'sayl-qbo.jpg'],
    'vitriny-dlya-sushi' => ['Витрины для суши — купить в ПРОФЭКВИП',
        'Холодильные витрины для суши SAYL (Испания) серии Sushi Line: LOGIC, SHARK, CLASSIC, SLIM. Подбор оборудования и комплексное оснащение.',
        'sayl-shark-sushi.jpg'],
];

$isTest3 = is_link($_SERVER['DOCUMENT_ROOT'] . '/upload/iblock') || !is_writable($_SERVER['DOCUMENT_ROOT'] . '/upload/iblock');
$uploadSubdir = $isTest3 ? 'import_test' : 'iblock';

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

function enumId(int $propId, string $value): int
{
    $e = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propId, 'VALUE' => $value])->Fetch();
    if (!$e) { throw new RuntimeException("Нет значения «{$value}» у свойства $propId (миграция?)"); }
    return (int) $e['ID'];
}

// --- старые «линии»: бэкап → деактивация → 301 ----------------------------------------
if (isset($opts['retire'])) {
    $redirectIblock = (int) GetIBlockIDByCode('redirect');
    if (!$redirectIblock) { throw new RuntimeException('Нет инфоблока redirect'); }
    $backup = [];
    foreach (RETIRE as $code => $target) {
        $rs = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $code]);
        $found = [];
        while ($ob = $rs->GetNextElement()) { $found[] = $ob; }
        if (count($found) !== 1) { throw new RuntimeException("$code: найдено " . count($found) . ' элементов, ожидался 1'); }
        $f = $found[0]->GetFields();
        $sections = [];
        $rsS = \CIBlockElement::GetElementGroups($f['ID'], true, ['ID', 'CODE', 'NAME']);
        while ($s = $rsS->Fetch()) { $sections[] = $s; }
        $files = [];
        foreach (array_filter([$f['PREVIEW_PICTURE'], $f['DETAIL_PICTURE']]) as $fid) { $files[$fid] = \CFile::GetPath($fid); }
        $props = $found[0]->GetProperties();
        foreach ((array) ($props['GALLERY']['VALUE'] ?? []) as $fid) { $files[$fid] = \CFile::GetPath($fid); }
        $backup[$code] = [
            'fields' => array_filter($f, static fn($k) => $k[0] === '~', ARRAY_FILTER_USE_KEY),
            'properties' => array_map(static fn($p) => ['NAME' => $p['NAME'], 'VALUE' => $p['~VALUE'], 'VALUE_ENUM_ID' => $p['VALUE_ENUM_ID'] ?? null, 'DESCRIPTION' => $p['DESCRIPTION'] ?? null], $props),
            'sections' => $sections,
            'files' => $files,
            'seo' => (new \Bitrix\Iblock\InheritedProperty\ElementTemplates(IBLOCK_ID, (int) $f['ID']))->findTemplates(),
        ];
        // цель обязана существовать (раздел) и не редиректить дальше
        $targetCode = basename(rtrim($target, '/'));
        if (!\CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $targetCode, 'ACTIVE' => 'Y'])->Fetch()) {
            throw new RuntimeException("$code: раздел-цель $target не найден");
        }
        echo "$code: ID={$f['ID']} «{$f['~NAME']}» ACTIVE={$f['ACTIVE']} → $target\n";
    }
    if ($dry) { exit(0); }

    $dir = rtrim($opts['backup-dir'] ?? '', '/');
    if ($dir === '' || (!is_dir($dir) && !mkdir($dir, 0700, true))) { throw new RuntimeException('Укажите --backup-dir=… (папка для JSON-бэкапа)'); }
    $file = $dir . '/sayl-old-products-' . date('Ymd') . '.json';
    if (is_file($file)) { $file = $dir . '/sayl-old-products-' . date('Ymd-His') . '.json'; }
    file_put_contents($file, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo "Бэкап: $file\n";

    $rProps = [];
    $rs = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $redirectIblock]);
    while ($p = $rs->Fetch()) { $rProps[$p['CODE']] = (int) $p['ID']; }
    foreach (RETIRE as $code => $target) {
        $from = "/product/$code/";
        $el = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $code], false, false, ['ID'])->Fetch();
        (new \CIBlockElement())->Update((int) $el['ID'], ['ACTIVE' => 'N']);
        $existing = \CIBlockElement::GetList([], ['IBLOCK_ID' => $redirectIblock, 'PROPERTY_FROM' => $from], false, false, ['ID'])->Fetch();
        if ($existing) {
            \CIBlockElement::SetPropertyValuesEx((int) $existing['ID'], $redirectIblock, ['WHERE' => $target]);
            (new \CIBlockElement())->Update((int) $existing['ID'], ['ACTIVE' => 'Y']);
            echo "$code: ACTIVE=N, редирект обновлён (ID={$existing['ID']})\n";
        } else {
            $rid = (new \CIBlockElement())->Add(['IBLOCK_ID' => $redirectIblock, 'NAME' => $from, 'ACTIVE' => 'Y',
                'PROPERTY_VALUES' => [$rProps['FROM'] => $from, $rProps['WHERE'] => $target]]);
            if (!$rid) { throw new RuntimeException("$code: редирект не создан"); }
            echo "$code: ACTIVE=N, редирект создан (ID=$rid)\n";
        }
    }
    clearCache();
    echo "Кэш очищен\n";
    exit(0);
}

// --- товары ------------------------------------------------------------------------
$data = json_decode(file_get_contents(__DIR__ . '/products.json'), true, 512, JSON_THROW_ON_ERROR);

$props = [];
$rs = \CIBlockProperty::GetList([], ['IBLOCK_ID' => IBLOCK_ID]);
while ($p = $rs->Fetch()) { $props[$p['CODE']] = (int) $p['ID']; }
foreach (['SPECS', 'GALLERY', 'BRAND', 'PROIZVODITEL', 'COUNTRY', 'LINEYKA'] as $code) {
    if (empty($props[$code])) { throw new RuntimeException("Нет свойства $code (запустите миграцию)."); }
}
$enumSayl = enumId($props['PROIZVODITEL'], 'SAYL');
$enumSpain = enumId($props['COUNTRY'], 'Испания');

$brand = \CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK_ID, 'CODE' => 'sayl'], false, false, ['ID'])->Fetch();
if (!$brand) { throw new RuntimeException('Нет бренда SAYL в инфоблоке брендов.'); }
$brandId = (int) $brand['ID'];

$sectionIds = [];
foreach (array_keys($data['sections']) as $code) {
    $section = \CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $code], false, ['ID', 'PICTURE'])->Fetch();
    if (!$section) { throw new RuntimeException("Нет раздела $code (запустите миграцию)."); }
    $sid = $sectionIds[$code] = (int) $section['ID'];
    if ($dry || $only) { continue; }
    [$title, $desc, $pic] = SECTION_META[$code];
    $tpl = new \Bitrix\Iblock\InheritedProperty\SectionTemplates(IBLOCK_ID, $sid);
    $tpl->set(['SECTION_META_TITLE' => $title, 'SECTION_META_DESCRIPTION' => $desc]);
    (new \Bitrix\Iblock\InheritedProperty\SectionValues(IBLOCK_ID, $sid))->clearValues();
    if (!$section['PICTURE']) {
        $fid = saveImage("$photosDir/$pic", $data['sections'][$code], $uploadSubdir);
        $DB->Query("UPDATE b_iblock_section SET PICTURE=$fid, TIMESTAMP_X=NOW() WHERE ID=$sid");
        echo "Раздел $code: картинка (file $fid)\n";
    }
}

foreach ($data['products'] as $p) {
    if ($only && $only !== $p['slug']) { continue; }
    foreach ($p['images'] as $img) {
        if (!is_file("$photosDir/{$img['file']}")) { throw new RuntimeException("{$p['slug']}: нет фото {$img['file']}"); }
    }
    $sections = array_map(static fn($c) => $sectionIds[$c], $p['sections']);
    if ($dry) {
        echo "[dry] {$p['slug']} → " . implode(',', $p['sections']) . ', ' . count($p['specs']) . ' хар., ' . count($p['images']) . " фото\n";
        continue;
    }

    $fields = [
        'IBLOCK_ID' => IBLOCK_ID,
        'IBLOCK_SECTION_ID' => $sections[0],
        'IBLOCK_SECTION' => $sections,
        'NAME' => $p['name'],
        'CODE' => $p['slug'],
        'ACTIVE' => $p['active'] ? 'Y' : 'N',
        'SORT' => $p['sort'],
        'PREVIEW_TEXT' => $p['preview_html'],
        'PREVIEW_TEXT_TYPE' => 'html',
        'DETAIL_TEXT' => $p['description_html'],
        'DETAIL_TEXT_TYPE' => 'html',
    ];
    $el = new \CIBlockElement();
    $existing = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CODE' => $p['slug']], false, false, ['ID', 'DETAIL_PICTURE'])->Fetch();
    if ($existing) {
        $id = (int) $existing['ID'];
        if (!$el->Update($id, $fields)) { throw new RuntimeException("{$p['slug']}: update: " . $el->LAST_ERROR); }
        $status = 'updated';
    } else {
        $fields['PROPERTY_VALUES'] = [];
        $id = (int) $el->Add($fields);
        if (!$id) { throw new RuntimeException("{$p['slug']}: add: " . $el->LAST_ERROR); }
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
    \CIBlockElement::SetPropertyValuesEx($id, IBLOCK_ID, [
        'BRAND' => [$brandId],
        'PROIZVODITEL' => $enumSayl,
        'COUNTRY' => [$enumSpain],
        'LINEYKA' => enumId($props['LINEYKA'], $p['line']),
        'SPECS' => $specs ?: false,
    ]);
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(IBLOCK_ID, $id);

    // картинки: 1-я → PREVIEW/DETAIL, остальные → GALLERY по порядку
    if ($p['images'] && (!$existing || !$existing['DETAIL_PICTURE'])) {
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
    } elseif ($existing && $existing['DETAIL_PICTURE']) {
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

    // проверка по базе, а не по коду возврата
    $chk = $DB->Query("SELECT IBLOCK_SECTION_ID, ACTIVE FROM b_iblock_element WHERE ID=$id")->Fetch();
    $cnt = (int) $DB->Query("SELECT COUNT(*) c FROM b_iblock_section_element WHERE IBLOCK_ELEMENT_ID=$id")->Fetch()['c'];
    if ((int) $chk['IBLOCK_SECTION_ID'] !== $sections[0] || $cnt !== count($sections)) {
        throw new RuntimeException("{$p['slug']}: разделы записаны неверно (основной {$chk['IBLOCK_SECTION_ID']}, привязок $cnt)");
    }

    echo "$status {$p['slug']} → ID=$id" . ($p['active'] ? '' : ' (черновик, ACTIVE=N)') . "\n";
}

if (!$dry) {
    clearCache();
    echo "Кэш очищен\n";
}
