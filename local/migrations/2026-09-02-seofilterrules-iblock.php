<?php
/**
 * Создаёт структуру инфоблока "seofilterrules" (SEO-правила умного фильтра),
 * который использует local/templates/profequip/components/bitrix/catalog/catalog/section.php.
 * Сами правила (элементы инфоблока) сюда не входят — их заводят через админку
 * (Содержимое -> SEO-правила фильтра), это контент, а не структура.
 *
 * Элемент правила: CODE = "<код раздела>__<код свойства фильтра в нижнем регистре>",
 * например "stiralnye-mashiny__proizvoditel". Свойства элемента — шаблоны ниже.
 */

global $DB;

CModule::IncludeModule('iblock');

$iblockCode = 'seofilterrules';

$ib = \CIBlock::GetList([], ['CODE' => $iblockCode])->Fetch();

if ($ib) {
    $iblockId = (int)$ib['ID'];
    echo "Инфоблок $iblockCode уже существует (ID=$iblockId), пропускаю создание.\n";
} else {
    $iblock = new \CIBlock();
    $iblockId = $iblock->Add([
        'ACTIVE' => 'Y',
        'IBLOCK_TYPE_ID' => 'seo',
        'SITE_ID' => ['s1'],
        'CODE' => $iblockCode,
        'NAME' => 'SEO-правила фильтра',
        'SORT' => 500,
        'GROUP_ID' => [1 => 'X', 2 => 'R'],
    ]);

    if (!$iblockId) {
        throw new \RuntimeException('Не создан инфоблок ' . $iblockCode . ': ' . $iblock->LAST_ERROR);
    }

    $check = $DB->Query("SELECT ID FROM b_iblock WHERE ID = " . (int)$iblockId)->Fetch();
    if (!$check) {
        throw new \RuntimeException('Инфоблок ' . $iblockCode . ' не найден в базе после Add() (ID=' . $iblockId . ')');
    }

    echo "Создан инфоблок $iblockCode (ID=$iblockId).\n";
}

$properties = [
    [
        'CODE' => 'H1_TEMPLATE',
        'NAME' => 'Шаблон H1 (#VALUE# — значение фильтра, #SECTION_NAME# — название раздела)',
        'ROW_COUNT' => 1,
        'COL_COUNT' => 30,
        'SORT' => 10,
    ],
    [
        'CODE' => 'TITLE_TEMPLATE',
        'NAME' => 'Шаблон Title',
        'ROW_COUNT' => 1,
        'COL_COUNT' => 30,
        'SORT' => 20,
    ],
    [
        'CODE' => 'DESCRIPTION_TEMPLATE',
        'NAME' => 'Шаблон Description',
        'ROW_COUNT' => 3,
        'COL_COUNT' => 30,
        'SORT' => 30,
    ],
    [
        'CODE' => 'SEO_TEXT_TEMPLATE',
        'NAME' => 'Шаблон SEO-текста (HTML, блок под товарами)',
        'ROW_COUNT' => 10,
        'COL_COUNT' => 60,
        'SORT' => 50,
    ],
];

foreach ($properties as $p) {
    $exists = \CIBlockProperty::GetList([], [
        'IBLOCK_ID' => $iblockId,
        'CODE' => $p['CODE'],
    ])->Fetch();

    if ($exists) {
        echo "Свойство {$p['CODE']} уже существует (ID={$exists['ID']}), пропускаю.\n";
        continue;
    }

    $prop = new \CIBlockProperty();
    $id = $prop->Add([
        'IBLOCK_ID' => $iblockId,
        'NAME' => $p['NAME'],
        'CODE' => $p['CODE'],
        'PROPERTY_TYPE' => 'S',
        'ROW_COUNT' => $p['ROW_COUNT'],
        'COL_COUNT' => $p['COL_COUNT'],
        'MULTIPLE' => 'N',
        'IS_REQUIRED' => 'N',
        'ACTIVE' => 'Y',
        'SORT' => $p['SORT'],
    ]);

    if (!$id) {
        throw new \RuntimeException('Не создано свойство ' . $p['CODE'] . ': ' . $prop->LAST_ERROR);
    }

    $check = $DB->Query("SELECT ID FROM b_iblock_property WHERE ID = " . (int)$id . " AND IBLOCK_ID = " . (int)$iblockId)->Fetch();
    if (!$check) {
        throw new \RuntimeException('Свойство ' . $p['CODE'] . ' не найдено в базе после Add() (ID=' . $id . ')');
    }

    echo "Создано свойство {$p['CODE']} (ID=$id) в инфоблоке $iblockId.\n";
}
