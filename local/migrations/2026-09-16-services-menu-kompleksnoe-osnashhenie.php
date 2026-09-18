<?php
/**
 * Добавляет ссылку на существующую статическую страницу
 * /kompleksnoe-osnashhenie-otelej/ в выпадающее меню "Услуги" в шапке сайта.
 * Меню "Услуги" (components/bitrix/menu/header + блок services-catalog в
 * header.php, компонент news.list c шаблоном directions_header) строится из
 * элементов инфоблока CODE=services; ссылка карточки берётся из свойства LINK
 * элемента, если оно заполнено, иначе — из DETAIL_PAGE_URL
 * (template.php: `$arItem['PROPERTIES']['LINK']['VALUE'] ?: $arItem['DETAIL_PAGE_URL']`).
 *
 * Страница уже живёт в инфоблоке "Направления" (CODE=directionshome) тем же
 * способом — свойство LINK там уже есть и используется для всех 6 элементов,
 * включая эту же страницу (элемент "Комплексное оснащение"). Здесь
 * повторяется тот же приём для инфоблока "Услуги": свойство LINK там
 * отсутствует и создаётся впервые.
 *
 * Картинка карточки — клон файла (новая строка b_file на тот же физический
 * путь), который уже используется элементом "Комплексное оснащение" в
 * "Направления" — картинки только прямым SQL (CIBlockElement::Update() с
 * картинками ломает IBLOCK_SECTION_ID, см. local/migrations/README.md).
 */

global $DB;

CModule::IncludeModule('iblock');

$servicesIblockId = (int)\CIBlock::GetList([], ['CODE' => 'services'])->Fetch()['ID'];
if (!$servicesIblockId) {
    throw new \RuntimeException('Инфоблок services не найден');
}

// 1. Свойство LINK (переопределение ссылки карточки в шапке), как в directionshome
$linkProp = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $servicesIblockId, 'CODE' => 'LINK'])->Fetch();
if (!$linkProp) {
    $property = new \CIBlockProperty();
    $linkPropId = $property->Add([
        'IBLOCK_ID' => $servicesIblockId,
        'CODE' => 'LINK',
        'NAME' => 'Ссылка',
        'PROPERTY_TYPE' => 'S',
        'SORT' => 500,
    ]);
    if (!$linkPropId) {
        throw new \RuntimeException('Не создано свойство LINK в инфоблоке services: ' . $property->LAST_ERROR);
    }
    $check = $DB->Query("SELECT ID FROM b_iblock_property WHERE ID = " . (int)$linkPropId)->Fetch();
    if (!$check) {
        throw new \RuntimeException('Свойство LINK не найдено в базе после Add()');
    }
    echo "Создано свойство LINK в инфоблоке services (ID=$linkPropId)\n";
} else {
    echo "Свойство LINK в инфоблоке services уже существует, пропускаю\n";
}

// 2. Элемент "Комплексное оснащение отелей"
$elementCode = 'kompleksnoe-osnashhenie-otelej';
$existingElement = \CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => $servicesIblockId, 'CODE' => $elementCode],
    false,
    false,
    ['ID']
)->Fetch();

if ($existingElement) {
    echo "Элемент $elementCode в инфоблоке services уже существует (ID={$existingElement['ID']}), пропускаю\n";
} else {
    $element = new \CIBlockElement();
    $newElementId = $element->Add([
        'IBLOCK_ID' => $servicesIblockId,
        'CODE' => $elementCode,
        'NAME' => 'Комплексное оснащение отелей',
        'ACTIVE' => 'Y',
        'SORT' => 500,
        'PROPERTY_VALUES' => [
            'LINK' => '/kompleksnoe-osnashhenie-otelej/',
        ],
    ]);
    if (!$newElementId) {
        throw new \RuntimeException("Не создан элемент $elementCode: " . $element->LAST_ERROR);
    }

    $check = $DB->Query("SELECT ID FROM b_iblock_element WHERE ID = " . (int)$newElementId)->Fetch();
    if (!$check) {
        throw new \RuntimeException('Элемент не найден в базе после Add()');
    }
    echo "Создан элемент $elementCode (ID=$newElementId)\n";

    // Картинка для карточки в шапке — клонируем файл того же элемента
    // из инфоблока "Направления" (match по NAME, т.к. у него пустой CODE).
    $directionsIblockId = (int)\CIBlock::GetList([], ['CODE' => 'directionshome'])->Fetch()['ID'];
    $sourceFileId = 0;
    if ($directionsIblockId) {
        $nameEsc = $DB->ForSql('Комплексное оснащение');
        $sourceElement = $DB->Query(
            "SELECT PREVIEW_PICTURE FROM b_iblock_element WHERE IBLOCK_ID = " . $directionsIblockId
            . " AND NAME = '$nameEsc'"
        )->Fetch();
        if ($sourceElement && $sourceElement['PREVIEW_PICTURE']) {
            $sourceFileId = (int)$sourceElement['PREVIEW_PICTURE'];
        }
    }

    if ($sourceFileId) {
        $DB->Query(
            "INSERT INTO b_file (TIMESTAMP_X, MODULE_ID, HEIGHT, WIDTH, FILE_SIZE, CONTENT_TYPE, SUBDIR, FILE_NAME, ORIGINAL_NAME, DESCRIPTION)
             SELECT NOW(), MODULE_ID, HEIGHT, WIDTH, FILE_SIZE, CONTENT_TYPE, SUBDIR, FILE_NAME, ORIGINAL_NAME, DESCRIPTION
             FROM b_file WHERE ID = " . $sourceFileId
        );
        $newFileId = (int)$DB->LastID();
        if (!$newFileId) {
            throw new \RuntimeException('Не удалось клонировать b_file (source ID=' . $sourceFileId . ')');
        }

        $DB->Query("UPDATE b_iblock_element SET PREVIEW_PICTURE = $newFileId WHERE ID = " . (int)$newElementId);

        $checkFile = $DB->Query(
            "SELECT PREVIEW_PICTURE FROM b_iblock_element WHERE ID = " . (int)$newElementId
        )->Fetch();
        if ((int)$checkFile['PREVIEW_PICTURE'] !== $newFileId) {
            throw new \RuntimeException('PREVIEW_PICTURE не проставился для элемента ' . $newElementId);
        }
        echo "Проставлена картинка для $elementCode (FILE ID=$newFileId, клон из $sourceFileId)\n";
    } else {
        echo "ПРЕДУПРЕЖДЕНИЕ: не найден исходный файл картинки, элемент $elementCode создан без превью-картинки\n";
    }
}
