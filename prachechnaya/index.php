<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "▶ Оснащение прачечных и химчисток профессиональным оборудованием. Запуск прачечных и химчисток с нуля. Консалтинг, проектирование, монтаж, сервис от группы компаний ПРОФЭКВИП.");
$APPLICATION->SetPageProperty("title", "Комплексное оснащение и проектирование прачечных и химчисток под ключ");
$APPLICATION->SetTitle("Прачечная");
$arProps = GetPropDirectionByCode("prachechnaya",GetIBlockIDByCode("prachechnaya"));
?>
<?if ($arProps["BANNERS"]):?>
    <?global $arFilterProjects;
    $arFilterProjects = array("ID" => $arProps["BANNERS"]);?>
    <?$APPLICATION->IncludeComponent("bitrix:news.list", "banners", array(
        "IBLOCK_TYPE" => "content",
        "IBLOCK_ID" => GetIBlockIDByCode("banners"),
        "NEWS_COUNT" => "20",
        "SORT_BY1" => "ACTIVE_FROM",
        "SORT_ORDER1" => "DESC",
        "SORT_BY2" => "SORT",
        "SORT_ORDER2" => "ASC",
        "FILTER_NAME" => "arFilterProjects",
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
        <?$APPLICATION->IncludeComponent(
                "bitrix:breadcrumb",
                "",
                array(
                    "START_FROM" => "0",
                    "PATH" => "",
                    "SITE_ID" => "s1"
                )
            ); ?> 
    <?$APPLICATION->IncludeComponent(
        "bitrix:news.detail", 
        "kuhnya_page", 
        array(
        "DISPLAY_DATE" => "Y",
        "DISPLAY_NAME" => "Y",
        "DISPLAY_PICTURE" => "Y",
        "DISPLAY_PREVIEW_TEXT" => "Y",
        "USE_SHARE" => "Y",
        "SHARE_HIDE" => "N",
        "SHARE_TEMPLATE" => "",
        "SHARE_HANDLERS" => array(
            0 => "delicious",
        ),
        "SHARE_SHORTEN_URL_LOGIN" => "",
        "SHARE_SHORTEN_URL_KEY" => "",
        "AJAX_MODE" => "N",
        "IBLOCK_ID" => GetIBlockIDByCode("prachechnaya"),
        "ELEMENT_CODE" => "prachechnaya",
        "CHECK_DATES" => "Y",
        "FIELD_CODE" => array(
            0 => "ID",
            1 => "",
        ),
        "PROPERTY_CODE" => array(
            0 => "MAP",
            1 => "DESCRIPTION",
            2 => "",
        ),
        "IBLOCK_URL" => "",
        "DETAIL_URL" => "",
        "SET_TITLE" => "Y",
        "SET_CANONICAL_URL" => "N",
        "SET_BROWSER_TITLE" => "N",
        "BROWSER_TITLE" => "-",
        "SET_META_KEYWORDS" => "N",
        "META_KEYWORDS" => "-",
        "SET_META_DESCRIPTION" => "N",
        "META_DESCRIPTION" => "-",
        "SET_STATUS_404" => "N",
        "SET_LAST_MODIFIED" => "N",
        "INCLUDE_IBLOCK_INTO_CHAIN" => "N",
        "ADD_SECTIONS_CHAIN" => "N",
        "ADD_ELEMENT_CHAIN" => "N",
        "ACTIVE_DATE_FORMAT" => "d.m.Y",
        "USE_PERMISSIONS" => "N",
        "GROUP_PERMISSIONS" => array(
            0 => "1",
        ),
        "CACHE_TYPE" => "A",
        "CACHE_TIME" => "3600",
        "CACHE_GROUPS" => "Y",
        "DISPLAY_TOP_PAGER" => "Y",
        "DISPLAY_BOTTOM_PAGER" => "Y",
        "PAGER_TITLE" => "Страница",
        "PAGER_TEMPLATE" => "",
        "PAGER_SHOW_ALL" => "Y",
        "PAGER_BASE_LINK_ENABLE" => "Y",
        "SHOW_404" => "N",
        "MESSAGE_404" => "",
        "STRICT_SECTION_CHECK" => "Y",
        "PAGER_BASE_LINK" => "",
        "PAGER_PARAMS_NAME" => "arrPager",
        "AJAX_OPTION_JUMP" => "N",
        "AJAX_OPTION_STYLE" => "Y",
        "AJAX_OPTION_HISTORY" => "N",
        "COMPONENT_TEMPLATE" => "about",
        "AJAX_OPTION_ADDITIONAL" => "",
        "FILE_404" => ""
        ),
        false
    );?>
<?if ($arProps["CATALOG_PRODUCTS"]):?>
    <?global $arFilterCatalog;
    $arFilterCatalog = array("ID" => $arProps["CATALOG_PRODUCTS"]);?>
    <?$APPLICATION->IncludeComponent(
        'bitrix:catalog.section',
        'catalog_slider',
        [
            'IBLOCK_TYPE' => 'catalog',
            'IBLOCK_ID' => GetIBlockIDByCode("catalog"),
            'SECTION_ID' => '',
            'SECTION_CODE' => '',
            'FILTER_NAME' => 'arFilterCatalog',
            'ELEMENT_SORT_FIELD' => '',
            'ELEMENT_SORT_ORDER' => 'ASC',
            'PAGE_ELEMENT_COUNT' => '20',
            'PROPERTY_CODE' => ['COLOR', 'SIZE', 'ARTICLE'],
            'SET_TITLE' => 'N',
            'SET_BROWSER_TITLE' => 'N',
            'SET_META_KEYWORDS' => 'N',
            'SET_META_DESCRIPTION' => 'N',
            'SHOW_ALL_WO_SECTION' => 'Y',
            'CACHE_TYPE' => 'A',
            'CACHE_TIME' => '3600',
            'CACHE_GROUPS' => 'Y',
        ],
        $component
    );
    ?>
<?endif;?>    
        <section class="calculate-project filled-bg">
            <div class="calculate-project__inner container">
                <div class="calculate-project__text">
                    <div class="calculate-project__text_top">
                        <h2 class="calculate-project__title">Рассчитать проект</h2>
                    </div>
                    <? $phoneMain = getMainPhoneFromIblock();
                    if ($phoneMain):?>
                    <div class="calculate-project__text_bottom"><a class="calculate-project__phone" href="tel:<?=$phoneMain;?>"><img src="<?=LAYOUT_DIR;?>assets/img/icons/phone.svg" alt="phone"><?=$phoneMain;?></a>
                        <p class="calculate-project__alert">Или позвоните нам по телефону, мы всегда на связи!</p>
                    </div>
                    <?endif;?>
                </div>
                                <?$APPLICATION->IncludeComponent(
                            "bitrix:form.result.new",
                            "calculate-project",
                            Array(
                                "CACHE_TIME" => "3600",
                                "CACHE_TYPE" => "A",
                                "CHAIN_ITEM_LINK" => "",
                                "CHAIN_ITEM_TEXT" => "",
                                "EDIT_URL" => "result_edit.php",
                                "IGNORE_CUSTOM_TEMPLATE" => "N",
                                "LIST_URL" => "/local/ajax/form/",
                                "SEF_MODE" => "N",
                                "SUCCESS_URL" => "",
                                "USE_EXTENDED_ERRORS" => "N",
                                "VARIABLE_ALIASES" => array("RESULT_ID"=>"RESULT_ID","WEB_FORM_ID"=>"WEB_FORM_ID",),
                                "WEB_FORM_ID" => 2,
                            )
                            );
                        ?>
            </div>
        </section>
<?if ($arProps["PROJECTS"]):?>
    <?global $arFilterProjects;
    $arFilterProjects = array("ID" => $arProps["PROJECTS"]);?>
    <?$APPLICATION->IncludeComponent("bitrix:news.list", "projects_in_directory", array(
        "IBLOCK_TYPE" => "content",
        "IBLOCK_ID" => GetIBlockIDByCode("projects"),
        "NEWS_COUNT" => "20",
        "SORT_BY1" => "ACTIVE_FROM",
        "SORT_ORDER1" => "DESC",
        "SORT_BY2" => "SORT",
        "SORT_ORDER2" => "ASC",
        "FILTER_NAME" => "arFilterProjects",
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
<?if ($arProps["BRANDS"]):?>
    <?global $arFilterBrands;
    $arFilterBrands = array("ID" => $arProps["BRANDS"]);?>
    <?$APPLICATION->IncludeComponent("bitrix:news.list", "brands_slider", array(
        "IBLOCK_TYPE" => "content",
        "IBLOCK_ID" => GetIBlockIDByCode("brands"),
        "NEWS_COUNT" => "20",
        "SORT_BY1" => "ACTIVE_FROM",
        "SORT_ORDER1" => "DESC",
        "SORT_BY2" => "SORT",
        "SORT_ORDER2" => "ASC",
        "FILTER_NAME" => "arFilterBrands",
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
<?if ($arProps["STEPS"]):?>
    <?global $arFilterSteps;
    $arFilterSteps = array("ID" => $arProps["STEPS"]);?>
    <?$APPLICATION->IncludeComponent("bitrix:news.list", "steps_in_directory", array(
        "IBLOCK_TYPE" => "content",
        "IBLOCK_ID" => GetIBlockIDByCode("steps"),
        "NEWS_COUNT" => "20",
        "SORT_BY1" => "ACTIVE_FROM",
        "SORT_ORDER1" => "DESC",
        "SORT_BY2" => "SORT",
        "SORT_ORDER2" => "ASC",
        "FILTER_NAME" => "arFilterSteps",
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
<?if ($arProps["CATEGORIES_CATALOG"]):?>
<? global $arrSectionFilter;
$arrSectionFilter = array(
    'ID' => $arProps["CATEGORIES_CATALOG"]
);
?>

<?$APPLICATION->IncludeComponent(
    "bitrix:catalog.section.list", 
    "section_in_directory", 
    array(
        "IBLOCK_ID" => GetIBlockIDByCode("catalog"),
        "IBLOCK_TYPE" => "catalog",
        "FILTER_NAME" => "arrSectionFilter", // Имя переменной с фильтром
        "SECTION_ID" => "",
        "COUNT_ELEMENTS" => "N",
        "TOP_DEPTH" => 4, 
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
<?endif;?>
<?if ($arProps["PROJECTS"]):?>
    <?global $arFilterProjects;
    $arFilterProjects = array("ID" => $arProps["PROJECTS"]);?>
    <?$APPLICATION->IncludeComponent("bitrix:news.list", "projects_in_directory", array(
        "IBLOCK_TYPE" => "content",
        "IBLOCK_ID" => GetIBlockIDByCode("projects"),
        "NEWS_COUNT" => "20",
        "SORT_BY1" => "ACTIVE_FROM",
        "SORT_ORDER1" => "DESC",
        "SORT_BY2" => "SORT",
        "SORT_ORDER2" => "ASC",
        "FILTER_NAME" => "arFilterProjects",
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
<?if ($arProps["SOLUTION"]):?>
    <?global $arFilterSolution;
    $arFilterSolution = array("ID" => $arProps["SOLUTION"]);?>
    <?$APPLICATION->IncludeComponent("bitrix:news.list", "solution_in_directory", array(
        "IBLOCK_TYPE" => "content",
        "IBLOCK_ID" => GetIBlockIDByCode("solution"),
        "NEWS_COUNT" => "20",
        "SORT_BY1" => "ACTIVE_FROM",
        "SORT_ORDER1" => "DESC",
        "SORT_BY2" => "SORT",
        "SORT_ORDER2" => "ASC",
        "FILTER_NAME" => "arFilterSolution",
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
<?if ($arProps["REVIEWS"]):?>
    <?global $arFilterReviews;
    $arFilterReviews = array("ID" => $arProps["REVIEWS"]);?>
    <?$APPLICATION->IncludeComponent("bitrix:news.list", "reviews_in_directory", array(
        "IBLOCK_TYPE" => "content",
        "IBLOCK_ID" => GetIBlockIDByCode("reviews"),
        "NEWS_COUNT" => "20",
        "SORT_BY1" => "ACTIVE_FROM",
        "SORT_ORDER1" => "DESC",
        "SORT_BY2" => "SORT",
        "SORT_ORDER2" => "ASC",
        "FILTER_NAME" => "arFilterReviews",
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
<?if ($arProps["FAQ"]):?>
    <?global $arFilterFaq;
    $arFilterFaq = array("ID" => $arProps["FAQ"]);?>
    <?$APPLICATION->IncludeComponent("bitrix:news.list", "faq", array(
        "IBLOCK_TYPE" => "content",
        "IBLOCK_ID" => GetIBlockIDByCode("faq"),
        "NEWS_COUNT" => "20",
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
    <div class="sale-banner">
        <div class="sale-banner__content"><button class="btn sale-banner__close" type="button" aria-label="Закрыть"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M9.90159 8.00004L15.6062 2.29544C16.1313 1.77033 16.1313 0.918951 15.6062 0.393911C15.0811 -0.131204 14.2298 -0.131204 13.7047 0.393911L7.99998 6.09858L2.29531 0.393836C1.7702 -0.131279 0.918895 -0.131279 0.39378 0.393836C-0.13126 0.918951 -0.13126 1.77033 0.39378 2.29537L6.09845 7.99996L0.39378 13.7046C-0.13126 14.2297 -0.13126 15.0811 0.39378 15.6062C0.918895 16.1313 1.7702 16.1313 2.29531 15.6062L7.99998 9.90149L13.7047 15.6062C14.2297 16.1313 15.0811 16.1313 15.6062 15.6062C16.1313 15.081 16.1313 14.2297 15.6062 13.7046L9.90159 8.00004Z" fill="white"></path>
                </svg></button>
                <a class="sale-banner__link" href="/product-category/prachechnoe-oborudovanie-v-nalichii/" target="_blank" rel="noopener"><img src="<?=LAYOUT_DIR;?>assets/img/sale-promo/promo.png" alt="Складской запас по спецценам"></a></div>
    </div>
<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>