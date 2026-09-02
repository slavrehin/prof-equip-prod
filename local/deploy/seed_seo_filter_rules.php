<?php
/**
 * Заводит/обновляет контент инфоблока "seofilterrules" (SEO-правила умного
 * фильтра) для задачи: SEO-значимые фильтры «Производитель» и «Загрузка, Кг»
 * в разделах "Стиральные машины"/"Сушильное оборудование", и «Производитель»
 * в разделе "Прачечное оборудование".
 *
 * Это КОНТЕНТ (см. local/migrations/README.md — правила заводятся через
 * админку, "Содержимое -> SEO-правила фильтра"), не структура БД, поэтому
 * скрипт лежит вне local/migrations/ и не участвует в раннере/таблице
 * применённых миграций. Идемпотентен: при повторном запуске обновляет те же
 * элементы теми же значениями (safe re-run), не создаёт дублей — ищет по CODE.
 *
 * Запуск:
 *   docker compose exec web php /var/www/html/local/deploy/seed_seo_filter_rules.php   # test3
 *   php local/deploy/seed_seo_filter_rules.php                                          # прод
 */

$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

CModule::IncludeModule('iblock');

global $DB;

$seoIblockId = GetIBlockIDByCode('seofilterrules');
if (!$seoIblockId) {
    throw new \RuntimeException('Инфоблок seofilterrules не найден — сначала примени миграцию 2026-09-02-seofilterrules-iblock.php');
}

// ID свойств расходятся между окружениями (test3/прод) — колонку TITLE_TEMPLATE
// в таблице значений (b_iblock_element_prop_sN) находим по CODE, не хардкодим.
$titlePropRow = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $seoIblockId, 'CODE' => 'TITLE_TEMPLATE'])->Fetch();
if (!$titlePropRow) {
    throw new \RuntimeException('Свойство TITLE_TEMPLATE не найдено в инфоблоке seofilterrules');
}
$titlePropColumn = 'PROPERTY_' . (int)$titlePropRow['ID'];

// CODE правила = "<код раздела>__<код свойства в нижнем регистре>".
$rules = [
    [
        'CODE' => 'stiralnye-mashiny__proizvoditel',
        'NAME' => 'Стиральные машины — Производитель',
        'H1' => 'Стиральные машины #VALUE#',
        'TITLE' => 'Стиральные машины #VALUE# купить с доставкой в ПРОФЭКВИП',
        'DESCRIPTION' => 'Каталог профессиональных стиральных машин #VALUE# для прачечных и химчисток. Доставка по всей России и СНГ.',
        'SEO_TEXT' => '<p>Компания ПРОФЭКВИП поставляет профессиональные стиральные машины #VALUE# для прачечных, химчисток и отелей. В наличии и под заказ — модели разной загрузки и производительности для коммерческой прачечной любого масштаба.</p><p>Поможем подобрать стиральную машину #VALUE#, которая впишется в объём вашего бизнеса, организуем доставку по всей России и СНГ, а также гарантийное и сервисное обслуживание.</p>',
    ],
    [
        'CODE' => 'stiralnye-mashiny__zagruzka_kg',
        'NAME' => 'Стиральные машины — Загрузка, Кг',
        'H1' => 'Стиральные машины с загрузкой #VALUE# кг',
        'TITLE' => 'Стиральные машины с загрузкой #VALUE# кг купить с доставкой в ПРОФЭКВИП',
        'DESCRIPTION' => 'Каталог профессиональных стиральных машин с загрузкой #VALUE# кг для прачечных и химчисток. Доставка по всей России и СНГ.',
        'SEO_TEXT' => '<p>В каталоге ПРОФЭКВИП — профессиональные стиральные машины с загрузкой #VALUE# кг для прачечных, химчисток и гостиниц. Модели рассчитаны на интенсивную ежедневную эксплуатацию и разный объём загрузки белья.</p><p>Подберём стиральную машину с загрузкой #VALUE# кг под задачи вашего предприятия, организуем доставку по всей России и СНГ, а также монтаж и сервисное сопровождение.</p>',
    ],
    [
        'CODE' => 'sushilnoe-oborudovanie__proizvoditel',
        'NAME' => 'Сушильное оборудование — Производитель',
        'H1' => 'Сушильное оборудование #VALUE#',
        'TITLE' => 'Сушильное оборудование #VALUE# купить с доставкой в ПРОФЭКВИП',
        'DESCRIPTION' => 'Каталог профессионального сушильного оборудования #VALUE# для прачечных и химчисток. Доставка по всей России и СНГ.',
        'SEO_TEXT' => '<p>ПРОФЭКВИП предлагает профессиональное сушильное оборудование #VALUE# для прачечных, химчисток и отелей. В наличии и под заказ — сушильные машины разной загрузки и производительности.</p><p>Поможем подобрать сушильное оборудование #VALUE# под объём вашей прачечной, организуем доставку по всей России и СНГ, гарантийное и постгарантийное обслуживание.</p>',
    ],
    [
        'CODE' => 'sushilnoe-oborudovanie__zagruzka_kg',
        'NAME' => 'Сушильное оборудование — Загрузка, Кг',
        'H1' => 'Сушильное оборудование с загрузкой #VALUE# кг',
        'TITLE' => 'Сушильное оборудование с загрузкой #VALUE# кг купить с доставкой в ПРОФЭКВИП',
        'DESCRIPTION' => 'Каталог профессионального сушильного оборудования с загрузкой #VALUE# кг для прачечных и химчисток. Доставка по всей России и СНГ.',
        'SEO_TEXT' => '<p>В каталоге ПРОФЭКВИП — профессиональное сушильное оборудование с загрузкой #VALUE# кг для прачечных, химчисток и гостиниц, рассчитанное на интенсивную ежедневную эксплуатацию.</p><p>Подберём сушильную машину с загрузкой #VALUE# кг под объём вашего производства, организуем доставку по всей России и СНГ, монтаж и сервисное сопровождение.</p>',
    ],
    [
        'CODE' => 'prachechnoe-oborudovanie__proizvoditel',
        'NAME' => 'Прачечное оборудование — Производитель',
        'H1' => 'Прачечное оборудование #VALUE#',
        'TITLE' => 'Прачечное оборудование #VALUE# купить с доставкой в ПРОФЭКВИП',
        'DESCRIPTION' => 'Каталог профессионального прачечного оборудования #VALUE# для прачечных и химчисток. Доставка по всей России и СНГ.',
        'SEO_TEXT' => '<p>ПРОФЭКВИП поставляет профессиональное прачечное оборудование #VALUE# для прачечных, химчисток и отелей: стиральные машины, сушильное и гладильное оборудование, финишную обработку белья.</p><p>Поможем подобрать оборудование #VALUE# под задачи и объём вашего предприятия, организуем доставку по всей России и СНГ, монтаж, гарантийное и сервисное обслуживание.</p>',
    ],
];

foreach ($rules as $rule) {
    $propValues = [
        'H1_TEMPLATE' => $rule['H1'],
        'TITLE_TEMPLATE' => $rule['TITLE'],
        'DESCRIPTION_TEMPLATE' => $rule['DESCRIPTION'],
        'SEO_TEXT_TEMPLATE' => $rule['SEO_TEXT'],
    ];

    $existing = \CIBlockElement::GetList([], [
        'IBLOCK_ID' => $seoIblockId,
        'CODE' => $rule['CODE'],
    ], false, false, ['ID'])->Fetch();

    if ($existing) {
        $elementId = (int)$existing['ID'];
        \CIBlockElement::SetPropertyValuesEx($elementId, $seoIblockId, $propValues);
        echo "Обновлено правило {$rule['CODE']} (ID=$elementId).\n";
    } else {
        $el = new \CIBlockElement();
        $elementId = $el->Add([
            'IBLOCK_ID' => $seoIblockId,
            'NAME' => $rule['NAME'],
            'CODE' => $rule['CODE'],
            'ACTIVE' => 'Y',
        ]);

        if (!$elementId) {
            throw new \RuntimeException('Не создан элемент ' . $rule['CODE'] . ': ' . $el->LAST_ERROR);
        }

        \CIBlockElement::SetPropertyValuesEx($elementId, $seoIblockId, $propValues);
        echo "Создано правило {$rule['CODE']} (ID=$elementId).\n";
    }

    // Проверка результата запросом к базе, а не доверие коду возврата API.
    $check = $DB->Query('SELECT ' . $titlePropColumn . ' AS TITLE_VALUE FROM b_iblock_element_prop_s' . $seoIblockId . ' WHERE IBLOCK_ELEMENT_ID = ' . (int)$elementId)->Fetch();
    if (!$check || $check['TITLE_VALUE'] !== $rule['TITLE']) {
        throw new \RuntimeException('Свойства правила ' . $rule['CODE'] . ' не сохранились как ожидалось (ID=' . $elementId . ')');
    }
}

echo "Готово.\n";
