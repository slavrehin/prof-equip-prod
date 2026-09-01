<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Перечень брендов наших партнеров. Качественное оборудование для предприятий общественного питания и прачечных.");
$APPLICATION->SetPageProperty("title", "Бренды оборудования для продуктов питания, химчисток и прачечных");
$APPLICATION->SetTitle("Бренды");
?>
<?php
$APPLICATION->IncludeComponent(
	"custom:news.with.filter", 
	"brands", 
    [
        "IBLOCK_ID" => GetIBlockIDByCode('brands'),
        "SEF_MODE" => "Y",
        "SEF_FOLDER" => "/brends/",
        "SEF_URL_TEMPLATES" => [
            "news" => "",
            "detail" => "#ELEMENT_CODE#/",
            "detail_with_filter" => "#ELEMENT_CODE#/f/#SMART_FILTER_PATH#/",
        ],
        "SET_TITLE" => "Y",
        "SET_STATUS_404" => "Y",
        "SHOW_404" => "Y",
        "CACHE_TYPE" => "A",
        "CACHE_TIME" => 36000000,
    ],
    false
);
?>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>