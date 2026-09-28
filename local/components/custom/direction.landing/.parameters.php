<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    'PARAMETERS' => [
        'DIRECTION_CODE' => [
            'PARENT' => 'BASE',
            'NAME' => 'Код направления (значение свойства «Направление» в инфоблоках лендинга)',
            'TYPE' => 'STRING',
            'DEFAULT' => 'prachechnaya',
        ],
        'DIRECTION_IBLOCK_CODE' => [
            'PARENT' => 'BASE',
            'NAME' => 'Код инфоблока направления (CODE), в котором лежит элемент направления с тем же CODE',
            'TYPE' => 'STRING',
            'DEFAULT' => 'prachechnaya',
        ],
        'CACHE_TIME' => ['DEFAULT' => 3600],
    ],
];
