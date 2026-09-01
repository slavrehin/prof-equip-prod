<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

/**
 * @global CMain $APPLICATION
 * @param array $arParams
 * @param array $arResult
 * @param CBitrixComponent $this
 */

if(!CModule::IncludeModule("iblock"))
{
    return;
}

$arResult = array();

// ===== 1. НОРМАЛИЗУЕМ ВХОДНЫЕ ПАРАМЕТРЫ =====
if (!is_array($arParams["FILTER"])) {
    $arParams["FILTER"] = array();
}

if (!isset($arParams["IBLOCK_ID"]) || empty($arParams["IBLOCK_ID"])) {
    $this->IncludeComponentTemplate();
    return;
}

// ===== 2. ФОРМИРУЕМ КЛЮЧ КЭША =====
$cacheKeyData = array(
    'iblock_id' => $arParams["IBLOCK_ID"],
    'filter' => $arParams["FILTER"],
    'select' => isset($arParams["SELECT"]) ? $arParams["SELECT"] : array(),
);

$cacheID = "SEO_PARAMS_" . md5(serialize($cacheKeyData));

if (empty($cacheID)) {
    $cacheID = "SEO_PARAMS_DEFAULT_" . $arParams["IBLOCK_ID"];
}

$cacheTime = 3600;
$cachePath = "/seo_params/";

$obCache = new CPHPCache();

if($obCache->InitCache($cacheTime, $cacheID, $cachePath))
{
    $vars = $obCache->GetVars();
    $arResult = isset($vars['RESULT']) ? $vars['RESULT'] : array();
}
elseif($obCache->StartDataCache($cacheTime, $cacheID, $cachePath))
{
    $arFilter = array(
        "IBLOCK_ID" => $arParams["IBLOCK_ID"], 
        "ACTIVE" => "Y"  // <-- Ищем ТОЛЬКО активные элементы
    );
    
    $arSelect = array(
        "ID", 
        "IBLOCK_ID", 
        "NAME", 
        "PREVIEW_PICTURE",
        "PREVIEW_TEXT",
        "DETAIL_TEXT", 
        "PROPERTY_*"
    );
    
    if(isset($arParams["SELECT"]) && is_array($arParams["SELECT"]) && !empty($arParams["SELECT"])) {
        $arSelect = array_merge($arSelect, $arParams["SELECT"]);
    }
    
    if(isset($arParams["FILTER"]) && is_array($arParams["FILTER"]) && !empty($arParams["FILTER"])) {
        foreach($arParams['FILTER'] as $key => $val) {
            $arFilter[$key] = $val;
        }
    }
    
    $res = CIBlockElement::GetList(array('SORT' => 'ASC'), $arFilter, false, false, $arSelect);
    
    $hasResult = false;
    if($ob = $res->GetNextElement()) {
        $arResult = $ob->GetFields();
        $arResult["PROPS"] = $ob->GetProperties();
        $hasResult = true;
    } else {
        $arResult = array();
    }
    
    // ===== ВАЖНО: кэшируем ТОЛЬКО если есть результат! =====
    if ($hasResult) {
        $obCache->EndDataCache(array('RESULT' => $arResult));
    } else {
        // Не кэшируем пустой результат - отменяем запись кэша
        $obCache->AbortDataCache();
    }
}

// ===== 4. ПЕРЕДАЕМ РЕЗУЛЬТАТ =====
$this->arResult = $arResult;

// ===== 5. УСТАНАВЛИВАЕМ SEO =====
if(!empty($arResult) && isset($arResult["PROPS"]) && is_array($arResult["PROPS"])) {
    
    $SEO_H1          = trim($arResult["PROPS"]['H1']['VALUE'] ?? '');
    $SEO_TITLE       = trim($arResult["PROPS"]['TITLE']['VALUE'] ?? '');
    $SEO_KEYWORDS    = trim($arResult["PROPS"]['KEYWORDS']['VALUE'] ?? '');
    $SEO_DESCRIPTION = trim($arResult["PROPS"]['DESCRIPTION']['VALUE'] ?? '');
    
    if(!empty($SEO_H1)) {
        $APPLICATION->SetTitle($SEO_H1);
    }
    
    if(!empty($SEO_TITLE)) {
        $APPLICATION->SetPageProperty("title", $SEO_TITLE);
    }
    
    if(!empty($SEO_KEYWORDS)) {
        $APPLICATION->SetPageProperty("keywords", $SEO_KEYWORDS);
    }
    
    if(!empty($SEO_DESCRIPTION)) {
        $APPLICATION->SetPageProperty("description", $SEO_DESCRIPTION);
    }
}

$this->IncludeComponentTemplate();