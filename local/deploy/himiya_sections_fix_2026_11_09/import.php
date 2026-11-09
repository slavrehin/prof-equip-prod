<?php
/**
 * Машины TOLON в разделах химии (2026-11-09).
 *
 * При восстановлении import_content.php брал разделы товара и из WP-листингов (extra.listing_sections),
 * где товар мелькал в виджете «В наличии». Поэтому ~26 стиральных/сушильных/барьерных машин и
 * складыватель TOLON оказались в разделах «Стиральные порошки с отбеливателем», «Средства для
 * аквачистки», «Для растворителя Higlo», а у части основным разделом стал «порошки» (самый глубокий).
 *
 * Скрипт отвязывает от разделов корня professionalnaya-himiya те товары, у которых есть разделы
 * из другого корня (настоящая химия — MAJESTIC TOTAL, SUPERWASH и т.п. — не трогается), и, если
 * основной раздел удалён, назначает основным самый глубокий из оставшихся (витрины «в наличии» — в последнюю очередь).
 *
 * Запуск (локально в контейнере, на dev и на проде — из корня сайта, от www-data):
 *   php local/deploy/himiya_sections_fix_2026_11_09/import.php [--dry]
 * Идемпотентен: повторный запуск ничего не меняет.
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
const CHEM_ROOT = 'professionalnaya-himiya';

// дерево разделов: ID => [CODE, DEPTH, ROOT, SHOWCASE]
$secs = [];
$root = null;
$r = \CIBlockSection::GetList(['LEFT_MARGIN' => 'ASC'], ['IBLOCK_ID' => IBLOCK_ID, 'CHECK_PERMISSIONS' => 'N'],
    false, ['ID', 'CODE', 'NAME', 'DEPTH_LEVEL']);
while ($s = $r->Fetch()) {
    if ((int) $s['DEPTH_LEVEL'] === 1) { $root = $s['CODE']; }
    $secs[(int) $s['ID']] = ['CODE' => $s['CODE'], 'NAME' => $s['NAME'], 'DEPTH' => (int) $s['DEPTH_LEVEL'],
        'ROOT' => $root, 'SHOWCASE' => str_contains($s['CODE'], 'v-nalichii')];
}
$chemIds = array_keys(array_filter($secs, fn($s) => $s['ROOT'] === CHEM_ROOT));
if (!$chemIds) { exit("Нет корня " . CHEM_ROOT . "\n"); }

// кандидаты — все элементы, привязанные к разделам химии
$els = [];
$r = \CIBlockElement::GetList([], ['IBLOCK_ID' => IBLOCK_ID, 'SECTION_ID' => $chemIds, 'CHECK_PERMISSIONS' => 'N'],
    false, false, ['ID', 'NAME', 'IBLOCK_SECTION_ID']);
while ($e = $r->Fetch()) { $els[(int) $e['ID']] = $e; }

$fixed = 0;
$el = new \CIBlockElement();
foreach ($els as $id => $e) {
    $cur = [];
    $g = \CIBlockElement::GetElementGroups($id, true, ['ID']);
    while ($x = $g->Fetch()) { if (isset($secs[(int) $x['ID']])) { $cur[] = (int) $x['ID']; } }
    $keep = array_values(array_filter($cur, fn($sid) => $secs[$sid]['ROOT'] !== CHEM_ROOT));
    if (!$keep) { continue; }                 // настоящая химия
    if (count($keep) === count($cur)) { continue; }
    $drop = array_diff($cur, $keep);
    $main = (int) $e['IBLOCK_SECTION_ID'];
    if (!in_array($main, $keep, true)) {
        usort($keep, fn($a, $b) => [$secs[$a]['SHOWCASE'], -$secs[$a]['DEPTH']] <=> [$secs[$b]['SHOWCASE'], -$secs[$b]['DEPTH']]);
        $main = $keep[0];
    }
    printf("%s#%d %s\n   − %s\n   основной: %s\n", $dry ? '[dry] ' : '', $id, $e['NAME'],
        implode(', ', array_map(fn($s) => $secs[$s]['CODE'], $drop)), $secs[$main]['CODE']);
    if (!$dry) {
        if (!$el->Update($id, ['IBLOCK_SECTION' => $keep, 'IBLOCK_SECTION_ID' => $main])) {
            throw new RuntimeException("#$id: " . $el->LAST_ERROR);
        }
    }
    $fixed++;
}
if (!$dry && $fixed) {
    \CIBlock::clearIblockTagCache(IBLOCK_ID);   // фасетный индекс CIBlockElement::Update обновляет сам
}
echo ($dry ? '[dry] ' : '') . "исправлено товаров: $fixed\n";
