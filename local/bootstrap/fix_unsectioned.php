<?php
// Товары из Битрикс24 без раздела: запчасти — в раздел запчастей по оборудованию (кухня/прачечная), прочее — отчёт.
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('iblock');
global $DB;
$ib = (int) CIBlock::GetList([], ['CODE' => 'catalog', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$sec = fn($c) => (int) CIBlockSection::GetList([], ['IBLOCK_ID' => $ib, '=CODE' => $c], false, ['ID'])->Fetch()['ID'];
$kitchen = $sec('zapasnye-chasti-dlya-kuhonnogo-oborudovaniya');
$laundry = $sec('zapasnye-chasti-dlya-prachechnogo-oborudovaniya');
$kitchenRe = '/FAMA|FIMAR|KROMO|TAS\b|Klarco|Rational|iCombi|COOLMES|HG-\d|GENUS|овощечист|пароконвект|плит|фритюр|посудомо|Winterhalter|Hobart/ui';
$laundryRe = '/TOLON|IMESA|Danube|JENSEN|Electrolux|Girbau|Pony|Macpi|стираль|сушил|гладил|каток|каландр|WE\d|TWE|TTD|TRD/ui';
$r = $DB->Query("SELECT ID, NAME FROM b_iblock_element WHERE IBLOCK_ID=$ib AND (IBLOCK_SECTION_ID IS NULL OR IBLOCK_SECTION_ID=0) AND XML_ID LIKE 'b24:%'");
$n = ['kitchen' => 0, 'laundry' => 0, 'left' => []];
while ($e = $r->Fetch()) {
    $isPart = preg_match('/запчаст|для |датчик|плата|двигател|ремень|клапан|тэн|трубк|шлейф|прокладк|фильтр|подшипник|диск|держател|датчик|реле|выключат|термостат|насос|уплотн/ui', $e['NAME']);
    $to = preg_match($kitchenRe, $e['NAME']) ? $kitchen : (preg_match($laundryRe, $e['NAME']) ? $laundry : 0);
    if (!$isPart || !$to) { $n['left'][] = $e['NAME']; continue; }
    CIBlockElement::SetElementSection($e['ID'], [$to], false, 0, $to);
    $DB->Query("UPDATE b_iblock_element SET IBLOCK_SECTION_ID=$to WHERE ID={$e['ID']}");
    $n[$to === $kitchen ? 'kitchen' : 'laundry']++;
}
echo "в запчасти кухни: {$n['kitchen']}, прачечной: {$n['laundry']}, осталось без раздела: " . count($n['left']) . "\n";
foreach (array_slice($n['left'], 0, 15) as $x) echo "  - $x\n";
