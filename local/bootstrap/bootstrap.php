<?php
/**
 * Воссоздание структуры БД prof-equip.ru на чистой установке Битрикса (боевая БД потеряна 2026-11-02).
 * Состояние «как у агентства до 01.09»: дальше поверх идут контент из архива, затем local/migrations/run.php.
 *
 * Идемпотентно: всё ищется по CODE/SID. Зашитые в коде ID (инфоблоки 1/4/8/11/14/23, корневые разделы
 * каталога 1/2/4/8/120, формы 1–3, тип цены 1) выставляются через AUTO_INCREMENT перед созданием.
 *
 * Запуск (из site/):  docker compose exec -T web php /var/www/html/local/bootstrap/bootstrap.php
 * Спецификация собрана по коду шаблона и миграций (см. разбор в сессии 2026-11-06).
 */
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_NO_ACCELERATOR_RESET', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;

foreach (['iblock', 'catalog', 'currency', 'form', 'highloadblock'] as $m) {
    if (!Loader::includeModule($m)) {
        throw new RuntimeException("Модуль $m не установлен");
    }
}
global $DB;
const SITE = 's1';

function out($s) { echo $s, "\n"; }

function setAutoIncrement($table, $id)
{
    global $DB;
    $max = (int) $DB->Query("SELECT IFNULL(MAX(ID),0) M FROM $table")->Fetch()['M'];
    if ($id > $max) {
        $DB->Query("ALTER TABLE $table AUTO_INCREMENT = " . (int) $id);
    }
    return $id > $max;
}

/* ---------------------------------------------------------------- типы инфоблоков */
$types = ['catalog' => 'Каталог', 'content' => 'Контент', 'home' => 'Главная', 'seo' => 'SEO'];
foreach ($types as $tid => $tname) {
    if (!CIBlockType::GetByID($tid)->Fetch()) {
        $t = new CIBlockType();
        $ok = $t->Add(['ID' => $tid, 'SECTIONS' => 'Y', 'IN_RSS' => 'N', 'SORT' => 100,
            'LANG' => ['ru' => ['NAME' => $tname, 'SECTION_NAME' => 'Разделы', 'ELEMENT_NAME' => 'Элементы']]]);
        if (!$ok) throw new RuntimeException("Тип $tid: " . $t->LAST_ERROR);
        out("тип инфоблока $tid создан");
    }
}

/* ---------------------------------------------------------------- инфоблоки
 * ID => [CODE, тип, NAME, LIST_PAGE_URL, SECTION_PAGE_URL, DETAIL_PAGE_URL]
 * Порядок важен: ID 1/4/8/11/14/16/23 зашиты в коде, остальные заполняют промежутки. */
$iblocks = [
    1  => ['contacts', 'content', 'Контакты', '', '', ''],
    2  => ['services', 'content', 'Услуги', '/', '', '/#ELEMENT_CODE#/'],
    3  => ['faq', 'content', 'FAQ', '', '', ''],
    4  => ['news', 'content', 'Новости', '/novosti/', '', '/novosti/#ELEMENT_CODE#/'],
    5  => ['steps', 'content', 'Этапы работ', '', '', ''],
    6  => ['reviews', 'content', 'Отзывы', '', '', ''],
    7  => ['banners', 'content', 'Баннеры', '', '', ''],
    8  => ['projects', 'content', 'Проекты (портфолио)', '/portfolio/', '', '/portfolio/#ELEMENT_CODE#/'],
    9  => ['solution', 'content', 'Готовые решения', '/gotovye-resheniya/', '', '/gotovye-resheniya/#ELEMENT_CODE#/'],
    10 => ['directionshome', 'home', 'Направления (главная)', '', '', ''],
    11 => ['catalog', 'catalog', 'Каталог', '/', '/product-category/#SECTION_CODE#/', '/product/#ELEMENT_CODE#/'],
    12 => ['herohome', 'home', 'Главная: первый экран', '', '', ''],
    13 => ['brandhome', 'home', 'Главная: клиенты и бренды', '', '', ''],
    14 => ['brands', 'content', 'Бренды', '/brends/', '', '/brends/#ELEMENT_CODE#/'],
    15 => ['advantages', 'home', 'Преимущества', '', '', ''],
    16 => ['textile', 'content', 'Направление: Текстиль', '', '', ''],
    17 => ['workstages', 'home', 'Этапы работы (главная)', '', '', ''],
    18 => ['aboutpage', 'content', 'О компании', '', '', ''],
    19 => ['aboutlist', 'content', 'О компании: цифры', '', '', ''],
    20 => ['partneram', 'content', 'Партнёрам', '', '', ''],
    21 => ['seoparams', 'content', 'SEO-параметры страниц', '', '', ''],
    22 => ['redirect', 'content', 'Редиректы', '', '', ''],
    23 => ['blog', 'content', 'Блог', '/blog/', '', '/blog/#ELEMENT_CODE#/'],
    24 => ['kuhnya', 'content', 'Направление: Кухня', '', '', ''],
    25 => ['chemistry', 'content', 'Направление: Химия', '', '', ''],
    26 => ['equipment', 'content', 'Направление: Оснащение', '', '', ''],
    27 => ['furniture', 'content', 'Направление: Мебель', '', '', ''],
    28 => ['prachechnaya', 'content', 'Направление: Прачечная', '', '', ''],
];
$IB = [];
foreach ($iblocks as $wantId => [$code, $type, $name, $list, $sect, $detail]) {
    $row = CIBlock::GetList([], ['CODE' => $code, 'CHECK_PERMISSIONS' => 'N'])->Fetch();
    if (!$row) {
        if (!setAutoIncrement('b_iblock', $wantId)) {
            out("ВНИМАНИЕ: ID $wantId для $code уже занят, получит другой");
        }
        $ib = new CIBlock();
        $id = $ib->Add([
            'ACTIVE' => 'Y', 'NAME' => $name, 'CODE' => $code, 'API_CODE' => null, 'IBLOCK_TYPE_ID' => $type,
            'SITE_ID' => [SITE], 'SORT' => 500, 'VERSION' => 1, 'INDEX_ELEMENT' => 'N', 'INDEX_SECTION' => 'N',
            'LIST_PAGE_URL' => $list ?: '', 'SECTION_PAGE_URL' => $sect, 'DETAIL_PAGE_URL' => $detail,
            'GROUP_ID' => ['1' => 'X', '2' => 'R'], 'WORKFLOW' => 'N', 'BIZPROC' => 'N',
            'FIELDS' => ['CODE' => ['IS_REQUIRED' => 'N', 'DEFAULT_VALUE' => ['UNIQUE' => 'Y', 'TRANSLITERATION' => 'Y',
                'TRANS_LEN' => 100, 'TRANS_CASE' => 'L', 'TRANS_SPACE' => '-', 'TRANS_OTHER' => '-', 'TRANS_EAT' => 'Y']],
                'SECTION_CODE' => ['IS_REQUIRED' => 'N', 'DEFAULT_VALUE' => ['UNIQUE' => 'Y', 'TRANSLITERATION' => 'Y',
                'TRANS_LEN' => 100, 'TRANS_CASE' => 'L', 'TRANS_SPACE' => '-', 'TRANS_OTHER' => '-', 'TRANS_EAT' => 'Y']]],
        ]);
        if (!$id) throw new RuntimeException("Инфоблок $code: " . $ib->LAST_ERROR);
        out("инфоблок $code создан, ID=$id" . ($id != $wantId ? " (ожидался $wantId!)" : ''));
        $row = ['ID' => $id];
    }
    $IB[$code] = (int) $row['ID'];
}

/* ---------------------------------------------------------------- свойства */
function ensureProp($iblockCode, $code, $name, $type = 'S', array $extra = [])
{
    global $IB;
    $iblockId = $IB[$iblockCode];
    if (CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code, 'CHECK_PERMISSIONS' => 'N'])->Fetch()) {
        return false;
    }
    [$ptype, $utype] = array_pad(explode(':', $type, 2), 2, '');
    $f = ['IBLOCK_ID' => $iblockId, 'CODE' => $code, 'NAME' => $name, 'ACTIVE' => 'Y', 'SORT' => $extra['SORT'] ?? 500,
        'PROPERTY_TYPE' => $ptype, 'MULTIPLE' => !empty($extra['MULTIPLE']) ? 'Y' : 'N',
        'WITH_DESCRIPTION' => !empty($extra['DESC']) ? 'Y' : 'N'];
    if ($utype === 'HTML') {
        $f['USER_TYPE'] = 'HTML';
        $f['DEFAULT_VALUE'] = ['TYPE' => 'HTML', 'TEXT' => ''];
    }
    if ($ptype === 'E' || $ptype === 'G') {
        $f['LINK_IBLOCK_ID'] = $IB[$extra['LINK']];
    }
    if ($ptype === 'L' && !empty($extra['CHECKBOX'])) {
        $f['LIST_TYPE'] = 'C';
        $f['VALUES'] = [['VALUE' => $extra['CHECKBOX'], 'XML_ID' => 'Y', 'DEF' => 'N', 'SORT' => 100]];
    }
    if (!empty($extra['FILTER'])) {
        $f['SMART_FILTER'] = 'Y';
    }
    $p = new CIBlockProperty();
    $id = $p->Add($f);
    if (!$id) throw new RuntimeException("Свойство $iblockCode.$code: " . $p->LAST_ERROR);
    return true;
}

$M = ['MULTIPLE' => true];
$MD = ['MULTIPLE' => true, 'DESC' => true];
$props = [
    'contacts' => [
        ['MAIN', 'Основной', 'L', ['CHECKBOX' => 'Да']], ['PHONE', 'Телефон'], ['EMAIL', 'E-mail'],
        ['ADDRESS', 'Адрес', 'S:HTML'], ['TG', 'Telegram'], ['MAP', 'Карта'],
    ],
    'services' => [['SHOW_FORM', 'Показывать форму', 'L', ['CHECKBOX' => 'Да']], ['FAQ', 'FAQ', 'E', $M + ['LINK' => 'faq']]],
    'news' => [['GALLERY', 'Галерея', 'F', $M]],
    'steps' => [['ICONS', 'Иконка', 'F']],
    'banners' => [['IMG_DESC', 'Картинка (десктоп)', 'F'], ['IMG_MOB', 'Картинка (мобильная)', 'F'], ['LINK', 'Ссылка']],
    'projects' => [
        ['TYPE', 'Тип', 'L', $M], ['OBJ_TYPE', 'Тип объекта', 'L'], ['CITY', 'Город'], ['GALLERY', 'Галерея', 'F', $M],
        ['LIST', 'Список', 'S', $MD], ['BLOCKS', 'Блоки', 'S:HTML', $MD], ['INFO_TITLE', 'Заголовок блока'],
        ['CATALOG_PRODUCT_1', 'Товары 1', 'E', $M + ['LINK' => 'catalog']], ['TITLE_CATALOG_1', 'Заголовок товаров 1'],
        ['CATALOG_PRODUCT_2', 'Товары 2', 'E', $M + ['LINK' => 'catalog']], ['TITLE_CATALOG_2', 'Заголовок товаров 2'],
        ['BRANDS', 'Бренды', 'E', $M + ['LINK' => 'brands']], ['TITLE_BRANDS', 'Заголовок брендов'],
    ],
    'solution' => [
        ['GALLERY', 'Галерея', 'F', $M], ['TEXT_BLOCKS', 'Текстовые блоки', 'S:HTML', $MD],
        ['CATALOG_PRODUCT_1', 'Товары 1', 'E', $M + ['LINK' => 'catalog']], ['TITLE_CATALOG_1', 'Заголовок товаров 1'],
        ['CATALOG_PRODUCT_2', 'Товары 2', 'E', $M + ['LINK' => 'catalog']], ['TITLE_CATALOG_2', 'Заголовок товаров 2'],
        ['FAQ', 'FAQ', 'E', $M + ['LINK' => 'faq']],
    ],
    'blog' => [
        ['ALERT', 'Врезка', 'S:HTML'],
        ['CATALOG_PRODUCT_1', 'Товары 1', 'E', $M + ['LINK' => 'catalog']], ['TITLE_CATALOG_1', 'Заголовок товаров 1'],
        ['CATALOG_PRODUCT_2', 'Товары 2', 'E', $M + ['LINK' => 'catalog']], ['TITLE_CATALOG_2', 'Заголовок товаров 2'],
        ['PROJECTS', 'Проекты', 'E', $M + ['LINK' => 'projects']],
    ],
    'brands' => [
        ['LOGO', 'Логотип', 'F'], ['WEBSITE_URL', 'Сайт'],
        ['GALLERY_1', 'Галерея 1', 'F', $M], ['GALLERY_2', 'Галерея 2', 'F', $M], ['GALLERY_3', 'Галерея 3', 'F', $M],
        ['GALLERY_4', 'Галерея 4', 'F', $M], ['GALLERY_5', 'Галерея 5', 'F', $M],
    ],
    'directionshome' => [['LINK', 'Ссылка'], ['LINK_TEXT', 'Текст ссылки']],
    'herohome' => [
        ['HERO_SLIDER_IMAGES', 'Слайдер', 'F', $M], ['HERO_TITLE', 'Заголовок'], ['HERO_BULLET_TEXTS', 'Пункты', 'S', $M],
        ['LOGO', 'Логотип', 'F'], ['ABOUT', 'О компании', 'S:HTML'],
    ],
    'brandhome' => [['CLIENTS', 'Клиенты (логотипы)', 'F', $M], ['BRANDS', 'Бренды (логотипы)', 'F', $M]],
    'aboutpage' => [['TEXT_BLOCKS', 'Текстовые блоки', 'S:HTML', $MD]],
    'aboutlist' => [['IMG_BLOCK', 'Картинка', 'F'], ['NUM', 'Число'], ['DESCR', 'Подпись']],
    'partneram' => [['TABS', 'Вкладки', 'S:HTML', $MD]],
    'seoparams' => [['H1', 'H1'], ['TITLE', 'Title'], ['KEYWORDS', 'Keywords'], ['DESCRIPTION', 'Description'], ['TEXT_TITLE', 'Заголовок SEO-текста']],
    'redirect' => [['FROM', 'Откуда'], ['WHERE', 'Куда']],
    'prachechnaya' => [['PROJECTS', 'Проекты', 'E', $M + ['LINK' => 'projects']], ['BRANDS', 'Бренды', 'E', $M + ['LINK' => 'brands']]],
    'textile' => [['PROJECTS', 'Проекты', 'E', $M + ['LINK' => 'projects']], ['BRANDS', 'Бренды', 'E', $M + ['LINK' => 'brands']]],
];
// общие привязки направлений со «старыми» шаблонами страниц (GetPropDirectionByCode)
$dirLinks = [
    ['BANNERS', 'Баннеры', 'E', $M + ['LINK' => 'banners']], ['CATALOG_PRODUCTS', 'Товары', 'E', $M + ['LINK' => 'catalog']],
    ['PROJECTS', 'Проекты', 'E', $M + ['LINK' => 'projects']], ['BRANDS', 'Бренды', 'E', $M + ['LINK' => 'brands']],
    ['CATEGORIES_CATALOG', 'Разделы каталога', 'G', $M + ['LINK' => 'catalog']], ['STEPS', 'Этапы', 'E', $M + ['LINK' => 'steps']],
    ['SOLUTION', 'Решения', 'E', $M + ['LINK' => 'solution']], ['REVIEWS', 'Отзывы', 'E', $M + ['LINK' => 'reviews']],
    ['FAQ', 'FAQ', 'E', $M + ['LINK' => 'faq']], ['ARTICLES', 'Статьи', 'E', $M + ['LINK' => 'blog']],
];
$props['kuhnya'] = array_merge($dirLinks, [['IMG_BLOCK', 'Картинка', 'F'], ['DESCRIPTION', 'Описание', 'S:HTML'], ['LINK_CATALOG', 'Ссылка на каталог']]);
$props['chemistry'] = array_merge($dirLinks, [
    ['MAIN_TITLE', 'Заголовок'], ['MAIN_TEXT_LEFT', 'Текст слева', 'S:HTML'], ['MAIN_TEXT_RIGHT', 'Текст справа', 'S:HTML'],
    ['STAT', 'Цифры', 'S', $MD], ['LIST_GARANT', 'Гарантии', 'S', $M], ['TITLE_GARANT', 'Заголовок гарантий'],
    ['TITLE_FORM', 'Заголовок формы'], ['TEXT_FORM', 'Текст формы', 'S:HTML'],
]);
$eq = [['IMG_BLOCK', 'Картинка', 'F']];
for ($n = 1; $n <= 3; $n++) {
    $eq[] = ["ITEM_{$n}_TITLE", "Блок $n: заголовок"]; $eq[] = ["ITEM_{$n}_IMAGE", "Блок $n: картинка", 'F'];
    $eq[] = ["ITEM_{$n}_TEXT", "Блок $n: текст", 'S:HTML']; $eq[] = ["ITEM_{$n}_BUTTON_LINK", "Блок $n: ссылка"];
}
$props['equipment'] = array_merge($dirLinks, $eq);
$fu = [['HERO_BG', 'Фон', 'F'], ['HERO_TITLE', 'Заголовок'], ['HERO_DESCR', 'Подзаголовок'], ['ABOUT_TEXT', 'О направлении'],
    ['ABOUT_IMAGE', 'Картинка', 'F'], ['CABINET_IMAGE', 'Кабинет: картинка', 'F'], ['CABINET_TITLE', 'Кабинет: заголовок'],
    ['CABINET_DESCR', 'Кабинет: описание'], ['CASES_SLIDER_IMAGES', 'Кейсы: слайдер', 'F', $M], ['CASES_TEXT', 'Кейсы: текст', 'S:HTML'],
    ['CASES_ADDITIONAL_IMAGE', 'Кейсы: доп. картинка', 'F'], ['CASES_ADDITIONAL_TEXT', 'Кейсы: доп. текст', 'S:HTML'],
    ['SUPPLIERS_IMAGES', 'Поставщики', 'F', $M]];
for ($n = 1; $n <= 4; $n++) { $fu[] = ["ABOUT_ICON_$n", "Иконка $n", 'F']; $fu[] = ["ABOUT_DESC_$n", "Подпись $n"]; }
for ($n = 1; $n <= 3; $n++) {
    $fu[] = ["PLACES_IMAGE_$n", "Место $n: картинка", 'F']; $fu[] = ["PLACES_TITLE_$n", "Место $n: заголовок"];
    $fu[] = ["PLACES_DESC_$n", "Место $n: описание"]; $fu[] = ["CABINET_FEATURE_ICON_$n", "Особенность $n: иконка", 'F'];
    $fu[] = ["CABINET_FEATURE_TEXT_$n", "Особенность $n: текст"];
}
$props['furniture'] = array_merge($dirLinks, $fu);

$cnt = 0;
foreach ($props as $ibCode => $list) {
    foreach ($list as $p) {
        $cnt += (int) ensureProp($ibCode, $p[0], $p[1], $p[2] ?? 'S', $p[3] ?? []);
    }
}
out("свойства контентных инфоблоков: создано $cnt");

/* ---------------------------------------------------------------- свойства каталога
 * Состояние до миграций сентября–октября: характеристики — списки L с SMART_FILTER.
 * Не создаём то, что создают миграции (STOCK_BADGE, TIP_TOVARA, SPECS, LINEYKA, VENT_*, свойства
 * льдогенераторов, TIP_POSUDOMOECHNOJ_MASHINY, MODEL_ARTIKUL) — иначе миграции решат, что работа уже сделана. */
$byMigrations = ['STOCK_BADGE', 'TIP_TOVARA', 'SPECS', 'LINEYKA', 'TIP_POSUDOMOECHNOJ_MASHINY', 'MODEL_ARTIKUL'];
$byMigrationsPrefix = ['VENT_'];
foreach (glob($_SERVER['DOCUMENT_ROOT'] . '/local/migrations/2026-09-21-ice-makers-section-props.php') as $f) {
    if (preg_match_all("/'CODE'\s*=>\s*'([A-Z0-9_]+)'/", file_get_contents($f), $mm)) {
        $byMigrations = array_merge($byMigrations, $mm[1]);
    }
    if (preg_match_all("/^\s*'([A-Z][A-Z0-9_]+)'\s*=>\s*\[/m", file_get_contents($f), $mm)) {
        $byMigrations = array_merge($byMigrations, $mm[1]);
    }
}
$multiple = ['COUNTRY', 'TIP_PREDPRIYATIYA', 'NAZNACHENIE', 'TIP_NAGREVA', 'MODEL', 'NALICHIE', 'STRANA'];
$cnt = 0;
$cnt += (int) ensureProp('catalog', 'GALLERY', 'Галерея', 'F', $M + ['SORT' => 10]);
$cnt += (int) ensureProp('catalog', 'BRAND', 'Бренд', 'E', $M + ['LINK' => 'brands', 'SORT' => 20, 'FILTER' => true]);
$cnt += (int) ensureProp('catalog', 'PROIZVODITEL', 'Производитель', 'L', ['SORT' => 30, 'FILTER' => true]);
$cnt += (int) ensureProp('catalog', 'COUNTRY', 'Страна', 'L', $M + ['SORT' => 40, 'FILTER' => true]);
$cnt += (int) ensureProp('catalog', 'MODEL', 'Модель', 'L', $M + ['SORT' => 50]);
$cnt += (int) ensureProp('catalog', 'NALICHIE', 'Наличие', 'L', $M + ['SORT' => 60]);
$names = CatalogStandard::PROPERTY_NAMES;
foreach (array_keys(CatalogStandard::PROPERTY_MERGES) as $src) {
    $names[$src] = $names[$src] ?? $src;
}
// коды из Битрикс-снимков архива (ссылки умного фильтра /f/<code>-is-...), если их нет в словаре
$extraFile = __DIR__ . '/archive_prop_codes.txt';
if (is_file($extraFile)) {
    foreach (file($extraFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        [$c, $n] = array_pad(explode("\t", $line, 2), 2, '');
        $c = strtoupper(trim($c));
        if ($c !== '' && !isset($names[$c])) $names[$c] = $n ?: $c;
    }
}
$sort = 100;
foreach ($names as $code => $name) {
    if (in_array($code, $byMigrations, true) || in_array($code, ['BRAND', 'GALLERY', 'PROIZVODITEL', 'COUNTRY', 'MODEL', 'NALICHIE'], true)) continue;
    foreach ($byMigrationsPrefix as $px) if (str_starts_with($code, $px)) continue 2;
    $cnt += (int) ensureProp('catalog', $code, $name, 'L', ['MULTIPLE' => in_array($code, $multiple, true), 'SORT' => $sort += 10, 'FILTER' => true]);
}
out("свойства каталога: создано $cnt");

// Умный фильтр хранится в b_iblock_section_property и пишется, только если у инфоблока SECTION_PROPERTY=Y.
// Глобальная привязка (SECTION_ID=0) — всем характеристикам-спискам, кроме TIP_ZAPCHASTI
// (на проде её не было, включает миграция 2026-09-04-tip-zapchasti-smart-filter).
if (CIBlock::GetArrayByID($IB['catalog'], 'SECTION_PROPERTY') !== 'Y') {
    (new CIBlock())->Update($IB['catalog'], ['SECTION_PROPERTY' => 'Y']);
    out('у каталога включено SECTION_PROPERTY');
}
$links = 0;
$r = $DB->Query("SELECT p.ID, p.CODE FROM b_iblock_property p LEFT JOIN b_iblock_section_property sp
    ON sp.PROPERTY_ID = p.ID AND sp.SECTION_ID = 0 WHERE p.IBLOCK_ID = {$IB['catalog']} AND p.PROPERTY_TYPE IN ('L','N') AND p.ACTIVE = 'Y' AND sp.PROPERTY_ID IS NULL");
while ($p = $r->Fetch()) {
    if ($p['CODE'] === 'TIP_ZAPCHASTI') continue;
    CIBlockSectionPropertyLink::Add(0, $p['ID'], ['SMART_FILTER' => 'Y', 'IBLOCK_ID' => $IB['catalog']]);
    $links++;
}
out("привязок к умному фильтру создано: $links");

/* ---------------------------------------------------------------- торговый каталог, цены, валюты */
if (!CCatalog::GetByID($IB['catalog'])) {
    if (!CCatalog::Add(['IBLOCK_ID' => $IB['catalog']])) throw new RuntimeException('CCatalog::Add не удался');
    out('инфоблок catalog сделан торговым каталогом');
}
if (!CCatalogGroup::GetList([], ['NAME' => 'BASE_PRICE'])->Fetch()) {
    setAutoIncrement('b_catalog_group', 1);
    $gid = CCatalogGroup::Add(['NAME' => 'BASE_PRICE', 'BASE' => 'Y', 'SORT' => 100, 'XML_ID' => 'BASE_PRICE',
        'USER_GROUP' => [1, 2], 'USER_GROUP_BUY' => [1, 2], 'USER_LANG' => ['ru' => 'Базовая цена']]);
    out("тип цены BASE_PRICE, ID=$gid" . ($gid != 1 ? ' (ожидался 1!)' : ''));
}

/* ---------------------------------------------------------------- разделы каталога
 * ID — боевые (local/import/data/import_<ID>.xml, март 2026), родители — reference_sections.csv модуля импорта.
 * mebel = 120 (зашит в catalog.element/.default/template.php). */
$root = $_SERVER['DOCUMENT_ROOT'] . '/local';
$secIds = [];
foreach (glob("$root/import/data/import_*.xml") as $f) {
    if (preg_match('/<section name="([^"]*)" date="[^"]*" total="\d+" id="(\d+)" code="([^"]*)"/', file_get_contents($f), $m)) {
        $secIds[$m[3]] = [(int) $m[2], html_entity_decode($m[1])];
    }
}
$secIds['mebel'] = [120, 'Мебель'];
$ref = [];
$fh = fopen("$root/modules/profequip.import/templates/reference_sections.csv", 'r');
fgetcsv($fh, 0, ';');
while ($r = fgetcsv($fh, 0, ';')) {
    if (count($r) >= 2) $ref[$r[0]] = ['name' => $r[1], 'parent' => $r[2] ?? ''];
}
fclose($fh);
$byName = [];
foreach ($ref as $c => $r) $byName[$r['name']] = $c;
$parentOverride = ['tapochki' => 'professionalnyj-tekstil', 'dlya-rastvoritelya-higlo' => 'professionalnaya-himiya'];
$all = [];
foreach ($secIds as $c => [$id, $n]) $all[$c] = ['id' => $id, 'name' => $ref[$c]['name'] ?? $n];
$next = 119;
foreach ($ref as $c => $r) {
    if (isset($all[$c])) continue;
    while (in_array($next, array_column($all, 'id'), true)) $next++;
    $all[$c] = ['id' => $next++, 'name' => $r['name']];
}
uasort($all, fn($a, $b) => $a['id'] <=> $b['id']);
$sec = new CIBlockSection();
$created = 0;
$codeToId = [];
foreach ($all as $c => $s) {
    $row = CIBlockSection::GetList([], ['IBLOCK_ID' => $IB['catalog'], '=CODE' => $c, 'CHECK_PERMISSIONS' => 'N'], false, ['ID'])->Fetch();
    if (!$row) {
        setAutoIncrement('b_iblock_section', $s['id']);
        $id = $sec->Add(['IBLOCK_ID' => $IB['catalog'], 'NAME' => $s['name'], 'CODE' => $c, 'ACTIVE' => 'Y', 'SORT' => 500], false);
        if (!$id) throw new RuntimeException("Раздел $c: " . $sec->LAST_ERROR);
        if ($id != $s['id']) out("ВНИМАНИЕ: раздел $c получил ID $id вместо {$s['id']}");
        $created++;
        $row = ['ID' => $id];
    }
    $codeToId[$c] = (int) $row['ID'];
}
foreach ($all as $c => $s) {
    $pc = $parentOverride[$c] ?? (($ref[$c]['parent'] ?? '') !== '' ? ($byName[$ref[$c]['parent']] ?? '') : '');
    $pid = $pc !== '' ? $codeToId[$pc] : 0;
    $cur = (int) CIBlockSection::GetByID($codeToId[$c])->Fetch()['IBLOCK_SECTION_ID'];
    if ($cur !== $pid) $sec->Update($codeToId[$c], ['IBLOCK_SECTION_ID' => $pid ?: false], false);
}
CIBlockSection::ReSort($IB['catalog']);
out("разделы каталога: создано $created, всего " . count($codeToId));

/* ---------------------------------------------------------------- UF-поля разделов каталога */
$entity = 'IBLOCK_' . $IB['catalog'] . '_SECTION';
$uf = [
    ['UF_PROF', 'boolean', 'N', [], 'Профессиональное (плашка)'],
    ['UF_FAQ', 'iblock_element', 'Y', ['IBLOCK_ID' => $IB['faq'], 'DISPLAY' => 'LIST'], 'FAQ раздела'],
];
foreach ($uf as [$name, $type, $mult, $settings, $label]) {
    if (!CUserTypeEntity::GetList([], ['ENTITY_ID' => $entity, 'FIELD_NAME' => $name])->Fetch()) {
        $e = new CUserTypeEntity();
        $id = $e->Add(['ENTITY_ID' => $entity, 'FIELD_NAME' => $name, 'USER_TYPE_ID' => $type, 'MULTIPLE' => $mult,
            'SETTINGS' => $settings, 'EDIT_FORM_LABEL' => ['ru' => $label], 'LIST_COLUMN_LABEL' => ['ru' => $label]]);
        if (!$id) throw new RuntimeException("UF $name не создано");
        out("UF-поле $name создано");
    }
}

/* ---------------------------------------------------------------- веб-формы 1–3 */
$forms = [
    1 => ['SIMPLE_FORM_1', 'Запрос стоимости', 'order', ['name' => 'Имя', 'phone' => 'Телефон', 'email' => 'E-mail', 'message' => 'Сообщение', 'product' => 'Товар', 'url' => 'Страница']],
    2 => ['SIMPLE_FORM_2', 'Расчёт проекта', 'calculate', ['name' => 'Имя', 'phone' => 'Телефон', 'email' => 'E-mail', 'company' => 'Компания', 'comment' => 'Комментарий', 'url' => 'Страница']],
    3 => ['SIMPLE_FORM_3', 'Консультация', 'consultation', ['name' => 'Имя', 'phone' => 'Телефон', 'email' => 'E-mail', 'message' => 'Сообщение', 'url' => 'Страница']],
];
foreach ($forms as $wantId => [$sid, $title, $prefix, $fields]) {
    $row = CForm::GetBySID($sid)->Fetch();
    if (!$row) {
        setAutoIncrement('b_form', $wantId);
        $fid = CForm::Set(['NAME' => $title, 'SID' => $sid, 'C_SORT' => 100, 'BUTTON' => 'Отправить', 'USE_CAPTCHA' => 'N',
            'arSITE' => [SITE], 'arMENU' => ['ru' => $title], 'arGROUP' => ['2' => 15], 'DESCRIPTION_TYPE' => 'text'], false, 'N');
        if (!$fid) throw new RuntimeException("Форма $sid не создана: " . $GLOBALS['strError']);
        out("форма $sid создана, ID=$fid" . ($fid != $wantId ? " (ожидался $wantId!)" : ''));
        CFormStatus::Set(['FORM_ID' => $fid, 'C_SORT' => 100, 'ACTIVE' => 'Y', 'TITLE' => 'DEFAULT', 'DEFAULT_VALUE' => 'Y',
            'arPERMISSION_VIEW' => [0], 'arPERMISSION_MOVE' => [0], 'arPERMISSION_EDIT' => [0], 'arPERMISSION_DELETE' => [0]], false, 'N');
        $s = 100;
        foreach ($fields + ['CAPTCHA_TOKEN' => 'CAPTCHA_TOKEN', 'CAPTCHA_VERIFY_DATA' => 'CAPTCHA_VERIFY_DATA', 'CAPTCHA_VERIFY_DATA_FULL' => 'CAPTCHA_VERIFY_DATA_FULL'] as $k => $label) {
            $qsid = str_starts_with($k, 'CAPTCHA') ? $k : "{$prefix}_{$k}";
            $type = in_array($k, ['message', 'comment'], true) ? 'textarea' : (str_starts_with($k, 'CAPTCHA') ? 'hidden' : 'text');
            $qid = CFormField::Set(['FORM_ID' => $fid, 'ACTIVE' => 'Y', 'TITLE' => $label, 'SID' => $qsid, 'C_SORT' => $s += 10,
                'ADDITIONAL' => 'N', 'REQUIRED' => in_array($k, ['name', 'phone'], true) ? 'Y' : 'N', 'IN_RESULTS_TABLE' => 'Y',
                'arANSWER' => [['MESSAGE' => ' ', 'C_SORT' => 100, 'ACTIVE' => 'Y', 'FIELD_TYPE' => $type]]], false, 'N');
            if (!$qid) throw new RuntimeException("Поле $qsid формы $sid: " . $GLOBALS['strError']);
        }
        $tpl = [];
        CForm::SetMailTemplate($fid, 'Y', '', true, $tpl);
    }
}
// адресат писем форм, как на проде (миграция лендинга 09-18 копирует его у SIMPLE_FORM_3)
$DB->Query("UPDATE b_event_message SET EMAIL_TO = 'info@prof-equip.ru'
    WHERE EVENT_NAME IN ('FORM_FILLING_SIMPLE_FORM_1','FORM_FILLING_SIMPLE_FORM_2','FORM_FILLING_SIMPLE_FORM_3')
    AND EMAIL_TO = '#DEFAULT_EMAIL_FROM#'");

/* ---------------------------------------------------------------- обязательные элементы */
function ensureElement($ibCode, $code, $name, array $fields = [], array $props = [])
{
    global $IB;
    $row = CIBlockElement::GetList([], ['IBLOCK_ID' => $IB[$ibCode], '=CODE' => $code, 'CHECK_PERMISSIONS' => 'N'], false, false, ['ID'])->Fetch();
    if ($row) return (int) $row['ID'];
    $el = new CIBlockElement();
    $id = $el->Add(['IBLOCK_ID' => $IB[$ibCode], 'NAME' => $name, 'CODE' => $code, 'ACTIVE' => 'Y'] + $fields + ['PROPERTY_VALUES' => $props]);
    if (!$id) throw new RuntimeException("Элемент $ibCode/$code: " . $el->LAST_ERROR);
    out("элемент $ibCode/$code создан, ID=$id");
    return $id;
}
$mainEnum = CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $IB['contacts'], 'CODE' => 'MAIN'])->Fetch();
ensureElement('contacts', 'main', 'Москва', [], [
    'MAIN' => $mainEnum['ID'], 'PHONE' => '8 (499) 302-37-62', 'EMAIL' => 'info@prof-equip.ru', 'TG' => 'https://t.me/prof_equip',
    'ADDRESS' => ['VALUE' => ['TYPE' => 'HTML', 'TEXT' => 'Москва']],
]);
foreach ([['prachechnaya', 'prachechnaya', 'Прачечная'], ['textile', 'textile', 'Профессиональный текстиль'],
          ['kuhnya', 'kuhnya', 'Оборудование профессиональной кухни'], ['chemistry', 'chemistry', 'Профессиональная химия'],
          ['equipment', 'equipment', 'Комплексное оснащение'], ['furniture', 'furniture', 'Мебель'],
          ['aboutpage', 'about', 'О компании'], ['partneram', 'partneram', 'Партнёрам'],
          ['herohome', 'herohome', 'Первый экран'], ['brandhome', 'brandhome', 'Клиенты и бренды']] as [$ib, $code, $name]) {
    ensureElement($ib, $code, $name);
}

/* ---------------------------------------------------------------- проверка запросом к базе */
$r = $DB->Query("SELECT ID, CODE, VERSION FROM b_iblock ORDER BY ID");
$ids = [];
while ($x = $r->Fetch()) $ids[] = "{$x['ID']}={$x['CODE']}" . ($x['VERSION'] != 1 ? '(v2!)' : '');
out('инфоблоки: ' . implode(' ', $ids));
$roots = $DB->Query("SELECT ID, CODE FROM b_iblock_section WHERE IBLOCK_ID={$IB['catalog']} AND IBLOCK_SECTION_ID IS NULL ORDER BY ID");
$rr = [];
while ($x = $roots->Fetch()) $rr[] = "{$x['ID']}={$x['CODE']}";
out('корни каталога: ' . implode(' ', $rr));
out('свойств у каталога: ' . $DB->Query("SELECT COUNT(*) C FROM b_iblock_property WHERE IBLOCK_ID={$IB['catalog']}")->Fetch()['C']);
out('ГОТОВО');
