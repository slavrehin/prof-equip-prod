<?php
/**
 * Локальная пересборка: возвращает товарам каталога привязки к подразделам, потерянные при импорте.
 *
 * Причина: import_content.php брал разделы из архивной карточки товара, а у многих карточек
 * в веб-архиве были только корневые крошки. В итоге товар лежал лишь в корне (напр. 18 из 24 iCombi
 * не попадали в /product-category/parokonvektomaty-rational/).
 *
 * 1. Все пароконвектоматы Rational iCombi → parokonvektomaty-rational + parokonvektomaty + teplovoe-oborudovanie
 *    (так привязаны 6 уцелевших; WP-категория на проде показывала 16 на 1-й стр. + 2-ю страницу).
 * 2. Остальные активные товары без подраздела → разделы из data/wp_category_map.json
 *    (код товара → WP-категории со страниц product-category/* в веб-архиве, включая /page/N/).
 *    Берутся только разделы, существующие в инфоблоке (по последнему сегменту кода).
 *
 * Только добавляет привязки, ничего не снимает. Повторный запуск безопасен.
 *   php local/bootstrap/restore_sections.php [--dry]
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
$_SERVER['SERVER_NAME'] = 'localhost';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('iblock');
global $DB;

$dry = in_array('--dry', $argv, true);
$ib = 11;

$sections = []; // CODE => [ID, DEPTH]
$r = CIBlockSection::GetList([], ['IBLOCK_ID' => $ib, 'CHECK_PERMISSIONS' => 'N'], false, ['ID', 'CODE', 'DEPTH_LEVEL']);
while ($s = $r->Fetch()) { $sections[$s['CODE']] = [(int) $s['ID'], (int) $s['DEPTH_LEVEL']]; }
$depth = [];
foreach ($sections as [$id, $d]) { $depth[$id] = $d; }

$groups = function (int $id): array {
    $ids = [];
    $g = CIBlockElement::GetElementGroups($id, true, ['ID']);
    while ($x = $g->Fetch()) { $ids[] = (int) $x['ID']; }
    return $ids;
};

$stat = ['rational' => 0, 'map' => 0, 'map_no_section' => 0, 'left_root_only' => 0];
$apply = function (array $el, array $add, string $kind) use ($groups, $depth, $dry, &$stat, $DB) {
    $cur = $groups((int) $el['ID']);
    $new = array_values(array_unique(array_merge($cur, $add)));
    // главный раздел: если сейчас корень — самый глубокий из добавленных
    $main = (int) $el['IBLOCK_SECTION_ID'];
    if (!$main || ($depth[$main] ?? 1) <= 1) {
        usort($add, fn($a, $b) => ($depth[$b] ?? 0) <=> ($depth[$a] ?? 0));
        $main = $add[0];
    }
    if (count($new) === count($cur) && $main === (int) $el['IBLOCK_SECTION_ID']) { return; }
    echo ($dry ? '[dry] ' : '') . "$kind {$el['CODE']}: +" . implode(',', array_diff($new, $cur)) . " main=$main\n";
    $stat[$kind]++;
    if ($dry) { return; }
    CIBlockElement::SetElementSection((int) $el['ID'], $new, false, 0, $main);
    $DB->Query('UPDATE b_iblock_element SET IBLOCK_SECTION_ID=' . (int) $main . ' WHERE ID=' . (int) $el['ID']);
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(11, (int) $el['ID']);
};

// 1. Rational iCombi
$rat = array_map(fn($c) => $sections[$c][0], ['parokonvektomaty-rational', 'parokonvektomaty', 'teplovoe-oborudovanie']);
$r = CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, 'CODE' => 'parokonvektomat-rational-icombi-%', 'CHECK_PERMISSIONS' => 'N'], false, false, ['ID', 'CODE', 'IBLOCK_SECTION_ID']);
while ($el = $r->Fetch()) { $apply($el, $rat, 'rational'); }

// 2. Товары только в корне → карта WP-категорий
$map = json_decode(file_get_contents(__DIR__ . '/data/wp_category_map.json'), true);
$r = $DB->Query("SELECT e.ID, e.CODE, e.IBLOCK_SECTION_ID FROM b_iblock_element e
    WHERE e.IBLOCK_ID=$ib AND e.ACTIVE='Y' AND NOT EXISTS (
      SELECT 1 FROM b_iblock_section_element se JOIN b_iblock_section s ON s.ID=se.IBLOCK_SECTION_ID
      WHERE se.IBLOCK_ELEMENT_ID=e.ID AND s.DEPTH_LEVEL>1)");
$left = [];
while ($el = $r->Fetch()) {
    $add = [];
    foreach ($map[$el['CODE']] ?? [] as $cat) {
        $code = basename(str_replace('_', '/', $cat));
        if (isset($sections[$code]) && $sections[$code][1] > 1) { $add[] = $sections[$code][0]; }
    }
    if (!$add) {
        $stat[isset($map[$el['CODE']]) ? 'map_no_section' : 'left_root_only']++;
        $left[] = $el['CODE'];
        continue;
    }
    $apply($el, array_values(array_unique($add)), 'map');
}
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/local/bootstrap/data/root_only_left.txt', implode("\n", $left) . "\n");

if (!$dry) {
    BXClearCache(true);
    $GLOBALS['CACHE_MANAGER']->CleanAll();
}
print_r($stat);
