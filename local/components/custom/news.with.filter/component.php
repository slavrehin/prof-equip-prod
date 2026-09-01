<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

// Подключаем класс, если его ещё нет
if (!class_exists('BrandsComplexComponent')) {
    require_once __DIR__ . '/class.php';
}

// Создаём и выполняем компонент
$component = new BrandsComplexComponent();
$component->initComponent($componentName);
$component->arParams = $arParams;
$component->__name = $componentName;
$component->__templatePage = '';

$component->executeComponent();