<?php
/**
 * Локальная пересборка: новости (инфоблок 4) — даты, порядок, обложки, новость о складском запасе.
 *
 * Проблема после импорта: у ~27 новостей пустой ACTIVE_FROM, у остальных — дата импорта (16.12.2025 / 25.02.2026),
 * поэтому порядок в /novosti/ случайный, новости про склад/наличие уехали вниз; у 29 новостей нет обложки.
 *
 * Источники:
 *  - data/news_fix/prod_list_20260521.tsv — список /novosti/ с прода (веб-архив 21.05.2026): порядок, день+месяц
 *    (год восстановлен по убыванию). Последние 21 новость на проде были без даты — у нас тоже без даты,
 *    а их порядок задаётся SORT 501+ (на проде они стояли в конце списка в этом же порядке).
 *  - 3 новости на проде переименованы (краул 02.07.2026) — меняем CODE, старый адрес → редирект (инфоблок redirect).
 *  - «Прачечное оборудование TOLON: стабильные поставки из Турции» — была на проде (без текста, только картинка).
 *  - Обложки: WP-картинки, на которые ссылались превью этих новостей (веб-архив), — только для новостей без обложки.
 *  - «Складской запас оборудования для прачечных…» (rasprodazha-…): дата 22.05.2026 (datePublished на проде),
 *    обложка и баннер из Б24 #9451 (05.10.2026), состав карточек: снимок 13.06 + #9101/#9229 (TTD28, TRD23, TFI3521,
 *    TWE24 с ценами из чек-листа), цены и «с НДС» по сохранённой странице прода (окт. 2026; #9089 «без НДС» позже отменено), ссылка на гладильный стол #9229,
 *    FTD-25 продан → «Получить КП» без плашки #9639. Картинки карточек — фото товаров из каталога
 *    (оригиналы /upload/medialibrary/ потеряны), FWDS-10/15 — фото колонны из Б24 #8599.
 *
 *   php local/bootstrap/news_fix.php [--dry]
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = 'localhost';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

$dry = in_array('--dry', $argv, true);
$data = __DIR__ . '/data/news_fix';
$newsIb = (int)GetIBlockIDByCode('news');
$redirIb = (int)GetIBlockIDByCode('redirect');
$el = new CIBlockElement();

function newsByCode(int $ib, string $code): ?array {
    $r = CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, '=CODE' => $code], false, false,
        ['ID', 'CODE', 'NAME', 'ACTIVE_FROM', 'SORT', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'DETAIL_TEXT']);
    return $r->Fetch() ?: null;
}
function upd(CIBlockElement $el, int $id, array $f, bool $dry, string $what): void {
    echo "  #$id $what\n";
    if ($dry) { return; }
    if (!$el->Update($id, $f, false, false, true, false)) { echo "    ошибка: {$el->LAST_ERROR}\n"; }
}

// ---------- 1. переименования (прод, краул 02.07.2026) ----------
$renames = [
    'profekvip-na-cleanexpo-moscow-2025-2' => 'cleanexpo-moscow-2025-sobstvennaya-khimiya-fermest-i-prachechnoe-oborudovanie',
    'profekvip-na-pir-expo-2025-vnimanie-k-brendu-fresco-maggiore-i-novye-delovye-ko' => 'pir-expo-2025-prezentatsiya-fresco-maggiore-i-vsekh-kompetentsiy-profekvip',
    'profekvip-na-texcare-asia-china-laundry-expo-2025-v-tsentre-mirovyh-trendov-industrii' => 'profekvip-na-texcare-asia-china-laundry-expo-2025-v-tsentre-mirovykh-trendov-industrii',
];
echo "== переименования\n";
foreach ($renames as $old => $new) {
    if ($n = newsByCode($newsIb, $old)) {
        upd($el, (int)$n['ID'], ['CODE' => $new], $dry, "$old → $new");
    }
    $from = "/novosti/$old/";
    $has = CIBlockElement::GetList([], ['IBLOCK_ID' => $redirIb, 'PROPERTY_FROM' => $from], false, ['nTopCount' => 1], ['ID'])->Fetch();
    if (!$has) {
        echo "  редирект $from\n";
        if (!$dry) {
            $el->Add(['IBLOCK_ID' => $redirIb, 'NAME' => $from, 'ACTIVE' => 'Y',
                'PROPERTY_VALUES' => ['FROM' => $from, 'WHERE' => "/novosti/$new/"]]);
        }
    }
}

// ---------- 2. недостающая новость ----------
echo "== недостающие\n";
$turcii = 'prachechnoe-oborudovanie-tolon-stabilnye-postavki-iz-turcii';
if (!newsByCode($newsIb, $turcii)) {
    echo "  + $turcii\n";
    if (!$dry) {
        $pic = CFile::MakeFileArray("$data/$turcii.jpg");
        $id = $el->Add(['IBLOCK_ID' => $newsIb, 'ACTIVE' => 'Y', 'CODE' => $turcii, 'SORT' => 500,
            'NAME' => 'Прачечное оборудование TOLON: стабильные поставки из Турции',
            'PREVIEW_PICTURE' => $pic, 'DETAIL_PICTURE' => $pic, 'DETAIL_TEXT_TYPE' => 'html', 'DETAIL_TEXT' => '']);
        if (!$id) { echo "    ошибка: {$el->LAST_ERROR}\n"; }
    }
}

// ---------- 3. даты и порядок как на проде ----------
echo "== даты/порядок\n";
$rows = array_map(fn($l) => explode("\t", rtrim($l, "\n")), file("$data/prod_list_20260521.tsv"));
$perDay = [];
foreach ($rows as $i => [$code, $date]) {
    $n = newsByCode($newsIb, $code);
    if (!$n) { echo "  нет в БД: $code\n"; continue; }
    if ($date !== '') {
        // в один день — у стоящей выше в списке время позже (news-detail-nav сравнивает строго)
        $k = $perDay[$date] = ($perDay[$date] ?? 0) + 1;
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        $af = ConvertTimeStamp(mktime(12, 60 - $k, 0, $m, $d, $y), 'FULL');
        $sort = 500;
    } else {
        $af = '';
        $sort = 501 + $i;
    }
    if ((string)$n['ACTIVE_FROM'] !== $af || (int)$n['SORT'] !== $sort) {
        upd($el, (int)$n['ID'], ['ACTIVE_FROM' => $af, 'SORT' => $sort], $dry,
            "$code: '{$n['ACTIVE_FROM']}'/{$n['SORT']} → '$af'/$sort");
    }
}

// ---------- 4. обложки из WP (только где пусто) ----------
echo "== обложки\n";
foreach (glob("$data/*.{jpg,jpeg,png,webp}", GLOB_BRACE) as $f) {
    $code = pathinfo($f, PATHINFO_FILENAME);
    $n = newsByCode($newsIb, $code);
    if (!$n || $n['PREVIEW_PICTURE']) { continue; }
    upd($el, (int)$n['ID'], ['PREVIEW_PICTURE' => CFile::MakeFileArray($f)], $dry, "$code ← " . basename($f));
}

// ---------- 5. «Складской запас оборудования для прачечных» ----------
echo "== складской запас\n";
$skladCode = 'rasprodazha-oborudovaniya-dlya-prachechnykh-skladskoy-zapas-po-spetstsenam';
$sklad = newsByCode($newsIb, $skladCode);
if ($sklad) {
    $photo = function (string $productCode): string {
        $p = CIBlockElement::GetList([], ['IBLOCK_ID' => 11, '=CODE' => $productCode], false, false, ['PREVIEW_PICTURE'])->Fetch();
        return $p && $p['PREVIEW_PICTURE'] ? (string)CFile::GetPath($p['PREVIEW_PICTURE']) : '';
    };
    // картинка FWDS из Б24 #8599 → /upload/news_fix/
    $fwds = '/upload/news_fix/fermest_fwds.png';
    if (!$dry) {
        @mkdir($_SERVER['DOCUMENT_ROOT'] . '/upload/news_fix', 0775, true);
        copy("$data/fermest_fwds.png", $_SERVER['DOCUMENT_ROOT'] . $fwds);
    }
    $tolonTwe = $photo('professionalnaya-stiralnaya-mashina-tolon-twe-18-zagruzka-18-kg');
    // цены и «с НДС» — сохранённая страница прода (после 05.10, #9451); FTD-25 продана (#9639) → «Получить КП»
    // [заголовок, тип, характеристики, цена (null = «Получить КП»), плашка, ссылка-код, картинка]
    $cards = [
        ['TOLON TWE 18', 'Профессиональная стиральная машина', '📦 Загрузка 18 кг 🔥 Комбинированный нагрев (электричество/пар) 🔒 Электромеханический замок', '1 268 730', '✅ В наличии в Москве', 'professionalnaya-stiralnaya-mashina-tolon-twe-18-zagruzka-18-kg', $tolonTwe],
        ['FERMEST FTD-20', 'Высокоэффективная сушильная машина', '📦 Загрузка 20 кг ⚡ Электрический нагрев 🔄 Реверс барабана', '340 000', '✅ В наличии в Москве', 'vysokoeffektivnaya-sushilnaya-mashina-fermest-ftd-20', null],
        ['FERMEST FTD-25', 'Высокоэффективная сушильная машина', '📦 Загрузка 25 кг ⚡ Электрический нагрев 🔄 Реверс барабана', null, '', 'vysokoeffektivnaya-sushilnaya-mashina-fermest-ftd-25', null],
        ['FERMEST FWE-20', 'Сверхскоростной стиральный аппарат', '📦 Загрузка 20 кг ⚡ Электрический нагрев ⚡ G-фактор 400', '595 000', '✅ В наличии в Москве', 'sverhskorostnoj-stiralnyj-apparat-fermest-fwe-20', null],
        ['FERMEST FWDS-10', 'Стирально-отжимная машина-сушилка', '👕 Стирка 10 кг 🔥 Сушка 10 кг ⚡ Электрический нагрев', '496 200', '✅ В наличии в Москве', 'mnogourovnevaya-stiralno-otzhimnaya-mashina-sushilka-fermest-fwds-10', $fwds],
        ['FERMEST FWDS-15', 'Стирально-отжимная машина-сушилка', '👕 Стирка 15 кг 🔥 Сушка 15 кг ⚡ Электрический нагрев', '650 000', '✅ В наличии в Москве', 'mnogourovnevaya-stiralno-otzhimnaya-mashina-sushilka-fermest-fwds-15', $fwds],
        // #9101/#9229 (характеристики — из свойств каталога)
        ['TOLON TWE 24', 'Профессиональная стиральная машина', '📦 Загрузка 24 кг 🔥 Нагрев: электричество/пар', '1 874 000', '✅ В наличии в Москве', 'professionalnaya-stiralnaya-mashina-tolon-twe-24-zagruzka-24-kg', $tolonTwe],
        ['TOLON TTD28', 'Профессиональная сушильная машина', '📦 Загрузка 28 кг ⚡ Электрический нагрев', '1 166 564', '✅ В наличии в Москве', 'professionalnaya-sushilnaya-mashina-tolon-ttd28', null],
        ['TOLON TRD23', 'Профессиональная сушильная машина', '🔥 Нагрев: газ / пар / электричество', '888 390', '✅ В наличии в Москве', 'professionalnaya-sushilnaya-mashina-tolon-trd23', null],
        ['TOLON TFI3521', 'Гладильный каландр', '📏 Вал Ø350 мм, длина 2100 мм', '1 974 738', '✅ В наличии в Москве', 'professionalnyj-gladilnyj-kalandr-tolon-tfi3521', null],
        ['PONY JOLLY', 'Пятновыводной стол', '🇮🇹 Италия 🔌 220/380 В ⚙️ 0,25 кВт', '215 900', '✅ В наличии в Москве', 'pyatnovyvodnoj-stol-pony-jolly', null],
        ['HAWO HP 630 KW', 'Упаковочная машина для одежды', '🇩🇪 Германия 📏 810×370×560 мм 🔌 220 В', '279 400', '✅ В наличии в Москве', 'mashina-upakovochnaya-dlya-odezhdy-hawo-hp-630-kw', null],
        ['FERMEST Гладильный стол с бойлером', 'Выставочный образец', '🔥 С бойлером ⭐ Демонстрационный экземпляр ✅ Отличное состояние', '368 300', '🎯 Выставочный образец', 'gladilnyy-stol-s-boylerom-fermest', null],
    ];
    $h = fn($s) => htmlspecialchars($s, ENT_QUOTES);
    $html = '';
    foreach (array_chunk($cards, 3) as $group) {
        $html .= "<div class=\"news-group\"><div class=\"news-cards\">\n";
        foreach ($group as [$title, $type, $specs, $price, $stock, $code, $img]) {
            $img = $img ?: $photo($code);
            $html .= "<div class=\"news-card\">"
                . "<div class=\"news-card-image\"><img alt=\"{$h($title)}\" src=\"{$h($img)}\" title=\"{$h($title)}\"/></div>"
                . "<div class=\"news-card-title\">{$h($title)}</div>"
                . "<div class=\"news-card-type\">{$h($type)}</div>"
                . "<div class=\"news-specs\">{$h($specs)}</div>"
                . ($price !== null
                    ? "<div class=\"news-price\">$price ₽ <small>с НДС</small></div>"
                    : "<div class=\"news-price\">Получить КП</div>")
                . ($stock ? "<div class=\"news-stock\">{$h($stock)}</div>" : '')
                . "<a class=\"news-link\" href=\"/product/$code/\" rel=\"noopener\" target=\"_blank\">Подробнее →</a>"
                . "</div>\n";
        }
        $html .= "</div></div>\n";
    }
    $text = (string)$sklad['DETAIL_TEXT'];
    $start = strpos($text, '<!-- ГРУППА 1');
    $end = strpos($text, '<div class="news-outro">');
    if ($start === false || $end === false) {
        // уже пересобрана: между вступлением и outro — первый news-group
        $start = strpos($text, '<div class="news-group">');
    }
    if ($start !== false && $end !== false) {
        $newText = substr($text, 0, $start) . $html . substr($text, $end);
        // на проде стили блока лежали <style> внутри .news-sale (импорт контента теги style вырезал)
        if (strpos($newText, '<style') === false) {
            $css = file_get_contents("$data/news_sale.css");
            $newText = preg_replace('~<div class="news-sale">~', "<div class=\"news-sale\">\n<style>\n$css</style>\n", $newText, 1);
        }
        $fields = ['DETAIL_TEXT' => $newText, 'DETAIL_TEXT_TYPE' => 'html', 'ACTIVE_FROM' => '22.05.2026 12:00:00', 'SORT' => 500];
        if (!$sklad['PREVIEW_PICTURE'] || in_array('--force', $argv, true)) {
            $fields['PREVIEW_PICTURE'] = CFile::MakeFileArray("$data/sklad_cover.png");
            $fields['DETAIL_PICTURE'] = CFile::MakeFileArray("$data/sklad_banner.png");
        }
        upd($el, (int)$sklad['ID'], $fields, $dry, 'текст: ' . count($cards) . ' карточек, дата 22.05.2026, обложка/баннер Б24 #9451');
    } else {
        echo "  не нашёл границы блока карточек — пропуск\n";
    }
}

if (!$dry) {
    \CIBlock::clearIblockTagCache($newsIb);
    BXClearCache(true, '/');
}
echo "done\n";
