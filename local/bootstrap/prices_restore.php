<?php
/**
 * Локальная пересборка: цены, которые были на проде в сентябре–октябре 2026, но жили только в БД
 * (ставились через админку, в git их нет). Запускать после импорта b24 и replay.sh.
 *
 * Источники:
 *  A. Rational, EUR (конвертация по курсу ЦБ ×1.05, как на проде) — Б24 #9159, файл
 *     b24/export/files/9159/Rational_для сайта .xlsx; список карточек — комментарий 08.09.2026
 *     (24 пароконвектомата + 7 iVario). iCombi Pro 20-1/1 G на сайте не было. Аксессуары
 *     («пока не добавляли на сайт», 09.09) — без цены.
 *  B. Складские остатки, фикс в RUB — сохранённая страница прода «категория прачечное оборудование.html»
 *     (после 22.10: у FTD-25 цены уже нет), новость «Складской запас…», лендинг прачечной;
 *     Fermest — Б24 #9451 (новые цены 05.10). FTD-25 продана (Б24 #9639) — цены нет;
 *     FWE-60 — цену сняли 20.10 (Б24 #9621) — цены нет.
 *  Уже на месте и здесь не трогаются: «В пути» (v_nalichii_2026_10_15, 24 шт., EUR), Hoshizaki (17 шт., EUR).
 *  Hobart / Halton — цен на проде не было («Запросить стоимость»).
 *
 *   php local/bootstrap/prices_restore.php [--dry]
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = 'localhost';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');
\CModule::IncludeModule('catalog');

const CATALOG_IBLOCK = 11;
$dry = in_array('--dry', $argv, true);

$eur = [
    // iCombi Pro, электро
    'parokonvektomat-rational-icombi-pro-6-1-1-e'        => 11588,
    'parokonvektomat-rational-icombi-pro-xs-6-2-3'       => 9257,
    'parokonvektomat-rational-icombi-pro-6-2-1'          => 17684,
    'parokonvektomat-rational-icombi-pro-10-1-1-e'       => 16281,
    'parokonvektomat-rational-icombi-pro-10-2-1'         => 23520,
    'parokonvektomat-rational-icombi-pro-20-1-1-e'       => 26954,
    'parokonvektomat-rational-icombi-pro-20-2-1'         => 38636,
    // iCombi Classic, электро
    'parokonvektomat-rational-icombi-classic-6-1-1-e'    => 9106,
    'parokonvektomat-rational-icombi-classic-6-2-1'      => 13907,
    'parokonvektomat-rational-icombi-classic-10-1-1'     => 12796,
    'parokonvektomat-rational-icombi-classic-10-2-1'     => 18492,
    'parokonvektomat-rational-icombi-classic-20-1-1-e'   => 23173,
    'parokonvektomat-rational-icombi-classic-20-2-1'     => 33206,
    // iCombi Pro, газ
    'parokonvektomat-rational-icombi-pro-6-1-1-gaz'      => 13810,
    'parokonvektomat-rational-icombi-pro-6-2-1-gaz'      => 20521,
    'parokonvektomat-rational-icombi-pro-10-1-1-gaz'     => 18881,
    'parokonvektomat-rational-icombi-pro-10-2-1-gaz'     => 27286,
    'parokonvektomat-rational-icombi-pro-20-2-1-gaz'     => 43653,
    // iCombi Classic, газ
    'parokonvektomat-rational-icombi-classic-6-1-1-gaz'  => 10854,
    'parokonvektomat-rational-icombi-classic-6-2-1-gaz'  => 16129,
    'parokonvektomat-rational-icombi-classic-10-1-1-gaz' => 14846,
    'parokonvektomat-rational-icombi-classic-10-2-1-gaz' => 21438,
    'parokonvektomat-rational-icombi-classic-20-1-1-gaz' => 26172,
    'parokonvektomat-rational-icombi-classic-20-2-1-gaz' => 37526,
    // iVario (код карточки iVario 2 XS исторически «jipa-jump-251»)
    'elektricheskaya-mnogofunktsionalnaya-skovorodka-jipa-jump-251' => 13811,
    'skovoroda-mnogofunktsionalnaya-rational-ivario-pro-2s'         => 20405,
    'elektroskovoroda-rational-ivario-pro-2-s-s-funktsiey-prigotovleniya-pod-davleniem' => 23863,
    'skovoroda-mnogofunktsionalnaya-rational-ivario-pro-l'          => 23279,
    'skovoroda-mnogofunktsionalnaya-rational-ivario-pro-l-s-funktsiey-prigotovleniya-pod-davleniem' => 26737,
    'skovoroda-mnogofunktsionalnaya-rational-ivario-pro-xl'         => 30645,
    'skovoroda-mnogofunktsionalnaya-rational-ivario-pro-xl-s-funktsiey-prigotovleniya-pod-davleniem' => 35737,
];

$rub = [
    'professionalnaya-stiralnaya-mashina-tolon-twe-18-zagruzka-18-kg'      => 1268730, // старый замок, склад; новая TWE18 — «в пути», EUR
    'professionalnaya-sushilnaya-mashina-tolon-ttd28'                      => 1166564,
    'professionalnyj-gladilnyj-kalandr-tolon-tfi3521'                      => 1974738,
    'sverhskorostnoj-stiralnyj-apparat-fermest-fwe-20'                     => 595000,
    'mnogourovnevaya-stiralno-otzhimnaya-mashina-sushilka-fermest-fwds-10'  => 496200,
    'mnogourovnevaya-stiralno-otzhimnaya-mashina-sushilka-fermest-fwds-15'  => 650000,
    'vysokoeffektivnaya-sushilnaya-mashina-fermest-ftd-20'                 => 340000,
    'mashina-upakovochnaya-dlya-odezhdy-hawo-hp-630-kw'                     => 279400,
    'pyatnovyvodnoj-stol-pony-jolly'                                       => 215900,
    'gladilnyy-stol-s-boylerom-fermest'                                    => 368300,
];

// без цены (сняли на проде)
$noPrice = [
    'vysokoeffektivnaya-sushilnaya-mashina-fermest-ftd-25', // #9639 продана
    'sverhskorostnoj-stiralnyj-apparat-fermest-fwe-60',     // #9621 «убери пока вообще цену»
];

// старое имя с ценой в названии → имя как на проде
$rename = ['pyatnovyvodnoj-stol-pony-jolly' => 'Пятновыводной стол Pony JOLLY'];

function elementId(string $code): int
{
    $r = \CIBlockElement::GetList([], ['IBLOCK_ID' => CATALOG_IBLOCK, '=CODE' => $code], false, false, ['ID'])->Fetch();
    return $r ? (int) $r['ID'] : 0;
}

$stat = ['set' => 0, 'same' => 0, 'missing' => 0, 'removed' => 0];
$apply = function (string $code, float $price, string $cur) use ($dry, &$stat) {
    $id = elementId($code);
    if (!$id) { echo "НЕТ КАРТОЧКИ: $code\n"; $stat['missing']++; return; }
    $pr = \CPrice::GetList([], ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => 1])->Fetch();
    if ($pr && (float) $pr['PRICE'] === $price && $pr['CURRENCY'] === $cur) { $stat['same']++; return; }
    echo ($dry ? '[dry] ' : '') . "ID=$id $code: " . ($pr ? "{$pr['PRICE']} {$pr['CURRENCY']}" : '—') . " → $price $cur\n";
    if ($dry) { return; }
    if (!\CCatalogProduct::GetByID($id)) { \CCatalogProduct::Add(['ID' => $id, 'QUANTITY' => 0]); }
    $f = ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => 1, 'PRICE' => $price, 'CURRENCY' => $cur];
    $pr ? \CPrice::Update((int) $pr['ID'], $f) : \CPrice::Add($f);
    $stat['set']++;
};

foreach ($eur as $code => $p) { $apply($code, (float) $p, 'EUR'); }
foreach ($rub as $code => $p) { $apply($code, (float) $p, 'RUB'); }

foreach ($noPrice as $code) {
    $id = elementId($code);
    if (!$id) { continue; }
    $pr = \CPrice::GetList([], ['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => 1])->Fetch();
    if ($pr && (float) $pr['PRICE'] > 0) {
        echo ($dry ? '[dry] ' : '') . "ID=$id $code: снять цену {$pr['PRICE']} {$pr['CURRENCY']}\n";
        if (!$dry) { \CPrice::Delete((int) $pr['ID']); $stat['removed']++; }
    }
}

foreach ($rename as $code => $name) {
    $id = elementId($code);
    $cur = $id ? \CIBlockElement::GetByID($id)->Fetch()['NAME'] : null;
    if ($id && $cur !== $name) {
        echo ($dry ? '[dry] ' : '') . "ID=$id имя: «{$cur}» → «{$name}»\n";
        if (!$dry) { (new \CIBlockElement())->Update($id, ['NAME' => $name]); }
    }
}

if (!$dry) { \CIBlock::clearIblockTagCache(CATALOG_IBLOCK); BXClearCache(true); }
echo json_encode($stat, JSON_UNESCAPED_UNICODE) . "\n";
