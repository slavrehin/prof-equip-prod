<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true){die();}
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
$this->setFrameMode(true);
?>
<section class="project-content">
    <div class="project-content__inner container">
        <div class="project-content__wrapper">
            <div class="title-block">
                <h1 class="title-block__title"><?=GetNameByCode($arResult["VARIABLES"]["ELEMENT_CODE"],$arParams["IBLOCK_ID"]);?></h1>
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
                $ElementID = $APPLICATION->IncludeComponent(
                    "bitrix:news.detail",
                    "project-detail",
                    [
                        "DISPLAY_DATE" => $arParams["DISPLAY_DATE"],
                        "DISPLAY_NAME" => $arParams["DISPLAY_NAME"],
                        "DISPLAY_PICTURE" => $arParams["DISPLAY_PICTURE"],
                        "DISPLAY_PREVIEW_TEXT" => $arParams["DISPLAY_PREVIEW_TEXT"],
                        "IBLOCK_TYPE" => $arParams["IBLOCK_TYPE"],
                        "IBLOCK_ID" => $arParams["IBLOCK_ID"],
                        "FIELD_CODE" => $arParams["DETAIL_FIELD_CODE"],
                        "PROPERTY_CODE" => $arParams["DETAIL_PROPERTY_CODE"],
                        "DETAIL_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["detail"],
                        "SECTION_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["section"],
                        "META_KEYWORDS" => $arParams["META_KEYWORDS"],
                        "META_DESCRIPTION" => $arParams["META_DESCRIPTION"],
                        "BROWSER_TITLE" =>  "Y",
                        "SET_CANONICAL_URL" => $arParams["DETAIL_SET_CANONICAL_URL"],
                        "SET_LAST_MODIFIED" => $arParams["SET_LAST_MODIFIED"],
                        "SET_TITLE" => "Y",
                        "MESSAGE_404" => $arParams["MESSAGE_404"],
                        "SET_STATUS_404" => $arParams["SET_STATUS_404"],
                        "SHOW_404" => $arParams["SHOW_404"],
                        "FILE_404" => $arParams["FILE_404"],
                        "INCLUDE_IBLOCK_INTO_CHAIN" => "N",
                        "ADD_SECTIONS_CHAIN" => $arParams["ADD_SECTIONS_CHAIN"],
                        "ACTIVE_DATE_FORMAT" => $arParams["DETAIL_ACTIVE_DATE_FORMAT"],
                        "CACHE_TYPE" => $arParams["CACHE_TYPE"],
                        "CACHE_TIME" => $arParams["CACHE_TIME"],
                        "CACHE_GROUPS" => $arParams["CACHE_GROUPS"],
                        "USE_PERMISSIONS" => $arParams["USE_PERMISSIONS"],
                        "GROUP_PERMISSIONS" => $arParams["GROUP_PERMISSIONS"],
                        "DISPLAY_TOP_PAGER" => $arParams["DETAIL_DISPLAY_TOP_PAGER"],
                        "DISPLAY_BOTTOM_PAGER" => $arParams["DETAIL_DISPLAY_BOTTOM_PAGER"],
                        "PAGER_TITLE" => $arParams["DETAIL_PAGER_TITLE"],
                        "PAGER_SHOW_ALWAYS" => "N",
                        "PAGER_TEMPLATE" => $arParams["DETAIL_PAGER_TEMPLATE"],
                        "PAGER_SHOW_ALL" => $arParams["DETAIL_PAGER_SHOW_ALL"],
                        "CHECK_DATES" => $arParams["CHECK_DATES"],
                        "ELEMENT_ID" => $arResult["VARIABLES"]["ELEMENT_ID"],
                        "ELEMENT_CODE" => $arResult["VARIABLES"]["ELEMENT_CODE"],
                        "SECTION_ID" => $arResult["VARIABLES"]["SECTION_ID"],
                        "SECTION_CODE" => $arResult["VARIABLES"]["SECTION_CODE"],
                        "IBLOCK_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["news"],
                        "USE_SHARE" => $arParams["USE_SHARE"],
                        "SHARE_HIDE" => $arParams["SHARE_HIDE"],
                        "SHARE_TEMPLATE" => $arParams["SHARE_TEMPLATE"],
                        "SHARE_HANDLERS" => $arParams["SHARE_HANDLERS"],
                        "SHARE_SHORTEN_URL_LOGIN" => $arParams["SHARE_SHORTEN_URL_LOGIN"],
                        "SHARE_SHORTEN_URL_KEY" => $arParams["SHARE_SHORTEN_URL_KEY"],
                        "ADD_ELEMENT_CHAIN" => $arParams["ADD_ELEMENT_CHAIN"],
                        'STRICT_SECTION_CHECK' => $arParams['STRICT_SECTION_CHECK'],
                    ],
                    $component
                );?>


        <section class="calculate-project">
            <div class="calculate-project__inner container">
                <div class="calculate-project__text">
                    <div class="calculate-project__text_top">
                        <h2 class="calculate-project__title">Рассчитать проект</h2>
                        <p class="calculate-project__descr">Оставьте заявку и получите консультацию и расчеты по вашему проекту</p>
                    </div>
                    <? $phoneMain = getMainPhoneFromIblock();
                    if ($phoneMain):?>
                    <div class="calculate-project__text_bottom">
                        <p class="calculate-project__alert">Или позвоните нам по телефону, мы всегда на связи!</p>
                        <a class="calculate-project__phone" href="tel:<?=$phoneMain;?>"><img src="<?=LAYOUT_DIR?>assets/img/icons/phone.svg" alt="phone"><?=$phoneMain;?></a>
                    </div>
                    <?endif;?>
                </div>
                <?$APPLICATION->IncludeComponent(
                    "bitrix:form.result.new",
                    "calculate-project-home",
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
<?
    $APPLICATION->IncludeComponent(
        "bitrix:news.detail",
        "news-detail-nav",
        [
            "DISPLAY_DATE" => $arParams["DISPLAY_DATE"],
            "DISPLAY_NAME" => $arParams["DISPLAY_NAME"],
            "DISPLAY_PICTURE" => $arParams["DISPLAY_PICTURE"],
            "DISPLAY_PREVIEW_TEXT" => $arParams["DISPLAY_PREVIEW_TEXT"],
            "IBLOCK_TYPE" => $arParams["IBLOCK_TYPE"],
            "IBLOCK_ID" => $arParams["IBLOCK_ID"],
            "FIELD_CODE" => $arParams["DETAIL_FIELD_CODE"],
            "PROPERTY_CODE" => $arParams["DETAIL_PROPERTY_CODE"],
            "DETAIL_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["detail"],
            "SECTION_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["section"],
            "META_KEYWORDS" => $arParams["META_KEYWORDS"],
            "META_DESCRIPTION" => $arParams["META_DESCRIPTION"],
            "BROWSER_TITLE" => $arParams["BROWSER_TITLE"],
            "SET_CANONICAL_URL" => $arParams["DETAIL_SET_CANONICAL_URL"],
            "SET_LAST_MODIFIED" => $arParams["SET_LAST_MODIFIED"],
            "SET_TITLE" => $arParams["SET_TITLE"],
            "MESSAGE_404" => $arParams["MESSAGE_404"],
            "SET_STATUS_404" => $arParams["SET_STATUS_404"],
            "SHOW_404" => $arParams["SHOW_404"],
            "FILE_404" => $arParams["FILE_404"],
            "INCLUDE_IBLOCK_INTO_CHAIN" => "N",
            "ADD_SECTIONS_CHAIN" =>  "N",
            "ACTIVE_DATE_FORMAT" => $arParams["DETAIL_ACTIVE_DATE_FORMAT"],
            "CACHE_TYPE" => $arParams["CACHE_TYPE"],
            "CACHE_TIME" => $arParams["CACHE_TIME"],
            "CACHE_GROUPS" => $arParams["CACHE_GROUPS"],
            "USE_PERMISSIONS" => $arParams["USE_PERMISSIONS"],
            "GROUP_PERMISSIONS" => $arParams["GROUP_PERMISSIONS"],
            "DISPLAY_TOP_PAGER" => $arParams["DETAIL_DISPLAY_TOP_PAGER"],
            "DISPLAY_BOTTOM_PAGER" => $arParams["DETAIL_DISPLAY_BOTTOM_PAGER"],
            "PAGER_TITLE" => $arParams["DETAIL_PAGER_TITLE"],
            "PAGER_SHOW_ALWAYS" => "N",
            "PAGER_TEMPLATE" => $arParams["DETAIL_PAGER_TEMPLATE"],
            "PAGER_SHOW_ALL" => $arParams["DETAIL_PAGER_SHOW_ALL"],
            "CHECK_DATES" => $arParams["CHECK_DATES"],
            "ELEMENT_ID" => $arResult["VARIABLES"]["ELEMENT_ID"],
            "ELEMENT_CODE" => $arResult["VARIABLES"]["ELEMENT_CODE"],
            "SECTION_ID" => $arResult["VARIABLES"]["SECTION_ID"],
            "SECTION_CODE" => $arResult["VARIABLES"]["SECTION_CODE"],
            "IBLOCK_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["news"],
            "USE_SHARE" => $arParams["USE_SHARE"],
            "SHARE_HIDE" => $arParams["SHARE_HIDE"],
            "SHARE_TEMPLATE" => $arParams["SHARE_TEMPLATE"],
            "SHARE_HANDLERS" => $arParams["SHARE_HANDLERS"],
            "SHARE_SHORTEN_URL_LOGIN" => $arParams["SHARE_SHORTEN_URL_LOGIN"],
            "SHARE_SHORTEN_URL_KEY" => $arParams["SHARE_SHORTEN_URL_KEY"],
            "ADD_ELEMENT_CHAIN" => "N",
            'STRICT_SECTION_CHECK' => $arParams['STRICT_SECTION_CHECK'],
        ],
        $component
    );?>
<?php
global $arrRelatedFilter;
$arrRelatedFilter = array(
    "!CODE" => $arResult["VARIABLES"]["ELEMENT_CODE"]
);


$APPLICATION->IncludeComponent(
    "bitrix:news.list",
    "projects-slider",
    array(
        "IBLOCK_ID" => $arParams["IBLOCK_ID"],
        "NEWS_COUNT" => "6",
        "SORT_BY1" => "ACTIVE_FROM",
        "SORT_ORDER1" => "DESC",
        "FILTER_NAME" => "arrRelatedFilter",
        "INCLUDE_IBLOCK_INTO_CHAIN" => "N",
        "ADD_SECTIONS_CHAIN" =>  "N",
    ),
    $component
);
?>	