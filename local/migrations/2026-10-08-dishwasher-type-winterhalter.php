<?php
/**
 * Фасет «Тип» (TIP_POSUDOMOECHNOJ_MASHINY) для карточек-серий Winterhalter в разделе
 * «Посудомоечное оборудование» — без значения они пропадали из выдачи при выборе типа.
 * Добавляет значения «Конвейерная», «Туннельная», «Котломоечная» (под MTF / MTR, CTR, STF /
 * котломоечные — в «Фронтальная/Стаканомоечная/Купольная» они не укладываются) и проставляет
 * тип по CODE карточки (ID на test3 и проде могут расходиться). Отсутствующий CODE пропускается.
 * Идемпотентна: значения ищутся по XML_ID, установка значения перезаписывает то же самое.
 * Зависит от 2026-10-08-hobart-dishwasher-type.php.
 */

call_user_func(static function () {

CModule::IncludeModule('iblock');

global $DB;

$iblockId = (int) GetIBlockIDByCode('catalog');
$prop = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'TIP_POSUDOMOECHNOJ_MASHINY'])->Fetch();
if (!$prop) {
    throw new \RuntimeException('Нет свойства TIP_POSUDOMOECHNOJ_MASHINY (миграция 2026-10-08-hobart-dishwasher-type.php).');
}
$propId = (int) $prop['ID'];

$sort = 30;
foreach (['konveyernaya' => 'Конвейерная', 'tunnelnaya' => 'Туннельная', 'kotlomoechnaya' => 'Котломоечная'] as $xmlId => $value) {
    $sort += 10;
    if (\CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propId, 'XML_ID' => $xmlId])->Fetch()) {
        continue;
    }
    if (!(new \CIBlockPropertyEnum())->Add(['PROPERTY_ID' => $propId, 'VALUE' => $value, 'XML_ID' => $xmlId, 'SORT' => $sort, 'DEF' => 'N'])) {
        throw new \RuntimeException("Не создано значение «{$value}»");
    }
    echo "Значение «{$value}» ($xmlId)\n";
}

$enums = [];
$rs = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propId]);
while ($e = $rs->Fetch()) {
    $enums[$e['XML_ID']] = (int) $e['ID'];
}

$byCode = [
    'vstraivaemye-podstolnye-posudomoechnye-mashiny-winterhalter-uc' => 'frontalnaya',
    'kupolnye-posudomoechnye-mashiny-winterhalter-pt' => 'kupolnaya',
    'kotlomoechnye-mashiny-winterhalter' => 'kotlomoechnaya',
    'konveyernye-posudomoechnye-mashiny-winterhalter-mtf' => 'konveyernaya',
    'tunnelnye-posudomoechnye-mashiny-winterhalter-mtr' => 'tunnelnaya',
    'tunnelnye-posudomoechnye-mashiny-winterhalter-ctr' => 'tunnelnaya',
    'odnotankovye-tunnelnye-posudomoechnye-mashiny-winterhalter-stf' => 'tunnelnaya',
];
foreach ($byCode as $code => $xmlId) {
    $el = \CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code], false, false, ['ID'])->Fetch();
    if (!$el) {
        echo "$code: нет на этом сайте, пропуск\n";
        continue;
    }
    $id = (int) $el['ID'];
    \CIBlockElement::SetPropertyValuesEx($id, $iblockId, ['TIP_POSUDOMOECHNOJ_MASHINY' => $enums[$xmlId]]);
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex($iblockId, $id);
    $chk = $DB->Query("SELECT VALUE_ENUM FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID=$propId")->Fetch();
    if ((int) ($chk['VALUE_ENUM'] ?? 0) !== $enums[$xmlId]) {
        throw new \RuntimeException("$code: тип не записался");
    }
    echo "$code (ID=$id): $xmlId\n";
}

});
return true;
