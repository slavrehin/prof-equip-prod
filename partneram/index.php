<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Компания ПРОФЭКВИП – один из лидеров России и СНГ в области комплексного оснащения HORECA. Наша деятельность охватывает несколько направлений: оснащение");
$APPLICATION->SetPageProperty("title", "Партнерам");
$APPLICATION->SetTitle("Партнерам");
?>
<section class="text-block">
    <div class="text-block__inner container">
        <h1 class="text-block__title"><?=$APPLICATION->GetTitle(false);?></h1>
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
            "partneram", 
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
            "IBLOCK_ID" => GetIBlockIDByCode("partneram"),
            "ELEMENT_CODE" => "partneram",
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
    </div>
</section>


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
<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>