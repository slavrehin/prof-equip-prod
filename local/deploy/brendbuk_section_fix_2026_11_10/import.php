<?php
/**
 * Лишние товары в разделе «Сетевые отели (бренд-бук)» (2026-11-10).
 *
 * import_content.php взял разделы товаров из WP-карточек (WooCommerce product_cat), где в
 * setevye-oteli-brend-buk были отмечены и обычные позиции Standard Textile, тапочки и ткани.
 * В разделе должен остаться только текстиль под стандарт конкретной сети (в названии есть сеть:
 * готовое бельё / полотенца «для отелей <Сеть>», HANSE Marriott/Renaissance, халат Four Points,
 * серии ACCOR / Sheraton / Marriott / Radisson Blu). Остальное отвязываем от раздела.
 *
 * Если у товара не остаётся разделов (ткань Euroval) — кладём к остальным тканям
 * (postelnoe-bele + pododeyalniki). Если удалён основной раздел — основным становится самый
 * глубокий из оставшихся.
 *
 * Запуск (локально, на dev и на проде — из корня сайта, от www-data):
 *   php local/deploy/brendbuk_section_fix_2026_11_10/import.php [--dry]
 * Идемпотентен.
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
const IBLOCK_ID = 11;
const SECTION = 'setevye-oteli-brend-buk';
const FALLBACK = ['postelnoe-bele', 'pododeyalniki'];   // последний — основной

$remove = [
    'polotentse-mahrovoe-dlya-nog-1400-g-m-kv-100-hb-glad-standard-textile',
    'polotentse-mahrovoe-dlya-nog-650-g-m-kv-100-hb-polosy-standard-textile',
    'polotentse-mahrovoe-dlya-nog-650-g-m-kv-100-hb-polosy-standard-textile-2',
    'polotentse-mahrovoe-dlya-nog-700-g-m-kv-100-hb-polosy-standard-textile',
    'polotentse-mahrovoe-portofino-570-g-m-kv-100-hb-volna-standard-textile',
    'salfetka-mahrovaya-400-g-m-kv-100-hb-glad-standard-textile',
    'salfetka-mahrovaya-450-g-m-kv-100-hb-glad-standard-textile',
    'salfetka-mahrovaya-550-g-m-kv-100-hb-glad-standard-textile',
    'tapochki-mahrovye-dlya-otelya-odnorazovye-015r-rossiya',
    'tapochki-velyurovye-dlya-otelya-odnorazovye-015r-rossiya',
    'tkan-euroval-prestige-evroval-prestizh-dlya-postelnogo-belya-60-hlopok-125-g-m-kv-ts160-ref-175-standard-textile',
    'tkan-polisatin-strajp-2x2-dlya-postelnogo-belya-50-hlopok-145-g-m-kv-ts300-ref-420-standard-textile',
    'tkan-polisatin-strajp-8x8-dlya-postelnogo-belya-70-hlopok-120-g-m-kv-ts150-ref-408-standard-textile',
    'tkan-satin-dlya-postelnogo-belya-60-hlopok-135-g-m-kv-ts300-ref-300-standard-textile',
];

$secs = [];
$byCode = [];
$r = \CIBlockSection::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'CHECK_PERMISSIONS' => 'N'], false,
    ['ID', 'CODE', 'DEPTH_LEVEL']);
while ($s = $r->Fetch()) {
    $secs[(int) $s['ID']] = ['CODE' => $s['CODE'], 'DEPTH' => (int) $s['DEPTH_LEVEL'],
        'SHOWCASE' => str_contains($s['CODE'], 'v-nalichii')];
    $byCode[$s['CODE']] = (int) $s['ID'];
}
foreach (array_merge([SECTION], FALLBACK) as $c) {
    if (!isset($byCode[$c])) { exit("Нет раздела $c\n"); }
}
$target = $byCode[SECTION];

$fixed = 0;
$el = new \CIBlockElement();
$r = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, '=CODE' => $remove, 'CHECK_PERMISSIONS' => 'N'],
    false, false, ['ID', 'CODE', 'NAME', 'IBLOCK_SECTION_ID']);
$found = 0;
while ($e = $r->Fetch()) {
    $found++;
    $id = (int) $e['ID'];
    $cur = [];
    $g = \CIBlockElement::GetElementGroups($id, true, ['ID']);
    while ($x = $g->Fetch()) { $cur[] = (int) $x['ID']; }
    if (!in_array($target, $cur, true)) { continue; }
    $keep = array_values(array_diff($cur, [$target]));
    $main = (int) $e['IBLOCK_SECTION_ID'];
    if (!$keep) {
        $keep = array_map(fn($c) => $byCode[$c], FALLBACK);
        $main = end($keep);
    } elseif (!in_array($main, $keep, true)) {
        usort($keep, fn($a, $b) => [$secs[$a]['SHOWCASE'], -$secs[$a]['DEPTH']] <=> [$secs[$b]['SHOWCASE'], -$secs[$b]['DEPTH']]);
        $main = $keep[0];
    }
    printf("%s#%d %s\n   остаётся в: %s; основной: %s\n", $dry ? '[dry] ' : '', $id, $e['NAME'],
        implode(', ', array_map(fn($s) => $secs[$s]['CODE'], $keep)), $secs[$main]['CODE']);
    if (!$dry && !$el->Update($id, ['IBLOCK_SECTION' => $keep, 'IBLOCK_SECTION_ID' => $main])) {
        throw new RuntimeException("#$id: " . $el->LAST_ERROR);
    }
    $fixed++;
}
if (!$dry && $fixed) {
    \CIBlock::clearIblockTagCache(IBLOCK_ID);
}
echo ($dry ? '[dry] ' : '') . "найдено: $found из " . count($remove) . ", отвязано от " . SECTION . ": $fixed\n";
