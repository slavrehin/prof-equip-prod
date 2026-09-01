<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$imageSrc = '';

$protocol = (CMain::IsHTTPS()) ? "https://" : "http://";
$domain = $protocol . $_SERVER['SERVER_NAME'];

// Получаем картинку элемента по ID
if (!empty($arResult["ID"])) {
    $arElement = CIBlockElement::GetList(
        array(),
        array("ID" => $arResult["ID"]),
        false,
        false,
        array("ID", "PREVIEW_PICTURE", "DETAIL_PICTURE")
    )->Fetch();
    
    if ($arElement) {
        // Сначала пробуем взять детальную картинку, если нет - превью
        $pictureId = $arElement["DETAIL_PICTURE"] ?: $arElement["PREVIEW_PICTURE"];
        
        if ($pictureId) {
            $arFile = CFile::GetFileArray($pictureId);
            if ($arFile) {
                $resizedImage = CFile::ResizeImageGet(
                    $arFile,
                    array('width' => 1200, 'height' => 630),
                    BX_RESIZE_IMAGE_EXACT,
                    true
                );
                $imageSrc = $resizedImage['src'];
            }
        }
    }
}

// Если картинки элемента нет, используем картинку раздела (как fallback)
if (empty($imageSrc) && !empty($arResult["SECTION"]["PICTURE"])) {
    $arFile = CFile::GetFileArray($arResult["SECTION"]["PICTURE"]);
    if ($arFile) {
        $resizedImage = CFile::ResizeImageGet(
            $arFile,
            array('width' => 1200, 'height' => 630),
            BX_RESIZE_IMAGE_EXACT,
            true
        );
        $imageSrc = $resizedImage['src'];
    }
}

$imageUrl = $imageSrc ? $domain . $imageSrc : '';

$APPLICATION->AddHeadString('<meta property="og:image" content="' . $imageUrl . '" />');
