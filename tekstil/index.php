<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
// Title/description — вкладка «SEO» элемента направления «Текстиль»; H1 — заголовок первого слайда.
// Содержимое блоков: Контент -> «Направления» -> «Текстиль (направление)» -> вкладка «Лендинг»
// (см. local/components/custom/direction.landing).
$APPLICATION->SetTitle("Текстиль");
$APPLICATION->IncludeComponent(
    "custom:direction.landing",
    "",
    [
        "DIRECTION_CODE" => "textile",
        "DIRECTION_IBLOCK_CODE" => "textile",
        "CACHE_TIME" => 3600,
    ],
    false
);
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
