<?php
/** Fill missing product SEO only. Default: dry run. Apply: --apply --backup=/backup/file.json */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('iblock');
$iblock = CIBlock::GetList([], ['CODE' => 'catalog'])->Fetch();
if (!$iblock) { throw new RuntimeException('Catalog not found'); }
$ib = (int)$iblock['ID'];
$apply = in_array('--apply', $argv, true);
$backup = '';
foreach ($argv as $arg) { if (str_starts_with($arg, '--backup=')) { $backup = substr($arg, 9); } }
function metaMissing($value): bool {
    $text = mb_strtolower(trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    return in_array($text, ['', 'title', 'description', 'head'], true);
}
$plan = []; $total = 0; $counts = ['title' => 0, 'description' => 0];
$rs = CIBlockElement::GetList(['ID' => 'ASC'], ['IBLOCK_ID' => $ib], false, false, ['ID', 'NAME', 'CODE', 'ACTIVE']);
while ($p = $rs->Fetch()) {
    $total++;
    $values = (new \Bitrix\Iblock\InheritedProperty\ElementValues($ib, (int)$p['ID']))->getValues();
    $name = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($p['NAME']), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($name === '') { throw new RuntimeException('Empty product name: ' . $p['ID']); }
    $set = [];
    if (metaMissing($values['ELEMENT_META_TITLE'] ?? '')) {
        $set['ELEMENT_META_TITLE'] = $name . ' купить в ПРОФЭКВИП'; $counts['title']++;
    }
    if (metaMissing($values['ELEMENT_META_DESCRIPTION'] ?? '')) {
        $set['ELEMENT_META_DESCRIPTION'] = 'Купить ' . $name . ' по выгодной цене в компании Профэквип. Купить с доставкой по России. Заказывайте на сайте или по телефону.';
        $counts['description']++;
    }
    if ($set) {
        $tpl = new \Bitrix\Iblock\InheritedProperty\ElementTemplates($ib, (int)$p['ID']);
        $plan[] = ['product' => $p, 'before_templates' => $tpl->get(), 'before_values' => $values, 'set' => $set];
    }
}
echo json_encode(['total' => $total, 'products_to_change' => count($plan), 'fields' => $counts, 'apply' => $apply], JSON_UNESCAPED_UNICODE) . "\n";
if (!$apply || !$plan) { exit; }
if (!$backup || !is_dir(dirname($backup)) || file_exists($backup)
    || str_starts_with($backup, $_SERVER['DOCUMENT_ROOT'] . '/')) {
    throw new RuntimeException('A new backup path outside document root is required');
}
$payload = json_encode(['iblock' => $ib, 'created' => date(DATE_ATOM), 'plan' => $plan], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
$fp = fopen($backup, 'x');
if (!$fp) { throw new RuntimeException('Cannot create backup'); }
chmod($backup, 0600);
if (fwrite($fp, $payload) !== strlen($payload)) { throw new RuntimeException('Incomplete backup'); }
fclose($fp);
echo "Backup: $backup\n";
foreach ($plan as $change) {
    $id = (int)$change['product']['ID'];
    (new \Bitrix\Iblock\InheritedProperty\ElementTemplates($ib, $id))->set($change['set']);
    $entity = new \Bitrix\Iblock\InheritedProperty\ElementValues($ib, $id);
    $entity->clearValues();
    $actual = $entity->getValues();
    foreach ($change['set'] as $key => $value) {
        if (($actual[$key] ?? null) !== $value) { throw new RuntimeException("Verification failed: $id $key"); }
    }
    foreach ($change['before_values'] as $key => $value) {
        if (!array_key_exists($key, $change['set']) && ($actual[$key] ?? null) !== $value) {
            throw new RuntimeException("Unchanged field mismatch: $id $key");
        }
    }
}
$GLOBALS['CACHE_MANAGER']->ClearByTag('iblock_id_' . $ib);
\Bitrix\Main\Data\Cache::createInstance()->cleanDir('/');
echo 'Verified products: ' . count($plan) . "\n";
