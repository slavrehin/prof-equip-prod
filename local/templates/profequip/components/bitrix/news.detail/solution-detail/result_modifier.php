<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

// ===== 1. ПОЛУЧЕНИЕ ДАННЫХ КАТАЛОГА =====
if (!empty($arResult['ID']) && !empty($arResult['PROPERTIES'])) {

    $catalog1Products = [];
    $titleCatalog1 = 'Наш ассортимент'; // Заголовок по умолчанию
    
    if (!empty($arResult['PROPERTIES']['CATALOG_PRODUCT_1']['VALUE'])) {
        $productIds = $arResult['PROPERTIES']['CATALOG_PRODUCT_1']['VALUE'];
        
        // Прямой getlist без функции
        $res = CIBlockElement::GetList(
            ['SORT' => 'ASC'],
            [
                'IBLOCK_ID' => 11,
                'ID' => $productIds,
                'ACTIVE' => 'Y'
            ],
            false,
            false,
            [
                'ID',
                'NAME',
                'DETAIL_PAGE_URL',
                'PREVIEW_PICTURE',
                'DETAIL_PICTURE',
            ]
        );
        
        while ($item = $res->GetNext()) {
            // Получаем картинку
            $pictureId = $item['DETAIL_PICTURE'] ?: $item['PREVIEW_PICTURE'];
            $item['PICTURE'] = null;
            
            if ($pictureId) {
                $item['PICTURE'] = CFile::ResizeImageGet(
                    $pictureId,
                    ['width' => 300, 'height' => 300],
                    BX_RESIZE_IMAGE_PROPORTIONAL,
                    true
                );
            }
            
            $catalog1Products[$item['ID']] = [
                'ID' => $item['ID'],
                'NAME' => $item['NAME'],
                'URL' => $item['DETAIL_PAGE_URL'],
                'PICTURE_SRC' => $item['PICTURE'] ? $item['PICTURE']['src'] : '',
                'PICTURE_ALT' => $item['NAME'],
            ];
        }
        
        // Получаем заголовок
        if (!empty($arResult['PROPERTIES']['TITLE_CATALOG_1']['VALUE'])) {
            $titleCatalog1 = $arResult['PROPERTIES']['TITLE_CATALOG_1']['VALUE'];
        }
    }
    
    // --- Получаем данные для второго каталога ---
    $catalog2Products = [];
    $titleCatalog2 = 'Наш ассортимент';
    
    if (!empty($arResult['PROPERTIES']['CATALOG_PRODUCT_2']['VALUE'])) {
        $productIds = $arResult['PROPERTIES']['CATALOG_PRODUCT_2']['VALUE'];
        
        $res = CIBlockElement::GetList(
            ['SORT' => 'ASC'],
            [
                'IBLOCK_ID' => 11,
                'ID' => $productIds,
                'ACTIVE' => 'Y'
            ],
            false,
            false,
            [
                'ID',
                'NAME',
                'DETAIL_PAGE_URL',
                'PREVIEW_PICTURE',
                'DETAIL_PICTURE',
            ]
        );
        
        while ($item = $res->GetNext()) {
            $pictureId = $item['DETAIL_PICTURE'] ?: $item['PREVIEW_PICTURE'];
            $item['PICTURE'] = null;
            
            if ($pictureId) {
                $item['PICTURE'] = CFile::ResizeImageGet(
                    $pictureId,
                    ['width' => 300, 'height' => 300],
                    BX_RESIZE_IMAGE_PROPORTIONAL,
                    true
                );
            }
            
            $catalog2Products[$item['ID']] = [
                'ID' => $item['ID'],
                'NAME' => $item['NAME'],
                'URL' => $item['DETAIL_PAGE_URL'],
                'PICTURE_SRC' => $item['PICTURE'] ? $item['PICTURE']['src'] : '',
                'PICTURE_ALT' => $item['NAME'],
            ];
        }
        
        if (!empty($arResult['PROPERTIES']['TITLE_CATALOG_2']['VALUE'])) {
            $titleCatalog2 = $arResult['PROPERTIES']['TITLE_CATALOG_2']['VALUE'];
        }
    }
    
    $arResult['CATALOG_1'] = [
        'TITLE' => $titleCatalog1,
        'ITEMS' => $catalog1Products,
    ];
    
    $arResult['CATALOG_2'] = [
        'TITLE' => $titleCatalog2,
        'ITEMS' => $catalog2Products,
    ];
}

if (!empty($arResult['~DETAIL_TEXT'])) {
    $arResult['~DETAIL_TEXT'] = processImages($arResult['~DETAIL_TEXT']);
}

// Если есть preview text - тоже обрабатываем
if (!empty($arResult['~PREVIEW_TEXT'])) {
    $arResult['~PREVIEW_TEXT'] = processImages($arResult['~PREVIEW_TEXT']);
}
