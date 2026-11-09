<?php
// Добавляет вариант списка свойству каталога: php add_enum.php CODE "Значение"
// Для «строгих» скриптов local/deploy, ожидающих значения, которые на проде уже были (Danube, OASIS…).
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('iblock');
[, $code, $value] = $argv;
$ib = (int) CIBlock::GetList([], ['CODE' => 'catalog', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$p = CIBlockProperty::GetList([], ['IBLOCK_ID' => $ib, 'CODE' => $code])->Fetch() or exit("нет свойства $code\n");
$xml = class_exists('CatalogStandard') ? CatalogStandard::slug($value) : md5($value);
$id = (new CIBlockPropertyEnum())->Add(['PROPERTY_ID' => $p['ID'], 'VALUE' => $value, 'XML_ID' => $xml ?: md5($value), 'SORT' => 500]);
echo "вариант $code = «{$value}» -> $id\n";
