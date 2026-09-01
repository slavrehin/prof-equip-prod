<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Iblock\SectionTable;

// Проверяем, что указан инфоблок
if (empty($arParams['IBLOCK_ID'])) {
    return;
}

$arResult['SECTIONS'] = [];

if (!empty($arResult['ITEMS'])) {
    // Собираем ID разделов из элементов
    $sectionIds = [];
    foreach ($arResult['ITEMS'] as $item) {
        if (!empty($item['IBLOCK_SECTION_ID'])) {
            $sectionIds[] = (int)$item['IBLOCK_SECTION_ID'];
        }
    }
    
    if (!empty($sectionIds)) {
        $sectionIds = array_unique($sectionIds);
        
        // Получаем названия разделов
        $sections = [];
        $rsSections = SectionTable::getList([
            'filter' => ['ID' => $sectionIds],
            'select' => ['ID', 'NAME', 'CODE', 'SORT']
        ]);
        
        while ($section = $rsSections->fetch()) {
            $sections[$section['ID']] = [
                'ID' => $section['ID'],
                'NAME' => $section['NAME'],
                'CODE' => $section['CODE'],
                'SORT' => $section['SORT'],
                'ITEMS' => []
            ];
        }
        
        // Группируем элементы по разделам
        foreach ($arResult['ITEMS'] as $item) {
            $sectionId = (int)$item['IBLOCK_SECTION_ID'];
            if (isset($sections[$sectionId])) {
                // Подготавливаем данные для элемента
                $sections[$sectionId]['ITEMS'][] = [
                    'ID' => $item['ID'],
                    'NAME' => $item['NAME'],
                    'CODE' => $item['CODE'],
                    'PREVIEW_PICTURE' => $item['PREVIEW_PICTURE'],
                    'DETAIL_PICTURE' => $item['DETAIL_PICTURE'],
                    'DETAIL_PAGE_URL' => $item['DETAIL_PAGE_URL'],
                    'PROPERTIES' => $item['PROPERTIES'] ?? []
                ];
            }
        }
        
        // Сортируем разделы и элементы
        uasort($sections, function($a, $b) {
            return ($a['SORT'] < $b['SORT']) ? -1 : 1;
        });
        
        foreach ($sections as &$section) {
            usort($section['ITEMS'], function($a, $b) {
                return strcmp($a['NAME'], $b['NAME']); // Сортировка по имени
            });
        }
        
        $arResult['SECTIONS'] = array_values($sections);
    }
}

