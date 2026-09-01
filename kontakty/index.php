<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Главный офис ПРОФЭКВИП 127106, Россия, г. Москва, ул. Гостиничная 3. Телефон +7 (495) 477-57-13. Оборудование для ресторанов, прачечных, химчисток.");
$APPLICATION->SetPageProperty("title", "Контакты, главный офис, обратная связь ПРОФЭКВИП");
$APPLICATION->SetTitle("Контакты");
?>

        <div class="contacts-content">
            <div class="contacts-content__inner container">
                <div class="title-block">
                    <h1 class="title-block__title"><?=$APPLICATION->GetTitle(false);?></h1>
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
                <div class="contacts-content__info">
                    <?$APPLICATION->IncludeComponent("bitrix:news.list", "contacts", array(
                        "IBLOCK_TYPE" => "content",
                        "IBLOCK_ID" => GetIBlockIDByCode("contacts"),
                        "NEWS_COUNT" => "20",
                        "SORT_BY1" => "ACTIVE_FROM",
                        "SORT_ORDER1" => "DESC",
                        "SORT_BY2" => "SORT",
                        "SORT_ORDER2" => "ASC",
                        "FILTER_NAME" => "",
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
                <?$APPLICATION->IncludeComponent(
                    "bitrix:form.result.new",
                    "contact_form",
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
                <div id="map" data-center="55.843525,37.580948" data-zoom="14"></div>
            </div>
        </div>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>