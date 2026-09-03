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
        $currentSectionTitleOverride = [
            "H1" => $arSection["NAME"],
            "TITLE" => $arSection["NAME"],
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

    // ===== SEO умного фильтра =====
    // Валидируем каждый сегмент /f/свойство-is-значение/ по реальным свойствам и
    // значениям каталога: несуществующая комбинация -> 404, вместо мягкого 200.
    // Для валидных комбинаций подбираем шаблон META по правилу из инфоблока
    // "seofilterrules" (раздел + свойство), иначе -> noindex + canonical на раздел.
    if (!empty($arResult["CURRENT_SECTION_DATA"]) && trim((string)($_REQUEST["SMART_FILTER_PATH"] ?? '')) !== '') {
        $smartFilterSegments = array_values(array_filter(explode('/', trim($_REQUEST["SMART_FILTER_PATH"], '/'))));
        $parsedFilterValues = [];
        $smartFilterIsValid = true;

        foreach ($smartFilterSegments as $segment) {
            if (!preg_match('#^([a-z0-9_]+)-is-(.+)$#i', $segment, $segMatch)) {
                continue; // служебные сегменты (apply и т.п.) не относятся к свойствам
            }

            $arProp = CIBlockProperty::GetList([], [
                "IBLOCK_ID" => $iblockId,
                "CODE" => strtoupper($segMatch[1]),
            ])->Fetch();

            $arEnum = $arProp ? CIBlockPropertyEnum::GetList([], [
                "IBLOCK_ID" => $iblockId,
                "PROPERTY_ID" => $arProp["ID"],
                "XML_ID" => $segMatch[2],
            ])->Fetch() : false;

            if (!$arProp || !$arEnum) {
                $smartFilterIsValid = false;
                break;
            }

            $parsedFilterValues[] = [
                "PROPERTY_CODE" => $arProp["CODE"],
                "VALUE_TEXT" => $arEnum["VALUE"],
            ];
        }

        if (!$smartFilterIsValid) {
            include($_SERVER["DOCUMENT_ROOT"]."/404.php");
            die();
        }

        $seoFilterRuleFound = false;
        if (count($parsedFilterValues) === 1) {
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
                <div class="catalog__features"><button class="btn filter__btn"><svg>
                            <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#filter"></use>
                        </svg>Фильтр</button>
                    <?$APPLICATION->ShowViewContent('catalog_counter');?>    
                    <div class="catalog__list-view"><button class="btn catalog-view__btn active" data-view="columns"><svg>
                                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#columns"></use>
                            </svg></button><button class="btn catalog-view__btn" data-view="lines"><svg>
                                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#lines"></use>
                            </svg></button></div>
                </div>
                <div class="catalog__content">
                    <sidebar class="catalog-sidebar">
                        <?$APPLICATION->IncludeComponent(
                                "bitrix:catalog.section.list", 
                                "section_catalog", 
                                array(
                                    "IBLOCK_ID" => $arParams["IBLOCK_ID"],
                                    "IBLOCK_TYPE" => "catalog",
                                    "FILTER_NAME" => "",
                                    "SECTION_ID" => $arResult["CURRENT_SECTION_DATA"]["ID"],
                                    "COUNT_ELEMENTS" => "N",
                                    "TOP_DEPTH" => 12, 
                                    "SECTION_FIELDS" => array("NAME", "CODE", "PICTURE", "DESCRIPTION"),
                                    "SECTION_USER_FIELDS" => array("UF_*"),
                                    "CACHE_TYPE" => "A",
                                    "CACHE_TIME" => "36000000",
                                    "CACHE_GROUPS" => "Y",
                                    "ADD_SECTIONS_CHAIN" => "N",
                                    "VIEW_MODE" => "LIST",
                                    "SHOW_PARENT_NAME" => "Y",
                                    "COMPONENT_TEMPLATE" => "section_categories"
                                ),
                                false
                            );?>
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
                                    "INCLUDE_SUBSECTIONS" => "N",
                                    "SECTION_DESCRIPTION" => "DESCRIPTION"
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
                                
                                'PRICE_CODE' => ['BASE'],
                                'USE_PRICE_COUNT' => 'N',
                                'SHOW_PRICE_COUNT' => '1',
                                
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