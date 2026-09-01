<?php

if (!\Bitrix\Main\Loader::includeModule('iblock')) {
    return false;
}

global $USER;
if (!($USER instanceof CUser) || !$USER->IsAdmin()) {
    return false;
}

return [
    'parent_menu' => 'global_menu_store',
    'sort' => 250,
    'text' => 'Импорт товаров (CSV)',
    'title' => 'Загрузка товаров в каталог из CSV-файла',
    'icon' => 'catalog_menu_icon',
    'page_icon' => 'catalog_page_icon',
    'items_id' => 'menu_profequip_import',
    'items' => [
        [
            'text' => 'Импорт товаров из CSV',
            'url' => '/local/modules/profequip.import/admin/product_import.php',
            'more_url' => [],
            'title' => 'Загрузка товаров в каталог из CSV-файла',
        ],
    ],
];
