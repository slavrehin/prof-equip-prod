<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
// Лендинг направления «Кухня» (бриф «Брифы направления/бриф Кухня.docx», ноябрь 2026).
// Title/description/H1 — вкладка «SEO» элемента направления; содержимое блоков:
// Контент -> «Направления: Кухня» -> «Оборудование профессиональной кухни» -> вкладка «Лендинг»
// (см. local/components/custom/direction.landing/README.md). Прежняя страница на news.detail/kuhnya_page — в истории Git.
$APPLICATION->SetTitle("Кухня");
$APPLICATION->IncludeComponent(
    "custom:direction.landing",
    "",
    [
        "DIRECTION_CODE" => "kuhnya",
        "DIRECTION_IBLOCK_CODE" => "kuhnya",
        "CACHE_TIME" => 3600,
    ],
    false
);
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
