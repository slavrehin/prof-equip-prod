<?php
/**
 * Возврат портфолио к состоянию старого сервера на конец октября 2026 (см. README.md).
 * Каждый проект, кроме --keep, приводится точно к projects.json: поля, обложки, галерея,
 * все свойства (лишние — очищаются), SEO-шаблоны элемента. Отсутствующие проекты создаются.
 * Сухой прогон по умолчанию; повторный запуск после --apply должен давать 0 изменений.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$opt = ['apply' => false, 'activate-hidden' => false, 'allow-production' => false];
$backupDir = $src = null; $keep = []; $only = null;
foreach (array_slice($argv, 1) as $arg) {
    if (isset($opt[ltrim($arg, '-')])) { $opt[ltrim($arg, '-')] = true; continue; }
    if (str_starts_with($arg, '--backup-dir=')) { $backupDir = rtrim(substr($arg, 13), '/'); continue; }
    if (str_starts_with($arg, '--src=')) { $src = rtrim(substr($arg, 6), '/'); continue; }
    if (str_starts_with($arg, '--keep=')) { $keep = array_filter(array_map('trim', explode(',', substr($arg, 7)))); continue; }
    if (str_starts_with($arg, '--only=')) { $only = array_filter(array_map('trim', explode(',', substr($arg, 7)))); continue; }
    fwrite(STDERR, "Unknown argument: $arg\n"); exit(1);
}
$root = realpath(dirname(__DIR__, 3));
$isProd = !str_contains($root, '/dev.prof-equip.ru/') && $root !== '/var/www/html';
if ($opt['apply'] && $isProd && !$opt['allow-production']) {
    fwrite(STDERR, "Refusing write on production without --allow-production (needs approval).\n"); exit(1);
}
if ($opt['apply'] && (!$backupDir || !is_dir($backupDir) || !is_writable($backupDir) || str_starts_with(realpath($backupDir), $root.'/'))) {
    fwrite(STDERR, "--apply needs an existing writable --backup-dir outside the document root.\n"); exit(1);
}
if (!$src || !is_dir($src)) { fwrite(STDERR, "--src=<image dir> is required.\n"); exit(1); }

$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['SERVER_NAME'] = $isProd ? 'prof-equip.ru' : 'dev.prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('NO_AGENT_CHECK', true);
require $root.'/bitrix/modules/main/include/prolog_before.php';
CModule::IncludeModule('iblock');
use Bitrix\Iblock\InheritedProperty\ElementTemplates;

function fail(string $m): never { throw new RuntimeException($m); }
function iblockId(string $code): int {
    $r = CIBlock::GetList([], ['CODE' => $code, 'CHECK_PERMISSIONS' => 'N'])->Fetch() ?: fail("Missing iblock: $code");
    return (int)$r['ID'];
}
function element(int $ib, string $code): ?array {
    $rs = CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, '=CODE' => $code, 'CHECK_PERMISSIONS' => 'N'], false, false,
        ['ID','CODE','NAME','ACTIVE','SORT','ACTIVE_FROM','PREVIEW_PICTURE','DETAIL_PICTURE','PREVIEW_TEXT','PREVIEW_TEXT_TYPE','DETAIL_TEXT','DETAIL_TEXT_TYPE']);
    $r = $rs->Fetch();
    if ($r && $rs->Fetch()) { fail("Ambiguous CODE $code in iblock $ib"); }
    return $r ?: null;
}
/** Текущие значения свойств в каноническом виде, сравнимом с expected(). */
function currentProps(int $ib, int $id, array $types): array {
    $out = [];
    $rs = CIBlockElement::GetProperty($ib, $id, ['sort' => 'asc', 'id' => 'asc'], []);
    while ($p = $rs->Fetch()) {
        $v = $p['VALUE'];
        if ($v === false || $v === null || $v === '' || (is_array($v) && trim((string)($v['TEXT'] ?? '')) === '')) { continue; }
        $code = $p['CODE'];
        $d = (string)($p['DESCRIPTION'] ?? '');
        $out[$code][] = match ($types[$code]) {
            'F' => ['sha' => fileSha((int)$v), 'id' => (int)$v],
            'L', 'E' => (int)$v,
            'HTML' => ['text' => (string)$v['TEXT'], 'd' => $d],
            default => ['v' => (string)$v, 'd' => $d],
        };
    }
    return $out;
}
function fileSha(int $id): ?string {
    static $cache = [];
    if (!$id) { return null; }
    if (!array_key_exists($id, $cache)) {
        $f = CFile::GetFileArray($id);
        $path = $f ? $_SERVER['DOCUMENT_ROOT'].$f['SRC'] : null;
        $cache[$id] = ($path && is_file($path)) ? hash_file('sha256', $path) : 'missing';
    }
    return $cache[$id];
}
function fileArray(string $src, array $img): array {
    $f = CFile::MakeFileArray("$src/{$img['file']}") ?: fail("Cannot read {$img['file']}");
    $f['name'] = $img['name'] ?: basename($img['file']);
    $f['MODULE_ID'] = 'iblock';
    return $f;
}
function ownSeo(int $ib, int $id): array {
    $out = [];
    foreach ((new ElementTemplates($ib, $id))->findTemplates() as $code => $t) {
        if (($t['ENTITY_TYPE'] ?? '') === 'E' && (int)($t['ENTITY_ID'] ?? 0) === $id && trim($t['TEMPLATE']) !== '') { $out[$code] = trim($t['TEMPLATE']); }
    }
    ksort($out);
    return $out;
}
function ts(?string $v): ?int { return ($v === null || $v === '') ? null : (int)MakeTimeStamp($v, str_contains($v, '-') ? 'YYYY-MM-DD HH:MI:SS' : FORMAT_DATETIME); }
/** Сохраняет копию файла, который будет заменён, по его пути в upload/ — для отката вместе с дампом БД. */
function keepFile(?string $backupDir, int $id): void {
    if (!$backupDir || !$id || !($f = CFile::GetFileArray($id))) { return; }
    $from = $_SERVER['DOCUMENT_ROOT'].$f['SRC'];
    $to = $backupDir.'/files'.$f['SRC'];
    if (is_file($from) && !is_file($to)) { @mkdir(dirname($to), 0700, true); copy($from, $to) || fail("Cannot back up $from"); }
}

$ib = iblockId('projects');
$refIb = ['CATALOG_PRODUCT_1' => iblockId('catalog'), 'CATALOG_PRODUCT_2' => iblockId('catalog'), 'BRANDS' => iblockId('brands')];
$brandAlias = ['prof-equip' => 'profekvip']; // бренд переименован при переезде
$types = [];
$rs = CIBlockProperty::GetList([], ['IBLOCK_ID' => $ib]);
while ($p = $rs->Fetch()) { $types[$p['CODE']] = $p['USER_TYPE'] === 'HTML' ? 'HTML' : $p['PROPERTY_TYPE']; }
$typeEnum = [];
$rs = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $ib, 'CODE' => 'TYPE']);
while ($e = $rs->Fetch()) { $typeEnum[$e['VALUE']] = (int)$e['ID']; }
$seoCodes = ['ELEMENT_META_TITLE', 'ELEMENT_META_KEYWORDS', 'ELEMENT_META_DESCRIPTION', 'ELEMENT_PAGE_TITLE'];

$data = json_decode(file_get_contents(__DIR__.'/projects.json'), true, 512, JSON_THROW_ON_ERROR);
$rows = array_values(array_filter($data['projects'], fn($r) => !in_array($r['code'], $keep, true) && ($only === null || in_array($r['code'], $only, true))));
if (array_diff($keep, array_column($data['projects'], 'code'))) { fwrite(STDERR, "Unknown --keep code\n"); exit(1); }

$refCache = []; $report = ['unresolved_refs' => [], 'unknown_types' => []];
$resolve = function (string $prop, string $code) use (&$refCache, $refIb, $brandAlias): ?int {
    if ($prop === 'BRANDS') { $code = $brandAlias[$code] ?? $code; }
    $k = "$prop:$code";
    if (!array_key_exists($k, $refCache)) { $r = element($refIb[$prop], $code); $refCache[$k] = ($r && $r['ACTIVE'] === 'Y') ? (int)$r['ID'] : null; }
    return $refCache[$k];
};
/** Ожидаемые значения всех свойств инфоблока; свойства, которых нет в источнике, — пустые. */
$expected = function (array $r) use ($types, $typeEnum, $resolve, &$report): array {
    $out = array_fill_keys(array_keys($types), []);
    foreach ($r['type'] as $t) { isset($typeEnum[$t]) ? $out['TYPE'][] = $typeEnum[$t] : $report['unknown_types'][$r['code']][] = $t; }
    $out['TYPE'] = array_values(array_unique($out['TYPE']));
    $out['LIST'] = array_map(fn($v) => $types['LIST'] === 'HTML' ? ['text' => $v['value'], 'd' => $v['description']] : ['v' => $v['value'], 'd' => $v['description']], $r['list']);
    $out['BLOCKS'] = array_map(fn($v) => ['text' => $v['html'], 'd' => $v['title']], $r['blocks']);
    $out['GALLERY'] = array_map(fn($g) => ['sha' => $g['sha256']], $r['gallery']);
    foreach ($r['strings'] as $k => $v) { if (isset($types[$k])) { $out[$k] = [['v' => $v, 'd' => '']]; } }
    foreach ($r['refs'] as $k => $codes) {
        foreach ($codes as $c) { ($id = $resolve($k, $c)) ? $out[$k][] = $id : $report['unresolved_refs'][$r['code']][] = "$k:$c"; }
    }
    return $out;
};
$same = fn($a, $b) => json_encode($a, JSON_UNESCAPED_UNICODE) === json_encode($b, JSON_UNESCAPED_UNICODE);
$noIds = fn(array $vals) => array_map(fn($v) => is_array($v) ? array_diff_key($v, ['id' => 1]) : $v, $vals);
$norm = function (string $code, array $vals) use ($types): array { if ($types[$code] === 'L') { sort($vals); } return array_values($vals); };

// ---------- план ----------
$plans = [];
foreach ($rows as $r) {
    foreach (array_merge([$r['preview_picture'], $r['detail_picture']], $r['gallery']) as $img) {
        if ($img && (!is_file("$src/{$img['file']}") || hash_file('sha256', "$src/{$img['file']}") !== $img['sha256'])) { fail("Missing or changed image {$img['file']}"); }
    }
    $active = ($r['old_active'] === 'Y' || $opt['activate-hidden']) ? 'Y' : 'N';
    $want = $expected($r);
    $cur = element($ib, $r['code']);
    if (!$cur) { $plans[] = ['action' => 'create', 'row' => $r, 'active' => $active, 'want' => $want]; continue; }
    $id = (int)$cur['ID'];
    $fields = [];
    $target = ['NAME' => $r['name'], 'SORT' => $r['old_sort'], 'ACTIVE' => $active,
        'PREVIEW_TEXT' => $r['preview_text'], 'PREVIEW_TEXT_TYPE' => $r['preview_text_type'],
        'DETAIL_TEXT' => $r['detail_text'], 'DETAIL_TEXT_TYPE' => $r['detail_text_type']];
    foreach ($target as $k => $v) { if ((string)$cur[$k] !== (string)$v) { $fields[$k] = $v; } }
    if (ts($cur['ACTIVE_FROM']) !== ts($r['old_active_from'])) { $fields['ACTIVE_FROM'] = $r['old_active_from'] ? ConvertTimeStamp(ts($r['old_active_from']), 'FULL') : false; }
    foreach (['PREVIEW_PICTURE' => 'preview_picture', 'DETAIL_PICTURE' => 'detail_picture'] as $f => $k) {
        if (fileSha((int)$cur[$f]) !== ($r[$k]['sha256'] ?? null)) { $fields[$f] = $r[$k]; }
    }
    $have = currentProps($ib, $id, $types);
    $props = [];
    foreach ($want as $code => $vals) { if (!$same($norm($code, $vals), $norm($code, $noIds($have[$code] ?? [])))) { $props[] = $code; } }
    $seoNow = ownSeo($ib, $id); $seoWant = $r['seo']; ksort($seoWant);
    $seo = $same($seoNow, $seoWant) ? null : $seoWant + array_fill_keys(array_keys($seoNow), '');
    $plans[] = ['action' => ($fields || $props || $seo !== null) ? 'update' : 'none', 'row' => $r, 'cur' => $cur, 'id' => $id,
        'fields' => $fields, 'props' => $props, 'seo' => $seo, 'want' => $want, 'have' => $have, 'seoNow' => $seoNow];
}

$propValue = function (string $code, array $want, array $r) use ($src, $types) {
    $vals = $want[$code];
    if (!$vals) { return false; }
    $out = [];
    foreach ($vals as $n => $v) {
        $out['n'.$n] = match (true) {
            $code === 'GALLERY' => ['VALUE' => fileArray($src, $r['gallery'][$n]), 'DESCRIPTION' => ''],
            $types[$code] === 'HTML' => ['VALUE' => ['TYPE' => 'HTML', 'TEXT' => $v['text']], 'DESCRIPTION' => $v['d']],
            is_int($v) => $v,
            default => ['VALUE' => $v['v'], 'DESCRIPTION' => $v['d']],
        };
    }
    return $types[$code] === 'L' || $types[$code] === 'E' ? array_values($out) : $out;
};

// ---------- запись ----------
$el = new CIBlockElement();
$summary = ['mode' => $opt['apply'] ? 'apply' : 'dry', 'source' => $data['source'], 'keep' => array_values($keep),
    'activate_hidden' => $opt['activate-hidden'], 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'rows' => []];
$stamp = date('Ymd_His');
if ($opt['apply']) { @mkdir("$backupDir/snapshots", 0700, true); }
foreach ($plans as $p) {
    $r = $p['row'];
    if ($p['action'] === 'none') { $summary['unchanged']++; continue; }
    if ($p['action'] === 'create') {
        $summary['created']++;
        $summary['rows'][] = ['code' => $r['code'], 'action' => 'create', 'active' => $p['active']];
        if (!$opt['apply']) { continue; }
        $pv = [];
        foreach (array_keys($p['want']) as $code) { if ($p['want'][$code]) { $pv[$code] = $propValue($code, $p['want'], $r); } }
        $id = (int)$el->Add(['IBLOCK_ID' => $ib, 'CODE' => $r['code'], 'NAME' => $r['name'], 'ACTIVE' => $p['active'], 'SORT' => $r['old_sort'],
            'ACTIVE_FROM' => $r['old_active_from'] ? ConvertTimeStamp(ts($r['old_active_from']), 'FULL') : false,
            'PREVIEW_TEXT' => $r['preview_text'], 'PREVIEW_TEXT_TYPE' => $r['preview_text_type'],
            'DETAIL_TEXT' => $r['detail_text'], 'DETAIL_TEXT_TYPE' => $r['detail_text_type'],
            'PREVIEW_PICTURE' => $r['preview_picture'] ? fileArray($src, $r['preview_picture']) : false,
            'DETAIL_PICTURE' => $r['detail_picture'] ? fileArray($src, $r['detail_picture']) : false,
            'PROPERTY_VALUES' => $pv, 'IPROPERTY_TEMPLATES' => $r['seo']], false, true, false) ?: fail("Add {$r['code']}: ".$el->LAST_ERROR);
        file_put_contents("$backupDir/snapshots/$stamp-create-$id.json", json_encode(['action' => 'create', 'id' => $id, 'code' => $r['code']]));
        continue;
    }
    $id = $p['id'];
    $summary['updated']++;
    $summary['rows'][] = ['code' => $r['code'], 'action' => 'update', 'id' => $id, 'fields' => array_keys($p['fields']), 'props' => $p['props'], 'seo' => $p['seo'] !== null];
    if (!$opt['apply']) { continue; }
    file_put_contents("$backupDir/snapshots/$stamp-update-$id.json", json_encode(['action' => 'update', 'id' => $id, 'code' => $r['code'],
        'element' => $p['cur'], 'props' => $p['have'], 'seo' => $p['seoNow']], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $set = [];
    foreach ($p['fields'] as $k => $v) {
        if ($k === 'PREVIEW_PICTURE' || $k === 'DETAIL_PICTURE') {
            keepFile($backupDir, (int)$p['cur'][$k]);
            $set[$k] = $v ? fileArray($src, $v) : ['del' => 'Y'];
        } else { $set[$k] = $v; }
    }
    if ($set && !$el->Update($id, $set, false, true, false, false)) { fail("Update {$r['code']}: ".$el->LAST_ERROR); }
    foreach ($p['props'] as $code) {
        if ($types[$code] === 'F') {
            foreach ($p['have'][$code] ?? [] as $f) { keepFile($backupDir, $f['id']); }
            $vals = $propValue($code, $p['want'], $r);
            if ($vals === false) { // очистить: пометить каждое текущее значение на удаление
                $vals = [];
                $rsv = CIBlockElement::GetProperty($ib, $id, [], ['CODE' => $code]);
                while ($v = $rsv->Fetch()) { if ($v['PROPERTY_VALUE_ID']) { $vals[$v['PROPERTY_VALUE_ID']] = ['VALUE' => ['del' => 'Y']]; } }
            }
            CIBlockElement::SetPropertyValuesEx($id, $ib, [$code => $vals]);
        } else {
            // сначала очистить: иначе Bitrix оставляет совпадающие строки и их прежний порядок
            CIBlockElement::SetPropertyValuesEx($id, $ib, [$code => false]);
            $vals = $propValue($code, $p['want'], $r);
            if ($vals !== false) { CIBlockElement::SetPropertyValuesEx($id, $ib, [$code => $vals]); }
        }
    }
    if ($p['seo'] !== null) { (new ElementTemplates($ib, $id))->set($p['seo']); }
    // проверка записи
    $after = currentProps($ib, $id, $types);
    foreach ($p['props'] as $code) {
        if (!$same($norm($code, $p['want'][$code]), $norm($code, $noIds($after[$code] ?? [])))) { fail("Read-back mismatch: {$r['code']} / $code"); }
    }
}
if ($opt['apply'] && ($summary['created'] || $summary['updated'])) {
    CIBlock::clearIblockTagCache($ib);
    foreach (['news', 'news.detail', 'news.list'] as $c) { BXClearCache(true, "/s1/bitrix/$c/"); }
}
$summary += array_filter($report);
echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
