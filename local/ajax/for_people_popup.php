<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
?><?php
if (!empty($_GET["id"])) {
    $element_id = intval($_GET["id"]);
}
if (!empty($element_id)) {
    ?><?php
    $APPLICATION->IncludeComponent(
        "bitrix:news.detail",
        "for-people-modal",
        array(
            "DISPLAY_DATE" => "N",
            "DISPLAY_NAME" => "N",
            "DISPLAY_PICTURE" => "Y",
            "DISPLAY_PREVIEW_TEXT" => "N",
            "SHARE_SHORTEN_URL_LOGIN" => "",
            "SHARE_SHORTEN_URL_KEY" => "",
            "AJAX_MODE" => "N",
            "IBLOCK_ID" => "32",
            "ELEMENT_ID" => $element_id,
            "CHECK_DATES" => "Y",
            "FIELD_CODE" => array(
                0 => "DETAIL_PICTURE",
                1 => "DETAIL_TEXT",
                2 => "PREVIEW_PICTURE",
                3 => "",
            ),
            "PROPERTY_CODE" => array(
                0 => "VIDEO",
                1 => "GALLERY",
                2 => "",
            ),
            "IBLOCK_URL" => "",
            "DETAIL_URL" => "",
            "SET_TITLE" => "N",
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
        ),
        false
    ); ?><?php
}
?>