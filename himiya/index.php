<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Профессиональная химия для прачечных, химчисток, аквачисток. Средства для стирки. Аксессуары для прачечных и химчисток. Проверенная химия и аксессуары.");
$APPLICATION->SetPageProperty("title", "Химия для химчисток, прачечных, средства для стирки");
$APPLICATION->SetTitle("Химия");
$arProps = GetPropDirectionByCode("chemistry",GetIBlockIDByCode("chemistry"));
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
        "SET_TITLE" => "Y",
        "SET_BROWSER_TITLE" => "Y",
        "SET_META_KEYWORDS" => "N",
        "SET_META_DESCRIPTION" => "Y",
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
        "chemistry_page", 
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
        "IBLOCK_ID" => GetIBlockIDByCode("chemistry"),
        "ELEMENT_CODE" => "chemistry",
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

<?if ($arProps["STEPS"]):?>
    <?global $arFilterSteps;
    $arFilterSteps = array("ID" => $arProps["STEPS"]);?>
    <?$APPLICATION->IncludeComponent("bitrix:news.list", "steps_in_directory_chemistry", array(
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
<section class="chemistry-form">
    <div class="chemistry-form__bg">
        <picture>
            <source srcset="<?=LAYOUT_DIR?>assets/img/chemistry-form/bg.webp, <?=LAYOUT_DIR?>assets/img/chemistry-form/bg@2x.webp 2x" type="image/webp">
            <img src="<?=LAYOUT_DIR?>assets/img/chemistry-form/bg.jpg" srcset="<?=LAYOUT_DIR?>assets/img/chemistry-form/bg.jpg, <?=LAYOUT_DIR?>assets/img/chemistry-form/bg@2x.jpg 2x" alt="bg">
        </picture>
    </div>
    <div class="chemistry-form__inner container">
        <?$APPLICATION->IncludeComponent(
            "bitrix:news.detail", 
            "chemistry_form", 
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
            "IBLOCK_ID" => GetIBlockIDByCode("chemistry"),
            "ELEMENT_CODE" => "chemistry",
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
    </div>
</section>
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

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>