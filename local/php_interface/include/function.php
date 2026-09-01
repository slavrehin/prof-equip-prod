<?php

function pr($arr)
{
    echo "<pre>";
    print_r($arr);
    echo "</pre>";
}

function phoneNormalize(string $phone): string
{
    // Убираем всё, кроме цифр и плюса
    $normalized = preg_replace('/[^+\d]/', '', $phone);

    // Если номер начинается с 8 и состоит из 11 цифр (типичный российский формат), заменяем 8 на +7
    if (preg_match('/^8\d{10}$/', $normalized)) {
        $normalized = '+7' . substr($normalized, 1);
    } // Если номер начинается без плюса и не содержит кода страны — добавляем +7 по умолчанию
    elseif (preg_match('/^\d{10}$/', $normalized)) {
        $normalized = '+7' . $normalized;
    } // Если номер не начинается с плюса, добавляем его
    elseif ($normalized && $normalized[0] !== '+') {
        $normalized = '+' . $normalized;
    }

    return $normalized;
}

function splitArrayInHalf(array $array): array
{
    $half = ceil(count($array) / 2); // округляем вверх, если элементов нечётное количество
    return [
        array_slice($array, 0, $half),
        array_slice($array, $half)
    ];
}

function GetIBlockIDByCode($code, $type = '')
{
    CModule::IncludeModule("iblock");

    $arrFilter = array(
        'ACTIVE' => 'Y',
        'CODE' => $code,
        'SITE_ID' => "s1",
    );

    if ($type) {
        $arrFilter['TYPE'] = $type;
    }

    $res = CIBlock::GetList(array("SORT" => "ASC"), $arrFilter, false);
    $arIBlockId = "";

    if ($ar_res = $res->Fetch()) {
        $arIBlockId = $ar_res["ID"];
    }

    return $arIBlockId;
}

function GetNameByCode($code, $iblockid = '')
{
if (CModule::IncludeModule('iblock')) {
    $elementCode = $code;
    $rsElement = CIBlockElement::GetList(
        array(),
        array(
            "CODE" => $elementCode,
            "IBLOCK_ID" => $iblockid,
            "ACTIVE" => "Y"
        ),
        false,
        array("nTopCount" => 1),
        array("ID", "NAME", "CODE")
    );
    
    if ($arElement = $rsElement->GetNext()) {
        $currentElementName = $arElement["NAME"];
    }
}

    return $currentElementName;
}

function GetIDByCode($code, $iblockid = '')
{
if (CModule::IncludeModule('iblock')) {
    $elementCode = $code;
    $rsElement = CIBlockElement::GetList(
        array(),
        array(
            "CODE" => $elementCode,
            "IBLOCK_ID" => $iblockid,
            "ACTIVE" => "Y"
        ),
        false,
        array("nTopCount" => 1),
        array("ID", "NAME", "CODE")
    );
    
    if ($arElement = $rsElement->GetNext()) {
        $currentElementName = $arElement["ID"];
    }
}

    return $currentElementName;
}

function GetPropByCode($code, $iblockid = '')
{
if (CModule::IncludeModule('iblock')) {
    $elementCode = $code;
    $rsElement = CIBlockElement::GetList(
        array(),
        array(
            "CODE" => $elementCode,
            "IBLOCK_ID" => $iblockid,
            "ACTIVE" => "Y"
        ),
        false,
        array("nTopCount" => 1),
        array("ID", "NAME", "CODE","PROPERTY_SHOW_FORM")
    );
    
    if ($arElement = $rsElement->GetNext()) {
        $currentElementProp = $arElement["PROPERTY_SHOW_FORM_VALUE"];
    }
}

    return $currentElementProp;
}

function GetPropDirectionByCode($code, $iblockid = '')
{
    $currentElementProp = array();
    
    if (CModule::IncludeModule('iblock')) {
        $elementCode = $code;
        $rsElement = CIBlockElement::GetList(
            array(),
            array(
                "CODE" => $elementCode,
                "IBLOCK_ID" => $iblockid,
                "ACTIVE" => "Y"
            ),
            false,
            array("nTopCount" => 1),
            array("ID", "NAME", "CODE")
        );
        
        if ($obElement = $rsElement->GetNextElement()) {
            $arElement = $obElement->GetFields();
            
            $currentElementProp['ID'] = $arElement['ID'];
            $currentElementProp['NAME'] = $arElement['NAME'];
            $currentElementProp['CODE'] = $arElement['CODE'];

            $currentElementProp['PROJECTS'] = array();
            $currentElementProp['BANNERS'] = array();
            $currentElementProp['ARTICLES'] = array();
            $currentElementProp['BRANDS'] = array();
            $currentElementProp['CATEGORIES_CATALOG'] = array();
            $currentElementProp['CATALOG_PRODUCTS'] = array();
            $currentElementProp['STEPS'] = array();
            $currentElementProp['SOLUTION'] = array();
            $currentElementProp['REVIEWS'] = array();
            $currentElementProp['FAQ'] = array();

            $dbProperties = CIBlockElement::GetProperty(
                $iblockid,
                $arElement['ID'],
                array("sort" => "asc"),
                array()
            );
            
            while ($prop = $dbProperties->Fetch()) {
                $propCode = $prop['CODE'];
                
                if (in_array($propCode, array('PROJECTS', 'BRANDS', 'BANNERS', 'ARTICLES', 'CATEGORIES_CATALOG', 'CATALOG_PRODUCTS', 'STEPS', 'SOLUTION', 'REVIEWS', 'FAQ'))) {
                    if (!empty($prop['VALUE'])) {
                        $currentElementProp[$propCode][] = $prop['VALUE'];
                    }
                }
            }

        }
    }

    return $currentElementProp;
}

function getChildrenMenu($input, &$start = 0, $level = 0)
{
    $children = array();

    if (!$level) {
        $lastDepthLevel = 1;
        if (is_array($input)) {
            foreach ($input as $i => $arItem) {
                if ($arItem["DEPTH_LEVEL"] > $lastDepthLevel) {
                    if ($i > 0) {
                        $input[$i - 1]["IS_PARENT"] = 1;
                    }
                }
                $lastDepthLevel = $arItem["DEPTH_LEVEL"];
            }
        }
    }
    for ($i = $start, $count = count($input); $i < $count; ++$i) {
        $item = $input[$i];
        if ($level > $item['DEPTH_LEVEL'] - 1) {
            break;
        } elseif (!empty($item['IS_PARENT'])) {
            ++$i;
            $item['CHILDREN'] = getChildrenMenu($input, $i, $level + 1);
            --$i;
        }
        $children[] = $item;
    }

    $start = $i;
    return $children;
}

function sortArray($arSource, $arOrder)
{
    $arFirst = [];

    foreach ($arOrder as $sField) {
        if (in_array($sField, $arSource)) {
            $arFirst[] = $sField;

            foreach ($arSource as $keySource => $sSource) {
                if ($sSource == $sField) {
                    unset($arSource[$keySource]);
                }
            }
        }
    }

    return $arFirst;
}

function galleryGridParts($text, $arProperties) {
    if (empty($text)) return $text;
    
    return preg_replace_callback('/#GRID_([1-5])#/', function($matches) use ($arProperties) {
        $galleryNumber = $matches[1];
        $propertyCode = 'GRID_' . $galleryNumber;
        
        // Проверяем, есть ли такое свойство и не пустое ли оно
        if (empty($arProperties[$propertyCode]['VALUE'])) {
            return '';
        }
        
        $galleryValue = $arProperties[$propertyCode]['VALUE'];

        $html = '';
        if (is_array($galleryValue)) {
            if (count($galleryValue) > 1) {
                $html .= '<div class="images">';
                foreach ($galleryValue as $fileId) {
                    $fileData = CFile::GetFileArray($fileId);
                    if ($fileData) {
                        $html .= getGalleryItemHtml($fileData);
                    }
                }
                $html .= '</div>';
            } 
            // Если одно изображение
            else {
                $fileData = CFile::GetFileArray(reset($galleryValue));
                if ($fileData) {
                    $html .= getGalleryItemHtml($fileData);
                }
            }
        } 
        else {
            $fileData = CFile::GetFileArray($galleryValue);
            if ($fileData) {
                $html .= getGalleryItemHtml($fileData);
            }
        }
        
        return $html;
    }, $text);
}

function galleryParts($text, $arProperties) {
    if (empty($text)) return $text;
    
    return preg_replace_callback('/#GALLERY_([1-5])#/', function($matches) use ($arProperties) {
        $galleryNumber = $matches[1];
        $propertyCode = 'GALLERY_' . $galleryNumber;
        
        // Проверяем, есть ли такое свойство и не пустое ли оно
        if (empty($arProperties[$propertyCode]['VALUE'])) {
            return '';
        }
        
        $galleryValue = $arProperties[$propertyCode]['VALUE'];

        $html = '';
        if (is_array($galleryValue)) {
            if (count($galleryValue) > 1) {
                $html .= '<div class="images-col">';
                foreach ($galleryValue as $fileId) {
                    $fileData = CFile::GetFileArray($fileId);
                    if ($fileData) {
                        $html .= getGalleryItemHtml($fileData);
                    }
                }
                $html .= '</div>';
            } 
            // Если одно изображение
            else {
                $fileData = CFile::GetFileArray(reset($galleryValue));
                if ($fileData) {
                    $html .= getGalleryItemHtml($fileData);
                }
            }
        } 
        else {
            $fileData = CFile::GetFileArray($galleryValue);
            if ($fileData) {
                $html .= getGalleryItemHtml($fileData);
            }
        }
        
        return $html;
    }, $text);
}

function getGalleryItemHtml($fileData) {
    if (!$fileData) return '';
    
    $src = $fileData['SRC'];
    $src2x = str_replace('.', '@2x.', $src); // Предполагаем, что @2x версия лежит рядом
    
    // Проверяем существование @2x версии (опционально)
    $src2xPath = $_SERVER['DOCUMENT_ROOT'] . $src2x;
    if (!file_exists($src2xPath)) {
        $src2x = $src; // Если нет @2x, используем ту же картинку
    }

    
    return '
<div class="image-wrapper">
    <picture>
        <source srcset="' . $src2x . ', ' . $src2x . ' 2x" type="image/webp">
        <img src="' . $src . '" srcset="' . $src . ', ' . $src2x . ' 2x" alt="gallery image">
    </picture>
</div>';
}



function wrapImagesInTemplate($text) {
    // Регулярка для поиска всех img тегов
    $pattern = '/<img[^>]+src="([^"]+)"[^>]*>/i';
    
    // Заменяем каждый найденный img на обертку
    $text = preg_replace_callback($pattern, function($matches) {
        $imgSrc = $matches[1]; // Получаем src картинки
        
        // Формируем HTML обертки
        $wrappedHtml = '<div class="image-wrapper">';
        $wrappedHtml .= '<picture>';
        $wrappedHtml .= '<source srcset="' . $imgSrc . '" type="image/webp">';
        $wrappedHtml .= '<img src="' . $imgSrc . '" srcset="' . $imgSrc . ', ' . $imgSrc . ' 2x" alt="blog img">';
        $wrappedHtml .= '</picture>';
        $wrappedHtml .= '</div>';
        
        return $wrappedHtml;
    }, $text);
    
    return $text;
}


/**
 * Получение номера телефона из инфоблока (ID 1) у элемента с отмеченным чекбоксом MAIN
 * 
 * @return string|false Номер телефона или false, если не найден
 */
function getMainPhoneFromIblock() {
    // Подключаем модуль инфоблоков
    if (!\Bitrix\Main\Loader::includeModule('iblock')) {
        return false;
    }
    
    $iblockId = 1;

    $arFilter = [
        'IBLOCK_ID' => $iblockId,
        'ACTIVE' => 'Y',
        'PROPERTY_MAIN_VALUE' => 'Да'
    ];

    $arSelect = [
        'ID',
        'NAME',
        'PROPERTY_PHONE'
    ];
    
    $res = \CIBlockElement::GetList(
        ['SORT' => 'ASC'],
        $arFilter,
        false,
        ['nTopCount' => 1],
        $arSelect
    );
    
    if ($arElement = $res->GetNext()) {
        return $arElement['PROPERTY_PHONE_VALUE'];
    }
    
    return false;
}

/**
 * Получение номера телефона из инфоблока (ID 1) у элемента с отмеченным чекбоксом MAIN
 * 
 */
function getMainIdFromContactIblock() {
    // Подключаем модуль инфоблоков
    if (!\Bitrix\Main\Loader::includeModule('iblock')) {
        return false;
    }
    
    $iblockId = 1;

    $arFilter = [
        'IBLOCK_ID' => $iblockId,
        'ACTIVE' => 'Y',
        'PROPERTY_MAIN_VALUE' => 'Да'
    ];

    $arSelect = [
        'ID',
        'NAME'
    ];
    
    $res = \CIBlockElement::GetList(
        ['SORT' => 'ASC'],
        $arFilter,
        false,
        ['nTopCount' => 1],
        $arSelect
    );
    
    if ($arElement = $res->GetNext()) {
        return intval($arElement['ID']);
    }
    
    return false;
}


function findElementByCode($code) {
    if (empty($code)) return null;
    
    CModule::IncludeModule('iblock');
    
    // Получаем список всех инфоблоков (можно ограничить по типам)
    $iblockIds = array(4, 5, 6, 8, 9, 15, 16, 17, 18, 23, 24); // Если нужно получить все инфоблоки
    
    $dbIblocks = CIBlock::GetList(array(), array(
        'ACTIVE' => 'Y',
        // 'TYPE' => array('catalog', 'products') // Можно ограничить по типам
    ));
    
    while ($arIblock = $dbIblocks->Fetch()) {
        $iblockIds[] = $arIblock['ID'];
    }
    
    if (empty($iblockIds)) return null;
    
    // Ищем элемент по символьному коду во всех инфоблоках
    $arFilter = array(
        'IBLOCK_ID' => $iblockIds,
        'CODE' => $code,
        'ACTIVE' => 'Y'
    );
    
    $dbElements = CIBlockElement::GetList(
        array(),
        $arFilter,
        false,
        array('nTopCount' => 1),
        array('ID', 'IBLOCK_ID', 'NAME', 'DETAIL_PICTURE', 'PREVIEW_PICTURE')
    );
    
    if ($arElement = $dbElements->Fetch()) {
        return $arElement;
    }
    
    return null;
}

function processImages($content) {
    if (empty($content)) return $content;
    
    // Разбиваем контент на части по тегам, но сохраняем структуру
    $parts = preg_split('/(<[^>]+>)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    
    $result = '';
    $imageGroup = [];
    $textBuffer = '';
    $lastWasText = false;
    
    foreach ($parts as $part) {
        // Проверяем тип части
        if (preg_match('/^<img[^>]+>$/s', $part)) {
            // Это изображение - добавляем в группу
            if (!empty($textBuffer)) {
                $result .= $textBuffer;
                $textBuffer = '';
            }
            $imageGroup[] = $part;
            $lastWasText = false;
        } 
        elseif (preg_match('/^<br\s*\/?>$/s', $part)) {
            // Это br - игнорируем между изображениями, иначе сохраняем
            if (empty($imageGroup)) {
                $textBuffer .= $part;
            }
            $lastWasText = false;
        }
        elseif (preg_match('/^<[^>]+>$/s', $part)) {
            // Другой HTML тег
            if (!empty($imageGroup)) {
                // Завершаем группу изображений перед тегом
                $result .= processImageGroup($imageGroup);
                $imageGroup = [];
            }
            $textBuffer .= $part;
            $lastWasText = false;
        }
        else {
            // Это текст
            if (!empty($imageGroup)) {
                // Если после изображений идет текст - завершаем группу
                if (trim($part) !== '') {
                    $result .= processImageGroup($imageGroup);
                    $imageGroup = [];
                }
            }
            $textBuffer .= $part;
            $lastWasText = true;
        }
    }
    
    // Обрабатываем оставшуюся группу изображений
    if (!empty($imageGroup)) {
        $result .= processImageGroup($imageGroup);
    }
    
    // Добавляем оставшийся текст
    $result .= $textBuffer;
    
    // Очищаем от лишних пробелов и переносов
    $result = preg_replace('/\s+/', ' ', $result);
    $result = preg_replace('/>\s+</', '><', $result);
    
    return $result;
}

function processImageGroup($images) {
    $count = count($images);
    
    // Оборачиваем каждое изображение
    $wrapped = array_map(function($img) {
        // Убираем возможные пробелы и переносы внутри тега img
        $cleanImg = preg_replace('/\s+/', ' ', $img);
        return '<div class="image-wrapper"><picture>' . $cleanImg . '</picture></div>';
    }, $images);
    
    // Несколько изображений - в общий контейнер
    if ($count > 1) {
        return '<div class="images">' . implode('', $wrapped) . '</div>';
    }
    
    // Одно изображение
    return $wrapped[0];
}