<?php
/**
 * Правит SEO-метатеги разделов каталога (инфоблок 11: SECTION_META_TITLE /
 * SECTION_META_DESCRIPTION):
 *   - опечатки и спам-фразы ("КУПИТЬ ТУТ! ЗВОНИ СЕЙЧАС", "гостинииц", "Росссии" и т.д.);
 *   - обрезанный title у запчастей для кухонного оборудования;
 *   - метатеги для раздела "Аксессуары" (Rational), где их не было;
 *   - актуальный телефон 8 (499) 302-37-62 вместо старых (+7 (495) 477-57-13,
 *     8 (495) 72-555-70, подменный Манго Офис 8 (495) 161-94-48) во ВСЕХ шаблонах
 *     всех инфоблоков (разделы, товары, новости, портфолио).
 *
 * Раздел ищется по CODE. Идемпотентен (значения задаются явно). Затрагивает
 * только перечисленные коды шаблонов, остальные шаблоны раздела не трогает
 * (SectionTemplates::set() обновляет только переданные ключи).
 *
 * Флаг --dry — только показать изменения.
 *
 * Запуск:
 *   docker compose exec web php /var/www/html/local/deploy/fix_section_meta.php --dry   # test3
 *   php local/deploy/fix_section_meta.php                                              # прод
 */

$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

CModule::IncludeModule('iblock');

const META_IBLOCK_ID = 11;
const META_PHONE = '8 (499) 302-37-62';

$dry = in_array('--dry', $argv, true);

$T = 'SECTION_META_TITLE';
$D = 'SECTION_META_DESCRIPTION';

// Явные замены (код раздела => [код шаблона => новое значение]).
$fixes = [
    'postelnoe-bele' => [$T => 'Постельное белье для отелей и гостиниц купить в ПРОФЭКВИП'],
    'postelnye-prinadlezhnosti' => [$T => 'Постельные принадлежности для отелей и гостиниц купить в ПРОФЭКВИП'],
    'matrasy' => [
        $T => 'Матрасы для отелей и гостиниц купить в ПРОФЭКВИП',
        $D => 'Матрасы Fresco Maggiore для гостиничных номеров: коллекции Firenze, Milano, Veneto. Подбор под категорию номера, доставка по России.',
    ],
    'dlya-fitnesa-amp-spa' => [$T => 'Профессиональный текстиль для фитнеса и SPA купить в ПРОФЭКВИП'],
    'finishnoe-oborudovanie' => [
        $T => 'Финишное оборудование для прачечных и химчисток купить в ПРОФЭКВИП',
        $D => 'Финишное оборудование для прачечных и химчисток: гладильные столы, прессы, пароманекены, парогенераторы, складыватели. Доставка по России.',
    ],
    'podayushhie-ustrojstva' => [$T => 'Подающие машины для прачечной: купить в ПРОФЭКВИП'],
    'frityurnitsiy' => [$T => 'Профессиональные фритюрницы для кафе и ресторанов купить в ПРОФЭКВИП'],
    'stulya' => [$T => 'Стулья для ресторанов, кафе и баров от мировых брендов купить в ПРОФЭКВИП'],
    'zapasnye-chasti-dlya-kuhonnogo-oborudovaniya' => [$T => 'Запасные части для кухонного оборудования купить в ПРОФЭКВИП'],
    'aksessuary-dlya-ofitsiantov' => [
        $T => 'Аксессуары для официантов: бейсболки, галстуки, подтяжки | ПРОФЭКВИП',
        $D => 'Аксессуары для официантов Greiff: галстуки, галстуки-бабочки, подтяжки, бейсболки. Доставка по России. Оставьте заявку на сайте ПРОФЭКВИП.',
    ],
    'kollektsiya-osnovnaya-kuhnya' => [
        $D => 'Форма для персонала кафе и ресторанов коллекции «Основная кухня» от Greiff: поварские кители и брюки. Доставка по России. Оставьте заявку в ПРОФЭКВИП.',
    ],
    'dlya-rastvoritelya-phe' => [
        $D => 'Профессиональная химия для химчистки для растворителя ПХЭ: SULTRASOFT DEO P, SULTRASOFT P, SULTREX P от Cole&Wilson.',
    ],
    'dlya-rastvoriteleya-uglevodorod' => [
        $D => 'Профессиональная химия для химчистки: усилитель чистки SULTRASOFT от Cole&Wilson для использования в углеводородном растворителе.',
    ],
    'vspomogatelnoe-oborudovanie' => [
        $D => 'Вспомогательное оборудование для прачечных и химчисток в наличии и под заказ. Доставка по всей России. Гарантийное обслуживание.',
    ],
    'teplovoe-oborudovanie' => [
        $D => 'Большой выбор профессионального теплового оборудования с доставкой по России. Выгодные цены на тепловое оборудование. Гарантийное обслуживание.',
    ],
    'gladilnye-pressy' => [
        $D => 'Промышленные прессы для прачечных и химчисток. Подбор, гарантия, сервис. Доставка по всей России.',
    ],
    'gladilnye-kalandry' => [
        $D => 'Промышленные гладильные каландры для прачечных и химчисток. Высокая производительность, гарантия, подбор, сервис. Доставка по всей России.',
    ],
    'gladilnye-katki' => [
        $D => 'Гладильные катки для прачечных и химчисток. Каталог профессиональных гладильных катков от ПРОФЭКВИП. Доставка по всей России. Гарантийное обслуживание.',
    ],
    'obrabotka-fasonnyh-izdelij' => [
        $D => 'Оборудование для обработки фасонных изделий в ПРОФЭКВИП. Каталог оборудования финишной обработки для прачечной и химчистки.',
    ],
    'polotentsa-polotentsa' => [
        $D => 'Приобрести качественные махровые полотенца для отелей и гостиниц в ПРОФЭКВИП. Выгодные цены, доставка по всей России.',
    ],
    'salfetki-osibori' => [
        $D => 'Приобрести махровые салфетки осибори для объектов HoReCa в ПРОФЭКВИП. Выгодные цены, доставка по всей России.',
    ],
    'zapasnye-chasti-tolon' => [
        $D => 'Продажа запчастей для прачечного оборудования Tolon. Только оригинал от TOLON. Датчики, амортизаторы, гофра, двигатели, детекторы, замки, инверторы и другое.',
    ],
    'konditsionery' => [
        $D => 'Профессиональные кондиционеры для прачечных и химчисток. Купить оптом и в розницу. Проконсультироваться по телефону ' . META_PHONE . '.',
    ],
    'stiralnye-mashiny' => [
        $D => 'Купить профессиональные и полупрофессиональные стиральные машины для прачечных и химчисток с загрузкой от 5 до 270 кг. Выгодные цены для постоянных клиентов.',
    ],
    // title был равен названию раздела — из него SEO-заголовок не получался
    'prachechnoe-oborudovanie-v-nalichii' => [$T => 'Прачечное оборудование в наличии на складе купить в ПРОФЭКВИП'],
    'diet-pitanie-tablet-pitanie' => [$T => 'Оборудование для диетического питания (таблет-питание) купить в ПРОФЭКВИП'],
    'mobilnye-kuhonnye-stantsii' => [
        $T => 'Мобильные кухонные станции для ресторанов и мероприятий купить в ПРОФЭКВИП',
        $D => 'ПРОФЭКВИП поставляет мобильные кухонные станции для отелей, гостиниц и ресторанов. Оставьте заявку.',
    ],
    'professionalnye-skladyvateli' => [$T => 'Профессиональные складыватели белья для прачечных купить в ПРОФЭКВИП'],
    'aksessuary' => [
        $T => 'Аксессуары для пароконвектоматов Rational купить в ПРОФЭКВИП',
        $D => 'Аксессуары для пароконвектоматов Rational: решётки Superspike и Spare Rib, рамы с направляющими. Проверка совместимости по артикулу. Доставка по России.',
    ],
];

// Опечатки, встречающиеся в нескольких разделах — заменяются подстрокой.
$textReplace = [
    'Росссии' => 'России',
    'гостинииц' => 'гостиниц',
    'химчисткок' => 'химчисток',
];

$phonePatterns = [
    '/\+?7 ?\(495\) ?477-57-13/u',
    '/8 ?\(?495\)? ?477-57-13/u',
    '/8 ?\(?495\)? ?72-555-70/u',
    '/(?:\+?7|8) ?\(?495\)? ?161[- ]?94[- ]?48/u',
];

global $DB;
$changed = 0;
$touchedSections = [];
$touchedElements = [];

// 1. Явные замены.
foreach ($fixes as $code => $templates) {
    $section = CIBlockSection::GetList([], ['IBLOCK_ID' => META_IBLOCK_ID, 'CODE' => $code], false, ['ID'])->Fetch();
    if (!$section) {
        echo "НЕТ РАЗДЕЛА   $code\n";
        continue;
    }
    $tpl = new \Bitrix\Iblock\InheritedProperty\SectionTemplates(META_IBLOCK_ID, (int)$section['ID']);
    $current = $tpl->findTemplates();
    $set = [];
    foreach ($templates as $key => $value) {
        $cur = trim((string)($current[$key]['TEMPLATE'] ?? ''));
        if ($cur !== $value) {
            $set[$key] = $value;
            echo ($dry ? 'будет: ' : 'исправлено: ') . "$code $key\n    было: $cur\n    стало: $value\n";
        }
    }
    if ($set && !$dry) {
        $tpl->set($set);
        $touchedSections[(int)$section['ID']] = META_IBLOCK_ID;
    }
    $changed += count($set);
}

// 2. Подстрочные замены и телефоны — по всем шаблонам инфоблока.
// Телефоны меняются во всех инфоблоках (в новостях/портфолио те же старые номера),
// опечатки — только в каталоге.
$res = $DB->Query("SELECT ID, IBLOCK_ID, ENTITY_TYPE, ENTITY_ID, CODE, TEMPLATE FROM b_iblock_iproperty");
$rows = [];
while ($r = $res->Fetch()) {
    $rows[] = $r;
}
foreach ($rows as $row) {
    $new = (int)$row['IBLOCK_ID'] === META_IBLOCK_ID ? strtr($row['TEMPLATE'], $textReplace) : $row['TEMPLATE'];
    foreach ($phonePatterns as $re) {
        $new = preg_replace($re, META_PHONE, $new);
    }
    if ($new !== $row['TEMPLATE']) {
        echo ($dry ? 'будет: ' : 'исправлено: ') . "iproperty #{$row['ID']} ({$row['ENTITY_TYPE']} {$row['ENTITY_ID']} {$row['CODE']})\n";
        if (!$dry) {
            $DB->Query("UPDATE b_iblock_iproperty SET TEMPLATE = '" . $DB->ForSql($new) . "' WHERE ID = " . (int)$row['ID']);
            if ($row['ENTITY_TYPE'] === 'S') {
                $touchedSections[(int)$row['ENTITY_ID']] = (int)$row['IBLOCK_ID'];
            } elseif ($row['ENTITY_TYPE'] === 'E') {
                $touchedElements[(int)$row['ENTITY_ID']] = (int)$row['IBLOCK_ID'];
            }
        }
        $changed++;
    }
}

if (!$dry && $changed) {
    // Вычисленные значения SEO-шаблонов кэшируются в b_iblock_section_iprop /
    // b_iblock_element_iprop. Точечное удаление строки одного шаблона оставляет
    // "частичный" кэш (значение читается как NULL), поэтому сбрасываем значения
    // целиком для каждой затронутой сущности — они пересчитаются при обращении.
    foreach ($touchedSections as $id => $ibId) {
        (new \Bitrix\Iblock\InheritedProperty\SectionValues($ibId, $id))->clearValues();
    }
    foreach ($touchedElements as $id => $ibId) {
        (new \Bitrix\Iblock\InheritedProperty\ElementValues($ibId, $id))->clearValues();
    }
    global $CACHE_MANAGER;
    $CACHE_MANAGER->ClearByTag('iblock_id_' . META_IBLOCK_ID);
    \Bitrix\Main\Data\Cache::createInstance()->cleanDir('/');
}

echo "\nИзменений: $changed" . ($dry ? " (dry-run, ничего не записано)" : '') . "\n";
