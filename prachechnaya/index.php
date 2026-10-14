<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
// Title/description — вкладка «SEO» элемента направления «Прачечная»; H1 — заголовок первого слайда.
// Содержимое блоков: Контент -> «Лендинги направлений» (см. local/components/custom/direction.landing).
$APPLICATION->SetTitle("Прачечная");
$APPLICATION->IncludeComponent(
    "custom:direction.landing",
    "",
    [
        "DIRECTION_CODE" => "prachechnaya",
        "DIRECTION_IBLOCK_CODE" => "prachechnaya",
        "CACHE_TIME" => 3600,
    ],
    false
);
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
