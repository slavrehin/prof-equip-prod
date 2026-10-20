<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;

// bitrix:catalog (компонент "catalog", вызывается из /catalog/index.php) сам
// парсит REQUEST_URI по SEF_URL_TEMPLATES и должен заполнять
// VARIABLES["SECTION_CODE"] — но для некоторых разделов (замечено на пустых
// категориях без единого товара, например product-category/dlya-rastvoritelya-phe/)
// этого не происходит: VARIABLES остаётся пустым, CURRENT_SECTION_DATA ниже
// не заполняется, и вложенный bitrix:catalog.section получает пустой
// SECTION_ID — в результате вместо страницы раздела молча показывается общий
// список каталога (200, но с чужим контентом и заголовком-заглушкой "Title" —
// классический soft-404). Поэтому код раздела достаём независимо, напрямую
// из URL — так же, как уже сделано в /catalog/index.php.
$sectionCodeFromUrl = null;
if (preg_match('#^/product-category/([^/]+)/#', $_SERVER['REQUEST_URI'], $sectionCodeUrlMatch)) {
    $sectionCodeFromUrl = $sectionCodeUrlMatch[1];
}
$sectionCode = $arResult["VARIABLES"]["SECTION_CODE"] ?? '';
if ($sectionCode === '') {
    $sectionCode = $sectionCodeFromUrl ?? '';
}

if ($sectionCode !== '') {
    $iblockId = $arParams["IBLOCK_ID"]; // ID вашего инфоблока

    $arFilter = array(
        "IBLOCK_ID" => $iblockId,
        "CODE" => $sectionCode,
        "ACTIVE" => "Y",
        // GLOBAL_ACTIVE, а не только ACTIVE: раздел может быть активен сам по
        // себе, но недостижим из-за деактивированного родителя выше по дереву
        // (тот же случай уже отсекается в /catalog/index.php выше по цепочке
        // запроса; проверяем ещё раз здесь для согласованности).
        "GLOBAL_ACTIVE" => "Y",
    );

    $arSelect = array(
        "ID",
        "NAME",
        "CODE",
        "DESCRIPTION",
        "DESCRIPTION_TYPE",
        "PICTURE",
        "DETAIL_PICTURE",
        "UF_*" // все пользовательские поля
    );

    $rsSection = CIBlockSection::GetList(
        array(),
        $arFilter,
        false,
        $arSelect
    );

    if ($arSection = $rsSection->GetNext()) {
        $arResult["CURRENT_SECTION_DATA"] = [
            "ID" => $arSection["ID"],
            "NAME" => $arSection["NAME"],
            "CODE" => $arSection["CODE"],
            "DESCRIPTION" => $arSection["DESCRIPTION"],
            "DESCRIPTION_TYPE" => $arSection["DESCRIPTION_TYPE"],
            "PICTURE" => $arSection["PICTURE"],
            "PICTURE_SRC" => CFile::GetPath($arSection["PICTURE"]),
            "DETAIL_PICTURE" => $arSection["DETAIL_PICTURE"],
            "DETAIL_PICTURE_SRC" => CFile::GetPath($arSection["DETAIL_PICTURE"]),
            "FAQ"=> $arSection["UF_FAQ"]
        ];

        // Заголовок/H1 раздела — применяем ПОСЛЕ bitrix:catalog.section (см. его
        // вызов ниже, у него SET_TITLE/SET_BROWSER_TITLE=Y), иначе будет затёрто.
        // Гарантирует правильные title/H1 даже когда сам bitrix:catalog(.section)
        // не смог распознать раздел (см. комментарий выше про soft-404).
        // <title> берём из SEO-шаблона раздела (админка → раздел → SEO, с учётом
        // наследования от родителя), NAME — только запасной вариант. Раньше
        // <title> принудительно был равен NAME, и заданные в админке SEO-заголовки
        // игнорировались.
        $sectionIProps = new \Bitrix\Iblock\InheritedProperty\SectionValues((int)$iblockId, (int)$arSection["ID"]);
        $sectionIPropValues = $sectionIProps->getValues();
        $sectionMetaTitle = trim((string)($sectionIPropValues["SECTION_META_TITLE"] ?? ''));

        // H1 — поле раздела UF_H1 («Промышленные стиральные машины» при коротком названии «Стиральные
        // машины» в меню), иначе название. Не SEO-шаблон «Заголовок раздела»: тот наследуется подразделами
        // и попадает в хлебные крошки (см. local/migrations/2026-10-13-catalog-01-structure.php).
        $sectionH1 = trim((string)($arSection["UF_H1"] ?? '')) ?: $arSection["NAME"];
        $arResult["CURRENT_SECTION_DATA"]["H1"] = $sectionH1;
        $currentSectionTitleOverride = [
            "H1" => $sectionH1,
            "TITLE" => $sectionMetaTitle !== '' ? $sectionMetaTitle : $arSection["NAME"],
        ];

        // Раздел существует и достижим, но пока без единого товара (ни прямо
        // в нём, ни в подразделах) — легитимная категория каталога, которая
        // ещё не наполнена. Показываем её собственную страницу с понятным
        // сообщением вместо того, чтобы отдавать список товаров ниже, где он
        // может оказаться пустым молча.
        $arResult["CURRENT_SECTION_HAS_ELEMENTS"] = (bool) CIBlockElement::GetList(
            [],
            [
                "IBLOCK_ID" => $iblockId,
                "SECTION_ID" => $arSection["ID"],
                "INCLUDE_SUBSECTIONS" => "Y",
                "ACTIVE" => "Y",
            ],
            false,
            ["nTopCount" => 1],
            ["ID"]
        )->Fetch();
    }

    // ===== SEO-тег (посадочная подборка /product-category/<раздел>/<тег>/) =====
    // Маршрут (local/routes/web.php) уже подставил путь фильтра тега в SMART_FILTER_PATH — умный фильтр
    // и список товаров отработают как для /f/…/. Здесь — мета, текст, индексация и крошка тега.
    $seoTag = null;
    if (!empty($arResult["CURRENT_SECTION_DATA"]) && !empty($_REQUEST["SEO_TAG_ID"]) && class_exists('SeoTags')) {
        $seoTag = SeoTags::byId((int)$_REQUEST["SEO_TAG_ID"]);
        if ($seoTag && $seoTag["SECTION_CODE"] !== $sectionCode) {
            $seoTag = null;
        }
    }
    if ($seoTag) {
        $arResult["CURRENT_SEO_TAG"] = $seoTag;
        $seoTagH1 = $seoTag["H1"] !== '' ? $seoTag["H1"] : ($arResult["CURRENT_SECTION_DATA"]["H1"] . ': ' . $seoTag["NAME"]);
        $seoFilterTitleOverride = [
            "H1" => $seoTagH1,
            "TITLE" => $seoTag["TITLE"] !== '' ? $seoTag["TITLE"] : ($seoTagH1 . ' — купить в ПРОФЭКВИП'),
            "DESCRIPTION" => $seoTag["DESCRIPTION"] !== '' ? $seoTag["DESCRIPTION"] : null,
        ];
        if ($seoTag["SEO_TEXT"] !== '') {
            $arResult["CURRENT_SECTION_DATA"]["DESCRIPTION"] = $seoTag["SEO_TEXT"];
        }
        // мало товаров (ассортимент изменился) — страница остаётся для пользователей, но не индексируется
        $APPLICATION->SetPageProperty("robots", SeoTags::isIndexable($seoTag) ? "index, follow" : "noindex, follow");
        $seoFilterBreadcrumbTitle = $seoTag["NAME"];
    }

    // ===== SEO умного фильтра =====
    // Валидируем каждый сегмент /f/свойство-is-значение/ по реальным свойствам и
    // значениям каталога: несуществующая комбинация -> 404, вместо мягкого 200.
    // Для валидных комбинаций подбираем шаблон META по правилу из инфоблока
    // "seofilterrules" (раздел + свойство), иначе -> noindex + canonical на раздел.
    if (!$seoTag && !empty($arResult["CURRENT_SECTION_DATA"]) && trim((string)($_REQUEST["SMART_FILTER_PATH"] ?? '')) !== '') {
        $smartFilterSegments = array_values(array_filter(explode('/', trim($_REQUEST["SMART_FILTER_PATH"], '/'))));
        $parsedFilterValues = [];
        $smartFilterIsValid = true;

        // Старые адреса фильтра: значение переименовано/слито (слаг сменился), свойство стало числовым
        // (zagruzka_kg-is-25 → zagruzka_kg-from-25-to-25) — 301 на новый адрес (b_profequip_slug_history,
        // пишут миграции 2026-10-13-catalog-*). Новый адрес, совпавший с SEO-тегом, — сразу на тег.
        $legacyRewritten = false;
        $rewrittenSegments = [];
        foreach ($smartFilterSegments as $segment) {
            if (!preg_match('#^([a-z0-9_]+)-is-(.+)$#i', $segment, $segMatch)) {
                $rewrittenSegments[] = $segment;
                continue;
            }
            $propCode = strtolower($segMatch[1]);
            $arProp = CIBlockProperty::GetList([], ["IBLOCK_ID" => $iblockId, "CODE" => strtoupper($propCode)])->Fetch();
            $newValues = [];
            $numeric = [];
            foreach (explode('-or-', $segMatch[2]) as $urlValue) {
                $legacyValueExists = $arProp && $arProp["PROPERTY_TYPE"] === "L" && CIBlockPropertyEnum::GetList([], [
                    "PROPERTY_ID" => $arProp["ID"], "XML_ID" => $urlValue,
                ])->Fetch();
                if ($legacyValueExists) {
                    $newValues[] = $urlValue;
                    continue;
                }
                $history = $GLOBALS['DB']->Query("SELECT NEW_SLUG FROM b_profequip_slug_history WHERE PROPERTY_CODE = '" . $GLOBALS['DB']->ForSql($propCode)
                    . "' AND OLD_SLUG = '" . $GLOBALS['DB']->ForSql(mb_strtolower($urlValue)) . "'")->Fetch();
                if (!$history) {
                    $newValues[] = $urlValue; // ни значения, ни истории — пусть 404 ниже
                    continue;
                }
                $legacyRewritten = true;
                if (str_starts_with($history["NEW_SLUG"], 'num:')) {
                    $numeric[] = (float)substr($history["NEW_SLUG"], 4);
                } elseif ($history["NEW_SLUG"] !== '') {
                    $newValues[] = $history["NEW_SLUG"];
                }
            }
            if ($numeric) {
                $fmt = static fn($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
                $rewrittenSegments[] = $propCode . '-from-' . $fmt(min($numeric)) . '-to-' . $fmt(max($numeric));
            } elseif ($newValues) {
                $rewrittenSegments[] = $propCode . '-is-' . implode('-or-', array_unique($newValues));
            }
        }
        if ($legacyRewritten) {
            $newPath = implode('/', array_filter($rewrittenSegments, static fn($s) => $s !== 'apply'));
            $tagForPath = class_exists('SeoTags') ? SeoTags::matchFilterPath($sectionCode, $newPath) : null;
            // одно старое числовое значение (zagruzka_kg-is-25) — на тег-диапазон, куда оно попадает («25 кг» = 23–26,9)
            if (!$tagForPath && class_exists('SeoTags') && count($rewrittenSegments) === 1
                && preg_match('#^([a-z0-9_]+)-from-([\d.]+)-to-\2$#', $rewrittenSegments[0], $numMatch)) {
                $tagForPath = SeoTags::forNumericValue($sectionCode, $numMatch[1], (float)$numMatch[2]);
            }
            LocalRedirect($tagForPath ? SeoTags::url($tagForPath)
                : ('/product-category/' . $sectionCode . '/' . ($newPath !== '' ? 'f/' . $newPath . '/' : '')), false, '301 Moved permanently');
        }

        // «Наименование детали» (TIP_ZAPCHASTI, 200+ значений, обычно по 1 товару) больше не в фильтре —
        // проиндексированные /f/tip_zapchasti-is-<деталь>/ ведут 301 на тег группы («Ремни и шкивы»)
        if (class_exists('SeoTags') && count($smartFilterSegments) === 1
            && preg_match('#^tip_zapchasti-is-([^/]+)$#', $smartFilterSegments[0], $partMatch)) {
            $partProp = CIBlockProperty::GetList([], ["IBLOCK_ID" => $iblockId, "CODE" => "TIP_ZAPCHASTI"])->Fetch();
            $partEnum = $partProp ? CIBlockPropertyEnum::GetList([], ["PROPERTY_ID" => $partProp["ID"], "XML_ID" => $partMatch[1]])->Fetch() : false;
            if ($partEnum) {
                [$partGroup] = CatalogStandard::partGroup((string)$partEnum["VALUE"], '');
                foreach (SeoTags::forSection($sectionCode, false) as $partTag) {
                    if ($partTag["GROUP"] === 'Тип' && $partTag["NAME"] === $partGroup) {
                        LocalRedirect(SeoTags::url($partTag), false, '301 Moved permanently');
                    }
                }
            }
        }

        // Комбинация совпадает с SEO-тегом раздела — канонический адрес у тега, сюда 301
        if (class_exists('SeoTags') && ($tagForPath = SeoTags::matchFilterPath($sectionCode, (string)$_REQUEST["SMART_FILTER_PATH"]))) {
            LocalRedirect(SeoTags::url($tagForPath), false, '301 Moved permanently');
        }

        foreach ($smartFilterSegments as $segment) {
            if (!preg_match('#^([a-z0-9_]+)-is-(.+)$#i', $segment, $segMatch)) {
                continue; // служебные сегменты (apply, числовые -from-/-to-) не относятся к спискам
            }

            $arProp = CIBlockProperty::GetList([], [
                "IBLOCK_ID" => $iblockId,
                "CODE" => strtoupper($segMatch[1]),
            ])->Fetch();

            $arEnum = false;
            if ($arProp) {
                // несколько значений одного свойства: …-is-a-or-b — валидируем каждое
                foreach (explode('-or-', $segMatch[2]) as $urlValue) {
                    $arEnum = CIBlockPropertyEnum::GetList([], [
                        "IBLOCK_ID" => $iblockId,
                        "PROPERTY_ID" => $arProp["ID"],
                        "XML_ID" => $urlValue,
                    ])->Fetch();
                    if (!$arEnum) {
                        break;
                    }
                }
            }

            if (!$arProp || !$arEnum) {
                $smartFilterIsValid = false;
                break;
            }

            $parsedFilterValues[] = [
                "PROPERTY_CODE" => $arProp["CODE"],
                "VALUE_TEXT" => $arEnum["VALUE"],
                "MULTI" => str_contains($segMatch[2], '-or-'),
            ];
        }

        if (!$smartFilterIsValid) {
            include($_SERVER["DOCUMENT_ROOT"]."/404.php");
            die();
        }

        $seoFilterRuleFound = false;
        // посадочная — только одно свойство с одним значением и без числовых диапазонов
        $hasNumericSegments = (bool)array_filter($smartFilterSegments, static fn($seg) => preg_match('#-(from|to)-#', $seg));
        if (count($parsedFilterValues) === 1 && !$parsedFilterValues[0]["MULTI"] && !$hasNumericSegments) {
            $seoFilterRuleIblockId = GetIBlockIDByCode("seofilterrules");
            $ruleCode = $sectionCode . "__" . strtolower($parsedFilterValues[0]["PROPERTY_CODE"]);

            $obRule = $seoFilterRuleIblockId ? CIBlockElement::GetList([], [
                "IBLOCK_ID" => $seoFilterRuleIblockId,
                "CODE" => $ruleCode,
                "ACTIVE" => "Y",
            ], false, false, ["ID", "IBLOCK_ID"])->GetNextElement() : false;

            if ($obRule) {
                $ruleProps = $obRule->GetProperties();
                $valueText = $parsedFilterValues[0]["VALUE_TEXT"];
                $substitute = function ($template) use ($valueText, $arResult) {
                    return str_replace(
                        ["#VALUE#", "#SECTION_NAME#"],
                        [$valueText, $arResult["CURRENT_SECTION_DATA"]["NAME"]],
                        // "~VALUE" — исходный текст без экранирования спецсимволов
                        // (GetProperties() отдаёт "VALUE" уже через htmlspecialchars).
                        (string)($template["~VALUE"] ?? '')
                    );
                };

                // Заголовок и meta применяются НИЖЕ, после bitrix:catalog.section — тот
                // компонент вызван с SET_TITLE/SET_BROWSER_TITLE/SET_META_*=Y и иначе
                // перетрёт эти значения.
                $seoFilterTitleOverride = [
                    "H1" => !empty($ruleProps["H1_TEMPLATE"]["~VALUE"]) ? $substitute($ruleProps["H1_TEMPLATE"]) : null,
                    "TITLE" => !empty($ruleProps["TITLE_TEMPLATE"]["~VALUE"]) ? $substitute($ruleProps["TITLE_TEMPLATE"]) : null,
                    "DESCRIPTION" => !empty($ruleProps["DESCRIPTION_TEMPLATE"]["~VALUE"]) ? $substitute($ruleProps["DESCRIPTION_TEMPLATE"]) : null,
                ];

                // Текстовый SEO-блок под товарами — применяется сразу, ничего его
                // после этого места в шаблоне больше не перезаписывает.
                if (!empty($ruleProps["SEO_TEXT_TEMPLATE"]["~VALUE"])) {
                    $arResult["CURRENT_SECTION_DATA"]["DESCRIPTION"] = $substitute($ruleProps["SEO_TEXT_TEMPLATE"]);
                }

                $APPLICATION->SetPageProperty("robots", "index, follow");
                $seoFilterRuleFound = true;

                // Хлебная крошка для посадочной страницы фильтра — некликабельный
                // последний пункт с текстом H1 ("Прачечное оборудование Electrolux"),
                // без ссылки. Само добавление в цепочку (AddChainItem) отложено до
                // ПОСЛЕ вызова bitrix:catalog.section ниже по шаблону: bitrix:breadcrumb
                // строит цепочку лениво при выводе буфера (см. комментарий в его
                // template.php), то есть важен порядок ВЫЗОВОВ AddChainItem за всё
                // время рендера страницы, а не место, где сам bitrix:breadcrumb
                // вызван в HTML. bitrix:catalog.section (ADD_SECTIONS_CHAIN) добавляет
                // свой пункт "Прачечное оборудование" в момент своего исполнения —
                # если вызвать AddChainItem здесь, наш пункт окажется ПЕРЕД разделом.
                $seoFilterBreadcrumbTitle = $seoFilterTitleOverride["H1"]
                    ?? ($arResult["CURRENT_SECTION_DATA"]["NAME"] . " " . $valueText);
            }
        }

        if (!$seoFilterRuleFound) {
            // Комбинация валидна, но не размечена как посадочная: не мусорим индекс,
            // но и не блокируем обход — canonical схлопывает её к странице раздела.
            $APPLICATION->SetPageProperty("robots", "noindex, follow");
            $APPLICATION->SetPageProperty("canonical_override", "/product-category/" . $sectionCode . "/");
        }
    }
}

    ?>

        <section class="catalog">
            <div class="catalog__inner container">
                <div class="title-block">
                    <h1 class="title-block__title"><?$APPLICATION->ShowTitle(false)?></h1>
                    <?$APPLICATION->IncludeComponent(
                        "bitrix:breadcrumb",
                        "",
                        array(
                            "START_FROM" => "0",
                            "PATH" => "",
                            "SITE_ID" => "s1"
                        )
                    ); ?> 
                </div>
                <?
                // Над листингом: плитки подкатегорий с картинками + «Популярные подборки» (SEO-теги раздела).
                // Заменяют список подразделов в сайдбаре — ссылки над товарами кликают чаще, чем в фильтре.
                include __DIR__ . '/include/catalog_top.php';
                ?>
                <div class="catalog__content catalog__content--toolbar">
                <div class="catalog__features catalog__features--inline"><button class="btn filter__btn"><svg>
                            <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#filter"></use>
                        </svg>Фильтр</button>
                    <span class="catalog__total js-product-count"><?$APPLICATION->ShowViewContent('catalog_counter');?></span>    
                    <div class="catalog__list-view"><button class="btn catalog-view__btn active" data-view="columns"><svg>
                                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#columns"></use>
                            </svg></button><button class="btn catalog-view__btn" data-view="lines"><svg>
                                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#lines"></use>
                            </svg></button></div>
                </div>
                    <sidebar class="catalog-sidebar">
                            <?$APPLICATION->IncludeComponent(
                                "bitrix:catalog.smart.filter",
                                "",
                                array(
                                    "IBLOCK_TYPE" => "catalog",
                                    "IBLOCK_ID" =>  $arParams["IBLOCK_ID"],
                                    'SECTION_ID' => $arResult["CURRENT_SECTION_DATA"]["ID"],
                                    'HIDE_NOT_AVAILABLE' => $arParams["HIDE_NOT_AVAILABLE"],
                                    "FILTER_NAME" => "arrFilter",
                                    "SEF_MODE"=>"Y",
                                    "SEF_RULE"=> "/product-category/#SECTION_CODE#/f/#SMART_FILTER_PATH#/",
                                    "SMART_FILTER_PATH" => $_REQUEST["SMART_FILTER_PATH"],
                                    "PRICE_CODE" => array(),
                                    "DISPLAY_ELEMENT_COUNT"=>"Y",
                                    "CACHE_TYPE" => "A",
                                    "CACHE_TIME" => "36000000",
                                    "CACHE_GROUPS" => "Y",
                                    "SAVE_IN_SESSION" => "N",
                                    "FILTER_VIEW_MODE" => "VERTICAL",
                                    "XML_EXPORT" => "Y",
                                    "SECTION_TITLE" => "NAME",
                                    // Y: товары подразделов тоже (товары больше не дублируются в родительских разделах —
                                    // см. local/migrations/2026-10-13-catalog-01-structure.php)
                                    "INCLUDE_SUBSECTIONS" => "Y",
                                    "SECTION_DESCRIPTION" => "DESCRIPTION",
                                    "SEO_TAG_ID" => $seoTag ? $seoTag["ID"] : 0
                                ),
                                $component,
                                array('HIDE_ICONS' => 'Y')
                            );?>
                    </sidebar>
                    <div class="catalog__list-wrapper js-product-container">
                        <?
                        // CURRENT_SECTION_HAS_ELEMENTS выставляется только когда запрошен
                        // конкретный раздел (см. выше); при обычном просмотре каталога без
                        // раздела оно не установлено, и список товаров показывается как раньше.
                        if (isset($arResult["CURRENT_SECTION_HAS_ELEMENTS"]) && !$arResult["CURRENT_SECTION_HAS_ELEMENTS"]):
                        ?>
                            <p class="catalog__empty">В этой категории пока нет товаров. Загляните позже — ассортимент пополняется.</p>
                        <?else:?>
                        <?$APPLICATION->IncludeComponent(
                            'bitrix:catalog.section',
                            '',
                            [
                                'IBLOCK_TYPE' => 'catalog',
                                'IBLOCK_ID' => $arParams["IBLOCK_ID"],
                                'SECTION_ID' => $arResult["CURRENT_SECTION_DATA"]["ID"],
                                "INCLUDE_SUBSECTIONS" => "Y",
                                'SECTION_CODE' => '',
                                "FILTER_NAME" => "arrFilter",
                                'PROPERTYS_PREVIEW' => [
                                    'SIZE',
                                    'COLOR',     
                                    'MATERIAL',  
                                ],

                                'ELEMENT_SORT_FIELD' => $sortField,
                                'ELEMENT_SORT_ORDER' => $sortOrder,
                                'ELEMENT_SORT_FIELD2' => $sortField2,
                                'ELEMENT_SORT_ORDER2' => $sortOrder2,
                                "ADD_SECTIONS_CHAIN" => $arParams["ADD_SECTIONS_CHAIN"],
                                // Указываем, какие свойства загружать
                                'PROPERTY_CODE' => ['SIZE', 'COLOR', 'MATERIAL'],
                                'HIDE_NOT_AVAILABLE' => $arParams["HIDE_NOT_AVAILABLE"],
                                "HIDE_NOT_AVAILABLE_OFFERS" => $arParams["HIDE_NOT_AVAILABLE_OFFERS"],

                                'SET_TITLE' => 'Y',
                                "USE_FILTER" => "Y",
                                'SET_BROWSER_TITLE' => 'Y',
                                'SET_META_KEYWORDS' => 'Y',
                                'SET_META_DESCRIPTION' => 'Y',
                                'PAGE_ELEMENT_COUNT' => '20',
                                "PAGER_TEMPLATE" => "arrows_custom",
                                
                                'PRICE_CODE' => ['BASE_PRICE'],
                                'USE_PRICE_COUNT' => 'N',
                                'SHOW_PRICE_COUNT' => '1',
                                'CONVERT_CURRENCY' => 'Y',
                                'CURRENCY_ID' => 'RUB',
                                'PRICE_VAT_INCLUDE' => 'Y',

                                'CACHE_TYPE' => 'A',
                                'CACHE_TIME' => '3600',
                                'CACHE_GROUPS' => 'Y',
                                
                                'AJAX_MODE' => 'N',
                                'AJAX_OPTION_JUMP' => 'N',
                                'AJAX_OPTION_STYLE' => 'Y',
                            ],
                            $component
                        );
                        endif;
                        ?>
                        <?
                        // Заголовок раздела — см. комментарий у $currentSectionTitleOverride
                        // выше: bitrix:catalog.section не всегда корректно резолвит title для
                        // раздела без товаров, поэтому подстраховываемся собственным значением.
                        if (!empty($currentSectionTitleOverride)) {
                            $APPLICATION->SetTitle($currentSectionTitleOverride["H1"]);
                            $APPLICATION->SetPageProperty("title", $currentSectionTitleOverride["TITLE"]);
                        }
                        // SEO умного фильтра — применяем ПОСЛЕ bitrix:catalog.section, который
                        // сам выставляет title/description (SET_TITLE и т.п. выше) и иначе
                        // затёр бы шаблонные значения, подготовленные выше по файлу.
                        if (!empty($seoFilterTitleOverride)) {
                            if ($seoFilterTitleOverride["H1"] !== null) {
                                $APPLICATION->SetTitle($seoFilterTitleOverride["H1"]);
                            }
                            if ($seoFilterTitleOverride["TITLE"] !== null) {
                                $APPLICATION->SetPageProperty("title", $seoFilterTitleOverride["TITLE"]);
                            }
                            if ($seoFilterTitleOverride["DESCRIPTION"] !== null) {
                                $APPLICATION->SetPageProperty("description", $seoFilterTitleOverride["DESCRIPTION"]);
                            }
                        }
                        // Хлебная крошка посадочной страницы фильтра — добавляем ТОЛЬКО
                        // сейчас, после bitrix:catalog.section выше: он сам кладёт в цепочку
                        // пункт раздела ("Прачечное оборудование") через ADD_SECTIONS_CHAIN,
                        // и наш пункт должен идти ПОСЛЕ него (см. комментарий у
                        // $seoFilterBreadcrumbTitle выше, где объясняется, почему тут, а не там).
                        if (!empty($seoFilterBreadcrumbTitle)) {
                            $APPLICATION->AddChainItem($seoFilterBreadcrumbTitle, false);
                        }
                        ?>
                    </div>
                </div>
                <?if ($arResult["CURRENT_SECTION_DATA"]["FAQ"]):?>
                    <?global $arFilterFaq;
                    $arFilterFaq = array("ID" => $arResult["CURRENT_SECTION_DATA"]["FAQ"]);?>
                    <?$APPLICATION->IncludeComponent("bitrix:news.list", "faq", array(
                        "IBLOCK_TYPE" => "content",
                        "IBLOCK_ID" => GetIBlockIDByCode("faq"),
                        "NEWS_COUNT" => "40",
                        "SORT_BY1" => "ACTIVE_FROM",
                        "SORT_ORDER1" => "DESC",
                        "SORT_BY2" => "SORT",
                        "SORT_ORDER2" => "ASC",
                        "FILTER_NAME" => "arFilterFaq",
                        "FIELD_CODE" => array("ID", "NAME", "PREVIEW_TEXT", "PREVIEW_PICTURE", "DATE_ACTIVE_FROM"),
                        "PROPERTY_CODE" => array("COUNTRY", "YEAR"),
                        "CHECK_DATES" => "Y",
                        "DETAIL_URL" => "",
                        "AJAX_MODE" => "N",
                        "AJAX_OPTION_JUMP" => "N",
                        "AJAX_OPTION_STYLE" => "Y",
                        "AJAX_OPTION_HISTORY" => "N",
                        "CACHE_TYPE" => "A",
                        "CACHE_TIME" => "36000000",
                        "CACHE_FILTER" => "N",
                        "CACHE_GROUPS" => "Y",
                        "PREVIEW_TRUNCATE_LEN" => "",
                        "ACTIVE_DATE_FORMAT" => "d.m.Y",
                        "SET_TITLE" => "N",
                        "SET_BROWSER_TITLE" => "N",
                        "SET_META_KEYWORDS" => "N",
                        "SET_META_DESCRIPTION" => "N",
                        "SET_LAST_MODIFIED" => "N",
                        "INCLUDE_IBLOCK_INTO_CHAIN" => "N",
                        "ADD_SECTIONS_CHAIN" => "N",
                        "HIDE_LINK_WHEN_NO_DETAIL" => "N",
                        "PARENT_SECTION" => "",
                        "PARENT_SECTION_CODE" => "",
                        "INCLUDE_SUBSECTIONS" => "Y",
                        "STRICT_SECTION_CHECK" => "N",
                        "DISPLAY_DATE" => "Y",
                        "DISPLAY_NAME" => "Y",
                        "DISPLAY_PICTURE" => "Y",
                        "DISPLAY_PREVIEW_TEXT" => "Y",
                        "PAGER_TEMPLATE" => "arrows_custom",
                        "DISPLAY_TOP_PAGER" => "N",
                        "DISPLAY_BOTTOM_PAGER" => "Y",
                        "PAGER_TITLE" => "Новости",
                        "PAGER_SHOW_ALWAYS" => "N",
                        "PAGER_DESC_NUMBERING" => "N",
                        "PAGER_DESC_NUMBERING_CACHE_TIME" => "36000",
                        "PAGER_SHOW_ALL" => "N",
                        "PAGER_BASE_LINK_ENABLE" => "N",
                        "SET_STATUS_404" => "N",
                        "SHOW_404" => "N",
                        "TITLE_BLOCK"=>"Проекты",
                        "MESSAGE_404" => ""
                    ),
                    false
                    );
                    ?>
                <?endif;?>
                <div class="catalog__text">
                   <?=$arResult["CURRENT_SECTION_DATA"]["DESCRIPTION"];?>
                </div>
            </div>
        </section>