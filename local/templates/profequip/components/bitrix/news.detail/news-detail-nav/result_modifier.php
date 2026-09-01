<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

// Получаем ID текущего элемента
$currentId = $arResult["ID"];

// Инициализируем массив навигации
$arResult["NAVIGATION"] = array(
    "PREV" => null,
    "NEXT" => null
);

// Параметры для поиска соседних элементов
$iblockId = $arResult["IBLOCK_ID"]; // 4
$sectionId = $arResult["IBLOCK_SECTION_ID"]; // может быть null
$currentDate = $arResult["ACTIVE_FROM"]; // дата для сортировки

// Получаем предыдущий элемент (более старый)
$arFilterPrev = array(
    "IBLOCK_ID" => $iblockId,
    "ACTIVE" => "Y",
    "ACTIVE_DATE" => "Y",
    "!ID" => $currentId
);

// Если дата есть, используем её для сортировки
if (!empty($currentDate)) {
    $arFilterPrev["<ACTIVE_FROM"] = $currentDate;
}

// Если есть раздел, ограничиваем им
if ($sectionId) {
    $arFilterPrev["SECTION_ID"] = $sectionId;
    $arFilterPrev["INCLUDE_SUBSECTIONS"] = "Y";
}

$rsPrev = CIBlockElement::GetList(
    array("ACTIVE_FROM" => "DESC", "SORT" => "DESC"), // Сначала более новые, но нам нужен самый близкий к текущему
    $arFilterPrev,
    false,
    array("nTopCount" => 1),
    array("ID", "NAME", "DETAIL_PAGE_URL", "ACTIVE_FROM", "SORT")
);

if ($prev = $rsPrev->GetNext()) {
    $arResult["NAVIGATION"]["PREV"] = $prev;
}

// Получаем следующий элемент (более новый)
$arFilterNext = array(
    "IBLOCK_ID" => $iblockId,
    "ACTIVE" => "Y",
    "ACTIVE_DATE" => "Y",
    "!ID" => $currentId
);

if (!empty($currentDate)) {
    $arFilterNext[">ACTIVE_FROM"] = $currentDate;
}

if ($sectionId) {
    $arFilterNext["SECTION_ID"] = $sectionId;
    $arFilterNext["INCLUDE_SUBSECTIONS"] = "Y";
}

$rsNext = CIBlockElement::GetList(
    array("ACTIVE_FROM" => "ASC", "SORT" => "ASC"), // Сначала более старые, но нам нужен самый близкий к текущему
    $arFilterNext,
    false,
    array("nTopCount" => 1),
    array("ID", "NAME", "DETAIL_PAGE_URL", "ACTIVE_FROM", "SORT")
);

if ($next = $rsNext->GetNext()) {
    $arResult["NAVIGATION"]["NEXT"] = $next;
}

// Если не нашли по дате, попробуем по ID (как запасной вариант)
if (empty($arResult["NAVIGATION"]["PREV"]) && empty($arResult["NAVIGATION"]["NEXT"])) {
    
    // Пробуем найти предыдущий по ID (меньше ID)
    $rsPrevById = CIBlockElement::GetList(
        array("ID" => "DESC"),
        array(
            "IBLOCK_ID" => $iblockId,
            "ACTIVE" => "Y",
            "<ID" => $currentId
        ),
        false,
        array("nTopCount" => 1),
        array("ID", "NAME", "DETAIL_PAGE_URL")
    );
    
    if ($prevById = $rsPrevById->GetNext()) {
        $arResult["NAVIGATION"]["PREV"] = $prevById;
    }
    
    // Пробуем найти следующий по ID (больше ID)
    $rsNextById = CIBlockElement::GetList(
        array("ID" => "ASC"),
        array(
            "IBLOCK_ID" => $iblockId,
            "ACTIVE" => "Y",
            ">ID" => $currentId
        ),
        false,
        array("nTopCount" => 1),
        array("ID", "NAME", "DETAIL_PAGE_URL")
    );
    
    if ($nextById = $rsNextById->GetNext()) {
        $arResult["NAVIGATION"]["NEXT"] = $nextById;
    }
}

// Добавим отладочную информацию (можно удалить после проверки)
$arResult["NAVIGATION_DEBUG"] = array(
    "current_id" => $currentId,
    "current_date" => $currentDate,
    "section_id" => $sectionId,
    "iblock_id" => $iblockId,
    "prev_found" => !empty($arResult["NAVIGATION"]["PREV"]),
    "next_found" => !empty($arResult["NAVIGATION"]["NEXT"])
);
?>