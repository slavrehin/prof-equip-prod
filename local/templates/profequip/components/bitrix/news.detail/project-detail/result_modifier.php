<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

// ===== 1. ПОЛУЧЕНИЕ ДАННЫХ КАТАЛОГА =====
if (!empty($arResult['ID']) && !empty($arResult['PROPERTIES'])) {

    $catalog1Products = [];
    $titleCatalog1 = 'Наш ассортимент';
    
    if (!empty($arResult['PROPERTIES']['CATALOG_PRODUCT_1']['VALUE'])) {
        $productIds = $arResult['PROPERTIES']['CATALOG_PRODUCT_1']['VALUE'];
        
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
            
            $catalog1Products[$item['ID']] = [
                'ID' => $item['ID'],
                'NAME' => $item['NAME'],
                'URL' => $item['DETAIL_PAGE_URL'],
                'PICTURE_SRC' => $item['PICTURE'] ? $item['PICTURE']['src'] : '',
                'PICTURE_ALT' => $item['NAME'],
            ];
        }
        
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
    
    // ===== 2. ПОЛУЧЕНИЕ БРЕНДОВ (инфоблок 14) =====
    $brandsItems = [];
    $brandsTitle = 'Бренды, задействованные в проекте:';
    
    if (!empty($arResult['PROPERTIES']['BRANDS']['VALUE'])) {
        $brandIds = $arResult['PROPERTIES']['BRANDS']['VALUE'];
        
        if (!is_array($brandIds)) {
            $brandIds = [$brandIds];
        }
        
        // Получаем элементы брендов со всеми свойствами
        $res = CIBlockElement::GetList(
            ['SORT' => 'ASC'],
            [
                'IBLOCK_ID' => 14,
                'ID' => $brandIds,
                'ACTIVE' => 'Y'
            ],
            false,
            false,
            [
                'ID',
                'NAME',
                'DETAIL_PAGE_URL',  // Добавляем URL для ссылки
                'PROPERTY_LOGO',
            ]
        );
        
        while ($brand = $res->GetNext()) {
            $logoData = [
                'SRC' => '',
                'IS_SVG' => false,
                'FILE_NAME' => '',
            ];
            
            // Получаем файл из свойства LOGO
            if (!empty($brand['PROPERTY_LOGO_VALUE'])) {
                $arFile = CFile::GetFileArray($brand['PROPERTY_LOGO_VALUE']);
                if ($arFile) {
                    $logoData['SRC'] = $arFile['SRC'];
                    $logoData['FILE_NAME'] = $arFile['FILE_NAME'];
                    
                    // Проверяем расширение файла
                    $ext = strtolower(pathinfo($arFile['FILE_NAME'], PATHINFO_EXTENSION));
                    $logoData['IS_SVG'] = ($ext === 'svg');
                }
            }
            
            $brandsItems[$brand['ID']] = [
                'ID' => $brand['ID'],
                'NAME' => $brand['NAME'],
                'URL' => $brand['DETAIL_PAGE_URL'], // Ссылка на страницу бренда
                'LOGO' => $logoData,
                'ALT' => $brand['NAME'],
            ];
        }
        
        if (!empty($arResult['PROPERTIES']['TITLE_BRANDS']['VALUE'])) {
            $brandsTitle = $arResult['PROPERTIES']['TITLE_BRANDS']['VALUE'];
        }
    }
    
    $arResult['BRANDS'] = [
        'TITLE' => $brandsTitle,
        'ITEMS' => $brandsItems,
    ];
}