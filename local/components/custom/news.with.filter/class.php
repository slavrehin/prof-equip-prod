<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Application;

class BrandsComplexComponent extends CBitrixComponent
{
    public function executeComponent()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль инфоблоков не установлен');
            return;
        }

        $componentPage = $this->getPage();

        // Определяем страницу
        $componentPage = $this->getPage();

        // Если страница не определена — 404
        if (!$componentPage) {
            $this->handle404();
            return;
        }

        // Сохраняем переменные для шаблона
        $this->arResult['VARIABLES'] = $this->arResult['VARIABLES'] ?? [];

        // Подключаем шаблон
        $this->IncludeComponentTemplate($componentPage);
    }

protected function getPage()
{
    $request = Application::getInstance()->getContext()->getRequest();
    $curPage = $request->getRequestUri();
    $curPage = strtok($curPage, '?');
    
    
    $folder = rtrim($this->arParams['SEF_FOLDER'] ?? '/brends/', '/');
    
    if (strpos($curPage, $folder) === 0) {
        $relativePath = substr($curPage, strlen($folder));
    } else {
        $relativePath = $curPage;
    }
    
    $relativePath = trim($relativePath, '/');
    
    $pathParts = explode('/', $relativePath);
    
    // Список
    if (empty($relativePath)) {
        return 'news';
    }
    
    // Фильтр
    if (in_array('f', $pathParts)) {
        $key = array_search('f', $pathParts);
        if ($key > 0) {
            // Код элемента — всё до /f/
            $elementCode = implode('/', array_slice($pathParts, 0, $key));
            // Путь фильтра — всё после /f/
            $filterParts = array_slice($pathParts, $key + 1);
            $filterPath = implode('/', $filterParts);
            
            // Сохраняем В ОБА МЕСТА для надёжности
            $this->arResult['VARIABLES']['ELEMENT_CODE'] = $elementCode;
            $this->arResult['VARIABLES']['SMART_FILTER_PATH'] = $filterPath;
            
            // Дублируем в корень arResult для удобства
            $this->arResult['ELEMENT_CODE'] = $elementCode;
            $this->arResult['SMART_FILTER_PATH'] = $filterPath;
            
            // Также передаём в глобальные REQUEST для совместимости
            $_REQUEST['ELEMENT_CODE'] = $elementCode;
            $_REQUEST['SMART_FILTER_PATH'] = $filterPath;
            
            if ($this->checkElementExists($elementCode)) {
                return 'detail_with_filter';
            }
        }
        return false;
    }
    
    // Детальная страница (один сегмент)
    if (count($pathParts) == 1) {
        $elementCode = $pathParts[0];
        
        $this->arResult['VARIABLES']['ELEMENT_CODE'] = $elementCode;
        
        // Проверяем существование
        $exists = $this->checkElementExists($elementCode);
        
        if ($exists) {
            return 'detail';
        } else {
            return false;
        }
    }
    return 'news';
}


    protected function checkElementExists($code)
    {
        if (empty($code) || empty($this->arParams['IBLOCK_ID'])) {
            return false;
        }

        $exists = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
                'CODE' => $code,
                'ACTIVE' => 'Y'
            ],
            false,
            ['nTopCount' => 1],
            ['ID']
        )->Fetch();

        return !empty($exists);
    }

    protected function handle404()
    {
        if ($this->arParams['SET_STATUS_404'] === 'Y') {
            \CHTTP::SetStatus("404 Not Found");
        }

        if ($this->arParams['SHOW_404'] === 'Y') {
            if (!empty($this->arParams['FILE_404'])) {
                include $_SERVER['DOCUMENT_ROOT'] . $this->arParams['FILE_404'];
            } else {
                include $_SERVER['DOCUMENT_ROOT'] . '/404.php';
            }
        }

        define('ERROR_404', true);
        die(); // Останавливаем выполнение
    }
}