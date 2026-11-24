<?php
/** CLI importer for B24 task 8627. Dry run by default; production needs an explicit switch. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$apply = in_array('--apply', $argv, true);
$root = realpath(dirname(__DIR__, 3));
$isDev = str_contains($root, '/dev.prof-equip.ru/') || $root === '/var/www/html';
if ($apply && !$isDev && !in_array('--allow-production', $argv, true)) {
    fwrite(STDERR, "Refusing write outside dev. Production requires approval and --allow-production.\n"); exit(1);
}
$backupDir = null;
$only = null; // --only=code1,code2 — обработать только указанные проекты
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--backup-dir=')) { $backupDir = substr($arg, 13); }
    if (str_starts_with($arg, '--only=')) { $only = array_values(array_filter(array_map('trim', explode(',', substr($arg, 7))))); }
}
if ($apply && (!$backupDir || !is_dir($backupDir) || !is_writable($backupDir))) {
    fwrite(STDERR, "An existing writable --backup-dir outside the document root is required.\n"); exit(1);
}
if ($apply && str_starts_with(realpath($backupDir), $root . '/')) {
    fwrite(STDERR, "Backups must be outside the document root.\n"); exit(1);
}
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['SERVER_NAME'] = $isDev ? 'dev.prof-equip.ru' : 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('NO_AGENT_CHECK', true);
require $root . '/bitrix/modules/main/include/prolog_before.php';
CModule::IncludeModule('iblock');

function iblockId(string $code): int {
    $r = CIBlock::GetList([], ['CODE' => $code])->Fetch();
    if (!$r) { throw new RuntimeException("Missing iblock: $code"); }
    return (int)$r['ID'];
}
function findElement(int $ib, string $code): array {
    $rs = CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, '=CODE' => $code], false, false,
        ['ID','CODE','NAME','ACTIVE','SORT','PREVIEW_PICTURE','DETAIL_PICTURE','DETAIL_TEXT','DETAIL_TEXT_TYPE','IBLOCK_SECTION_ID']);
    $r = $rs->Fetch();
    if (!$r || $rs->Fetch()) { throw new RuntimeException("Missing or ambiguous element: $code"); }
    return $r;
}
function readProps(int $ib, int $id): array {
    $out = [];
    $rs = CIBlockElement::GetProperty($ib, $id, ['sort' => 'asc', 'id' => 'asc'], []);
    while ($p = $rs->Fetch()) {
        if ($p['VALUE'] !== false && $p['VALUE'] !== null && $p['VALUE'] !== '') {
            $out[$p['CODE']][] = ['VALUE' => $p['VALUE'], 'DESCRIPTION' => $p['DESCRIPTION'] ?? ''];
        }
    }
    return $out;
}
function canonical($v) {
    if (!is_array($v)) { return (string)($v ?? ''); }
    $out = [];
    foreach ($v as $k => $value) { $out[$k] = canonical($value); }
    return $out;
}
function rawValues(array $values): array { return array_map(fn($v) => $v['VALUE'], $values); }
function expected(array $row, array $brandIds, array $productIds, array $current): array {
    $out = ['INFO_TITLE' => [['VALUE'=>'Кратко о проекте','DESCRIPTION'=>'']],
        'LIST' => array_map(fn($v) => ['VALUE'=>$v['value'],'DESCRIPTION'=>$v['label']], $row['brief']),
        'BLOCKS' => array_map(fn($v) => ['VALUE'=>['TYPE'=>'HTML','TEXT'=>$v['html']],'DESCRIPTION'=>$v['title']], $row['blocks'])];
    if ($brandIds) { $out['BRANDS'] = array_map(fn($id)=>['VALUE'=>$id,'DESCRIPTION'=>''], $brandIds); }
    if ($productIds) {
        $out['CATALOG_PRODUCT_1'] = array_map(fn($id)=>['VALUE'=>$id,'DESCRIPTION'=>''], $productIds);
        $out['TITLE_CATALOG_1'] = [['VALUE'=>'Оборудование и текстиль проекта','DESCRIPTION'=>'']];
    }
    return $out;
}
function apiValues(array $props): array {
    $out = [];
    foreach ($props as $code => $values) {
        foreach ($values as $n => $value) { $out[$code]['n'.$n] = $value; }
    }
    return $out;
}
function verifyProps(array $expected, array $actual, string $code): void {
    foreach ($expected as $key => $values) {
        if (canonical($values) !== canonical($actual[$key] ?? [])) {
            throw new RuntimeException("Read-back mismatch: $code / $key");
        }
    }
}

$projects = json_decode(file_get_contents(__DIR__.'/projects.json'), true, 512, JSON_THROW_ON_ERROR);
if ($only !== null) {
    $unknown = array_diff($only, array_column($projects, 'code'));
    if (!$only || $unknown) { fwrite(STDERR, "Unknown --only codes: ".implode(',', $unknown)."\n"); exit(1); }
    $projects = array_values(array_filter($projects, fn($p) => in_array($p['code'], $only, true)));
}
$ib = iblockId('projects'); $brandsIb = iblockId('brands'); $catalogIb = iblockId('catalog');
// Preflight the entire batch before changing any records.
$plans = [];
foreach ($projects as $row) {
    $el = findElement($ib, $row['code']);
    if ($el['ACTIVE'] !== 'Y') { throw new RuntimeException("Inactive project: {$row['code']}"); }
    $brands = []; $products = [];
    foreach (['brand_codes' => $brandsIb, 'product_codes' => $catalogIb] as $key => $refIb) {
        foreach ($row[$key] as $code) {
            $ref = findElement($refIb, $code);
            if ($ref['ACTIVE'] !== 'Y') { throw new RuntimeException("Inactive reference: $code"); }
            if ($key === 'brand_codes') { $brands[] = (int)$ref['ID']; } else { $products[] = (int)$ref['ID']; }
        }
    }
    foreach ($row['images'] as $image) {
        $file = __DIR__.'/'.$image['path'];
        if (!is_file($file) || hash_file('sha256', $file) !== $image['sha256']) {
            throw new RuntimeException("Missing or changed asset: {$image['path']}");
        }
    }
    $props = readProps($ib, (int)$el['ID']);
    $want = expected($row, $brands, $products, $props);
    $changes = [];
    foreach ($want as $key => $values) {
        if (canonical($values) !== canonical($props[$key] ?? [])) { $changes[$key] = $values; }
    }
    $plans[] = compact('row','el','props','want','changes');
}
$summary = ['mode'=>$apply?'apply':'dry','projects'=>count($plans),'changed'=>0,'rows'=>[]];
foreach ($plans as $plan) {
    extract($plan, EXTR_OVERWRITE);
    $id = (int)$el['ID'];
    $galleryEmpty = empty($props['GALLERY']) && !empty($row['images']);
    $coverEmpty = $row['cover'] && (!(int)$el['PREVIEW_PICTURE'] || !(int)$el['DETAIL_PICTURE']);
    $sortChanged = (int)$el['SORT'] !== $row['sort'];
    $changed = (bool)$changes || $galleryEmpty || $coverEmpty || $sortChanged;
    $summary['changed'] += (int)$changed;
    $entry = ['code'=>$row['code'],'id'=>$id,'properties'=>array_keys($changes),
        'add_gallery'=>$galleryEmpty?count($row['images']):0,'fill_cover'=>(bool)$coverEmpty,
        'sort'=>$row['sort'],'unresolved_brands'=>$row['unresolved_brands']];
    if ($apply && $changed) {
        $snapshot = $backupDir.'/'.$id.'-'.date('Ymd_His').'-'.bin2hex(random_bytes(3)).'.json';
        $oldUmask = umask(0077);
        if (file_put_contents($snapshot, json_encode(['element'=>$el,'properties'=>$props], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) === false) {
            throw new RuntimeException("Cannot save snapshot: $snapshot");
        }
        umask($oldUmask);
        if ($changes) { CIBlockElement::SetPropertyValuesEx($id, $ib, apiValues($changes)); }
        if ($galleryEmpty) {
            $gallery = [];
            foreach ($row['images'] as $n => $image) {
                $file = CFile::MakeFileArray(__DIR__.'/'.$image['path']);
                $file['MODULE_ID']='iblock';
                $gallery['n'.$n] = ['VALUE'=>$file,'DESCRIPTION'=>'b24#8627 '.$image['sha256']];
            }
            CIBlockElement::SetPropertyValuesEx($id, $ib, ['GALLERY'=>$gallery]);
        }
        if ($coverEmpty) {
            $file = CFile::MakeFileArray(__DIR__.'/'.$row['cover']);
            $file['MODULE_ID']='iblock'; $file['description']='b24#8627 cover '.$row['code'];
            $fileId = (int)CFile::SaveFile($file, 'iblock');
            if (!$fileId) { throw new RuntimeException("Cannot save cover: {$row['code']}"); }
            $set = [];
            foreach (['PREVIEW_PICTURE','DETAIL_PICTURE'] as $field) { if (!(int)$el[$field]) { $set[]="$field=$fileId"; } }
            $DB->Query('UPDATE b_iblock_element SET '.implode(',', $set).' WHERE ID='.$id.' AND IBLOCK_ID='.$ib);
        }
        if ($sortChanged && !(new CIBlockElement())->Update($id, ['SORT'=>$row['sort']], false, false, false, false)) {
            throw new RuntimeException("Cannot set sort: {$row['code']}");
        }
        $after = readProps($ib, $id);
        verifyProps($want, $after, $row['code']);
        if ($row['images'] && empty($after['GALLERY'])) { throw new RuntimeException("Empty gallery: {$row['code']}"); }
        foreach (rawValues($after['GALLERY'] ?? []) as $fileId) {
            $file = CFile::GetFileArray((int)$fileId);
            if (!$file || !is_file($root.$file['SRC'])) { throw new RuntimeException("Missing gallery file: $fileId"); }
        }
        $newEl = findElement($ib, $row['code']);
        if ((int)$newEl['SORT'] !== $row['sort'] || $newEl['IBLOCK_SECTION_ID'] !== $el['IBLOCK_SECTION_ID']) {
            throw new RuntimeException("Element field verification failed: {$row['code']}");
        }
    }
    $summary['rows'][]=$entry;
}
if ($apply && $summary['changed']) {
    CIBlock::clearIblockTagCache($ib);
    BXClearCache(true, '/s1/bitrix/news/');
    BXClearCache(true, '/s1/bitrix/news.detail/');
    BXClearCache(true, '/s1/bitrix/news.list/');
}
echo json_encode($summary, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), "\n";
