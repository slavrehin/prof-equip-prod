<?php
/**
 * HL-блок "SaleBanner" — переносит попап-баннер про складские запасы
 * (раньше зашит статикой в index.php и prachechnaya/index.php) под
 * управление из админки: Контент → Highload-блоки → SaleBanner.
 * Рендер — profequip_RenderSaleBanner() в function.php.
 *
 * Стартовая строка создаётся с UF_ACTIVE=0 (баннер выключен сразу после
 * миграции) и UF_IMAGE = текущая промо-картинка (local/layout/dist/assets/
 * img/sale-promo/promo.png), чтобы просто включить UF_ACTIVE давало тот же
 * баннер, что был раньше, а не пустоту.
 */

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;

if (!Loader::includeModule('highloadblock')) {
    throw new \RuntimeException('Модуль highloadblock не установлен');
}

global $DB;

$hlName = 'SaleBanner';
$hlTableName = 'sale_banner';

$hlBlockRow = HighloadBlockTable::getList(['filter' => ['NAME' => $hlName]])->fetch();
if (!$hlBlockRow) {
    $addResult = HighloadBlockTable::add([
        'NAME' => $hlName,
        'TABLE_NAME' => $hlTableName,
    ]);
    if (!$addResult->isSuccess()) {
        throw new \RuntimeException('Не создан HL-блок SaleBanner: ' . implode('; ', $addResult->getErrorMessages()));
    }
    $hlBlockRow = HighloadBlockTable::getList(['filter' => ['ID' => $addResult->getId()]])->fetch();
    echo "Создан HL-блок SaleBanner (ID={$hlBlockRow['ID']})\n";
} else {
    echo "HL-блок SaleBanner уже существует (ID={$hlBlockRow['ID']}), пропускаю создание\n";
}

$check = $DB->Query('SELECT ID FROM b_hlblock_entity WHERE ID = ' . (int)$hlBlockRow['ID'])->Fetch();
if (!$check) {
    throw new \RuntimeException('HL-блок SaleBanner не найден в базе после создания');
}

$entityId = 'HLBLOCK_' . $hlBlockRow['ID'];

$fields = [
    'UF_ACTIVE' => [
        'USER_TYPE_ID' => 'boolean',
        'XML_ID' => 'sale_banner_active',
        'SORT' => 100,
        'EDIT_FORM_LABEL' => ['ru' => 'Показывать баннер'],
        'SETTINGS' => ['DEFAULT_VALUE' => '0', 'LABEL_YES' => 'Да', 'LABEL_NO' => 'Нет'],
    ],
    'UF_IMAGE' => [
        'USER_TYPE_ID' => 'file',
        'XML_ID' => 'sale_banner_image',
        'SORT' => 200,
        'EDIT_FORM_LABEL' => ['ru' => 'Картинка (рекомендуемое соотношение сторон ~2.25:1, например 1900×845)'],
    ],
    'UF_LINK' => [
        'USER_TYPE_ID' => 'string',
        'XML_ID' => 'sale_banner_link',
        'SORT' => 300,
        'EDIT_FORM_LABEL' => ['ru' => 'Ссылка при клике'],
    ],
    'UF_ALT' => [
        'USER_TYPE_ID' => 'string',
        'XML_ID' => 'sale_banner_alt',
        'SORT' => 400,
        'EDIT_FORM_LABEL' => ['ru' => 'Alt-текст картинки'],
    ],
];

$userTypeEntity = new \CUserTypeEntity();
foreach ($fields as $fieldName => $fieldDef) {
    $existingField = $userTypeEntity->GetList([], ['ENTITY_ID' => $entityId, 'FIELD_NAME' => $fieldName])->Fetch();
    if ($existingField) {
        echo "Поле $fieldName уже существует, пропускаю\n";
        continue;
    }

    $fieldId = $userTypeEntity->Add(array_merge($fieldDef, [
        'ENTITY_ID' => $entityId,
        'FIELD_NAME' => $fieldName,
        'MULTIPLE' => 'N',
        'MANDATORY' => 'N',
        'SHOW_FILTER' => 'N',
        'SHOW_IN_LIST' => 'Y',
        'EDIT_IN_LIST' => 'Y',
        'IS_SEARCHABLE' => 'N',
    ]));
    if (!$fieldId) {
        throw new \RuntimeException("Не создано поле $fieldName: " . $userTypeEntity->LAST_ERROR);
    }

    $checkField = $DB->Query('SELECT ID FROM b_user_field WHERE ID = ' . (int)$fieldId)->Fetch();
    if (!$checkField) {
        throw new \RuntimeException("Поле $fieldName не найдено в базе после создания");
    }
    echo "Создано поле $fieldName (ID=$fieldId)\n";
}

$hlEntity = HighloadBlockTable::compileEntity($hlBlockRow);
$dataClass = $hlEntity->getDataClass();

$existingRow = $dataClass::getList(['select' => ['ID'], 'limit' => 1])->fetch();
if (!$existingRow) {
    // UF_IMAGE (тип "file") через ORM add()/update() молча зануляется до 0,
    // даже когда передан валидный ID существующего CFile — тот же класс
    // проблемы, что и с картинками инфоблоков (см. README "Известные
    // грабли"). Пишем ID картинки прямым SQL в физическую таблицу HL-блока
    // (TABLE_NAME = sale_banner), сам файл сохраняем штатно через CFile.
    $addRowResult = $dataClass::add([
        'UF_ACTIVE' => 0,
        'UF_LINK' => '/product-category/prachechnoe-oborudovanie-v-nalichii/',
        'UF_ALT' => 'Складской запас по спецценам',
    ]);
    if (!$addRowResult->isSuccess()) {
        throw new \RuntimeException('Не создана строка-конфиг SaleBanner: ' . implode('; ', $addRowResult->getErrorMessages()));
    }
    $rowId = $addRowResult->getId();

    $checkRow = $dataClass::getList(['select' => ['ID'], 'filter' => ['=ID' => $rowId]])->fetch();
    if (!$checkRow) {
        throw new \RuntimeException('Строка-конфиг не найдена в базе после создания');
    }

    $imageFieldId = null;
    $sourceImage = $_SERVER['DOCUMENT_ROOT'] . '/local/layout/dist/assets/img/sale-promo/promo.png';
    if (is_file($sourceImage)) {
        $arFile = \CFile::MakeFileArray($sourceImage);
        if ($arFile) {
            $imageFieldId = \CFile::SaveFile($arFile, 'import_test');
        }
    }

    if ($imageFieldId) {
        $DB->Query('UPDATE sale_banner SET UF_IMAGE = ' . (int)$imageFieldId . ' WHERE ID = ' . (int)$rowId);
        $checkImage = $DB->Query('SELECT UF_IMAGE FROM sale_banner WHERE ID = ' . (int)$rowId)->Fetch();
        if ((int)($checkImage['UF_IMAGE'] ?? 0) !== (int)$imageFieldId) {
            throw new \RuntimeException('UF_IMAGE не записался прямым SQL после SaveFile');
        }
    }

    echo 'Создана строка-конфиг баннера (ID=' . $rowId . '), UF_ACTIVE=0 — баннер выключен' . ($imageFieldId ? ', картинка перенесена из текущего дизайна' : ', картинка НЕ перенесена (файл-источник не найден)') . "\n";
} else {
    echo 'Строка-конфиг баннера уже существует (ID=' . $existingRow['ID'] . '), пропускаю' . "\n";
}
