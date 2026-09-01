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

if (!empty($arResult["VARIABLES"]["SECTION_CODE"])) {
    $sectionCode = $arResult["VARIABLES"]["SECTION_CODE"];
    $iblockId = $arParams["IBLOCK_ID"]; // ID вашего инфоблока
    
    $arFilter = array(
        "IBLOCK_ID" => $iblockId,
        "CODE" => $sectionCode,
        "ACTIVE" => "Y"
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