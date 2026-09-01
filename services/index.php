<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
if (CModule::IncludeModule('iblock')) {
    $elementCode = $_REQUEST['ELEMENT_CODE'];
    $rsElement = CIBlockElement::GetList(
        array(),
        array(
            "CODE" => $elementCode,
            "IBLOCK_ID" => GetIBlockIDByCode("services"),
            "ACTIVE" => "Y"
        ),
        false,
        array("nTopCount" => 1),
        array("ID", "NAME", "CODE")
    );
    
    if ($arElement = $rsElement->GetNext()) {
        $currentElementName = $arElement["NAME"];
        
        $rsProp = CIBlockElement::GetProperty(
            GetIBlockIDByCode("services"),
            $arElement["ID"],
            array("sort" => "asc"),
            array("CODE" => "FAQ") 
        );
        
        $faqIds = array();
        while ($arProp = $rsProp->Fetch()) {
            if (!empty($arProp["VALUE"])) {
                $faqIds[] = $arProp["VALUE"];
            }
        }    
    }
}?>
        <section class="service-content image-text-block">
            <div class="service-content__inner  image-text-block__inner container">
                <div class="title-block">
                    <h1 class="title-block__title"><?=GetNameByCode($_REQUEST['ELEMENT_CODE'],GetIBlockIDByCode("services"));?></h1>
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
<?php
$APPLICATION->IncludeComponent(
    "bitrix:news.detail",
    "service",
    array(
        "IBLOCK_ID" => GetIBlockIDByCode("services"),
        "ELEMENT_CODE" => $_REQUEST['ELEMENT_CODE'] ?? '',
        "FIELD_CODE" => array("NAME", "DETAIL_TEXT", "DETAIL_PICTURE", "PREVIEW_TEXT", "PREVIEW_PICTURE"),
        "PROPERTY_CODE" => array(),
        "SET_TITLE" => "Y",
        "SET_STATUS_404" => "Y",
        "SHOW_404" => "Y",
        "INCLUDE_IBLOCK_INTO_CHAIN" => "N",
        "ADD_SECTIONS_CHAIN" => "N",
        "ADD_ELEMENT_CHAIN" => "Y",
        "CACHE_TYPE" => "A",
        "CACHE_TIME" => "36000000",
        "ACTIVE_DATE_FORMAT" => "d.m.Y",
    ),
    false
);
?>
<?if (GetPropByCode($_REQUEST['ELEMENT_CODE'],GetIBlockIDByCode("services")) == "Да"):?>
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
<?endif;?>
<?if ($faqIds):?>
    <?global $arFilterFaq;
    $arFilterFaq = array("ID" => $faqIds);?>
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
<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>