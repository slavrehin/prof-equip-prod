<?php

use Bitrix\Main\Routing\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    
    // ===== СПЕЦИАЛЬНАЯ ПРОВЕРКА ПЕРЕД ВСЕМИ МАРШРУТАМИ =====
    // Если запрос ведет на существующую папку — пропускаем обработку роутером полностью
    $requestUri = $_SERVER['REQUEST_URI'];
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = rtrim($path, '/');
    
    // Защита от пустого пути (главная)
    if ($path === '') {
        return; // Отдаем управление системе
    }
    
    // Проверяем существование физической папки
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $path;
    if (is_dir($fullPath)) {
        return;
    }
    
    // ===== ТОЛЬКО ТЕПЕРЬ МОЖНО ОБРАБАТЫВАТЬ МАРШРУТЫ =====

    $routes->any('/brends/{path}', function ($path) {
        include_once $_SERVER['DOCUMENT_ROOT'] . '/brends/index.php';
        die();
    })->where('path', '.*');

    $routes->any('/search/{path}', function ($path) {
        include_once $_SERVER['DOCUMENT_ROOT'] . '/search/index.php';
        die();
    })->where('path', '.*');

    // 1. Фильтр в каталоге (самый конкретный маршрут)
    $routes->any('/product-category/{sectionPath}/f/{filterPath}/', function ($sectionPath, $filterPath) {
        $_REQUEST['SECTION_CODE_PATH'] = $sectionPath;
        $_REQUEST['SMART_FILTER_PATH'] = $filterPath; // <-- ВАЖНО: устанавливаем параметр для фильтра
        include_once $_SERVER['DOCUMENT_ROOT'] . '/catalog/index.php';
        die();
    })->where('sectionPath', '.+')
    ->where('filterPath', '.*');

    // Каталог (разделы)
    $routes->any('/product-category/{sectionPath}/', function ($sectionPath) {
        $_REQUEST['SECTION_CODE_PATH'] = $sectionPath;
        include_once $_SERVER['DOCUMENT_ROOT'] . '/catalog/index.php';
        die();
    })->where('sectionPath', '.+');
    
    // Каталог (товары)
    $routes->any('/product/{elementCode}/', function ($elementCode) {
        $_REQUEST['ELEMENT_CODE'] = $elementCode;
        include_once $_SERVER['DOCUMENT_ROOT'] . '/catalog/index.php';
        die();
    });
    
    // Услуги в корне
    $routes->any('/{serviceCode}/', function ($serviceCode) {

        $iblockId = GetIBlockIDByCode('services');
        if ($iblockId) {
            $exists = CIBlockElement::GetList(
                array(),
                array(
                    'IBLOCK_ID' => $iblockId,
                    'CODE' => $serviceCode,
                    'ACTIVE' => 'Y'
                ),
                false,
                array('nTopCount' => 1),
                array('ID')
            )->Fetch();
            
            if ($exists) {
                $_REQUEST['ELEMENT_CODE'] = $serviceCode;
                $_GET['ELEMENT_CODE'] = $serviceCode;
                include_once $_SERVER['DOCUMENT_ROOT'] . '/services/index.php';
                die();
            }
        }
        
        include_once $_SERVER['DOCUMENT_ROOT'] . '/404.php';
        die();
    });
};