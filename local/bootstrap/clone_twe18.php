<?php
// Новая карточка TOLON TWE 18 (шаг 1 local/deploy/tolon_photos_2026_10_13 без замены фото):
// копия складской TWE 18 под CODE professionalnaya-stiralnaya-mashina-tolon-twe-18, без строк про наличие/цену в анонсе.
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('iblock'); \Bitrix\Main\Loader::includeModule('catalog');
$ib = 11; $src = 'professionalnaya-stiralnaya-mashina-tolon-twe-18-zagruzka-18-kg'; $new = 'professionalnaya-stiralnaya-mashina-tolon-twe-18';
if (CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, '=CODE' => $new])->Fetch()) exit("уже есть\n");
$o = CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, '=CODE' => $src])->GetNextElement() or exit("нет исходной\n");
$f = $o->GetFields(); $props = [];
foreach ($o->GetProperties() as $c => $p) if ($p['PROPERTY_TYPE'] !== 'F' && $c !== 'STOCK_BADGE') $props[$c] = $p['PROPERTY_TYPE'] === 'L' ? $p['VALUE_ENUM_ID'] : $p['VALUE'];
$prev = preg_replace(['#<p>\s*<strong>\s*В НАЛИЧИИ\s*</strong>\s*</p>#ui', '#\s*<li[^>]*>(?:(?!</li>).)*?(Наличие:|Примечание:|Цена:)(?:(?!</li>).)*</li>#su'], '', $f['~PREVIEW_TEXT']);
$secs = []; $r = CIBlockElement::GetElementGroups($f['ID'], true); while ($s = $r->Fetch()) $secs[] = $s['ID'];
$el = new CIBlockElement();
$id = $el->Add(['IBLOCK_ID' => $ib, 'CODE' => $new, 'NAME' => 'Профессиональная стиральная машина TOLON TWE 18', 'ACTIVE' => 'Y',
    'PREVIEW_TEXT' => $prev, 'PREVIEW_TEXT_TYPE' => 'html', 'DETAIL_TEXT' => $f['~DETAIL_TEXT'], 'DETAIL_TEXT_TYPE' => 'html',
    'PREVIEW_PICTURE' => $f['PREVIEW_PICTURE'] ? CFile::MakeFileArray($f['PREVIEW_PICTURE']) : false,
    'DETAIL_PICTURE' => $f['DETAIL_PICTURE'] ? CFile::MakeFileArray($f['DETAIL_PICTURE']) : false,
    'IBLOCK_SECTION' => $secs, 'IBLOCK_SECTION_ID' => $f['IBLOCK_SECTION_ID'], 'PROPERTY_VALUES' => $props, 'XML_ID' => 'clone:tolon_photos_2026_10_13']);
if (!$id) exit("ошибка: {$el->LAST_ERROR}\n");
CCatalogProduct::Add(['ID' => $id, 'QUANTITY' => 0, 'TYPE' => 1]);
echo "создана $new, ID=$id\n";
