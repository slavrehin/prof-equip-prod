<?php
/**
 * Halton: разделы «Вентилируемые потолки» и «Вытяжные зонты» (оба внутри «Оборудование
 * профессиональной кухни»), свойство SPECS и значение «Halton» у PROIZVODITEL.
 *
 * SPECS — множественная строка с описанием: DESCRIPTION = название характеристики,
 * VALUE = значение. Нужна для характеристик в свободной форме (у Halton ~30 разных
 * ключей, повторяющиеся ключи, строки без значения), которые не ложатся на свойства-
 * списки. Выводится таблицей во вкладке «Характеристики» (catalog.element/.default),
 * в умный фильтр не попадает.
 *
 * Бренд Halton в инфоблоке брендов (brands) уже есть — здесь не создаётся.
 * Данные (тексты, товары, картинки) — local/deploy/halton/import.php.
 * Идемпотентна: всё ищется по CODE / VALUE.
 */

// Тело обёрнуто в call_user_func: локальная переменная $name иначе затирает
// переменную $name раннера run.php (см. local/migrations/README.md).
call_user_func(static function () {

CModule::IncludeModule('iblock');

global $DB;

$iblockId = (int) GetIBlockIDByCode('catalog');
if (!$iblockId) {
    throw new \RuntimeException('Инфоблок "catalog" не найден.');
}

// --- 1. Разделы -----------------------------------------------------------

$parent = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'oborudovanie-professionalnoj-kuhni'], false, ['ID'])->Fetch();
if (!$parent) {
    throw new \RuntimeException('Раздел oborudovanie-professionalnoj-kuhni не найден.');
}

foreach (['ventiliruemye-potolki' => 'Вентилируемые потолки', 'vytyazhnye-zonty' => 'Вытяжные зонты'] as $code => $name) {
    $section = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code], false, ['ID'])->Fetch();
    if ($section) {
        echo "Раздел $code уже существует (ID={$section['ID']}), пропускаю.\n";
        continue;
    }
    $obj = new \CIBlockSection();
    $sectionId = $obj->Add([
        'IBLOCK_ID' => $iblockId,
        'IBLOCK_SECTION_ID' => (int) $parent['ID'],
        'NAME' => $name,
        'CODE' => $code,
        'ACTIVE' => 'Y',
        'SORT' => 500,
    ]);
    if (!$sectionId) {
        throw new \RuntimeException("Раздел $code не создан: " . $obj->LAST_ERROR);
    }
    $check = $DB->Query('SELECT IBLOCK_SECTION_ID FROM b_iblock_section WHERE ID = ' . (int) $sectionId)->Fetch();
    if (!$check || (int) $check['IBLOCK_SECTION_ID'] !== (int) $parent['ID']) {
        throw new \RuntimeException("Раздел $code не найден под oborudovanie-professionalnoj-kuhni после Add().");
    }
    echo "Создан раздел «{$name}» (ID=$sectionId)\n";
}

// --- 2. Свойство SPECS --------------------------------------------------------

$existing = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'SPECS'])->Fetch();
if ($existing) {
    echo "Свойство SPECS уже существует (ID={$existing['ID']}), пропускаю.\n";
} else {
    $prop = new \CIBlockProperty();   // PHP 8.4: только через new
    $id = $prop->Add([
        'IBLOCK_ID' => $iblockId,
        'CODE' => 'SPECS',
        'NAME' => 'Технические характеристики',
        'PROPERTY_TYPE' => 'S',
        'MULTIPLE' => 'Y',
        'WITH_DESCRIPTION' => 'Y',
        'SORT' => 900,
        'ACTIVE' => 'Y',
        'SEARCHABLE' => 'Y',
    ]);
    if (!$id) {
        throw new \RuntimeException('Не создано свойство SPECS: ' . $prop->LAST_ERROR);
    }
    $check = $DB->Query('SELECT WITH_DESCRIPTION, MULTIPLE FROM b_iblock_property WHERE ID = ' . (int) $id)->Fetch();
    if (!$check || $check['WITH_DESCRIPTION'] !== 'Y' || $check['MULTIPLE'] !== 'Y') {
        throw new \RuntimeException('Свойство SPECS создано некорректно.');
    }
    echo "Создано свойство SPECS (ID=$id)\n";
}

// --- 3. Значение «Halton» у PROIZVODITEL (XML_ID = ЧПУ фильтра) -------------

$p = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'PROIZVODITEL'])->Fetch();
if (!$p) {
    throw new \RuntimeException('Нет свойства PROIZVODITEL.');
}
$enum = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $p['ID'], 'VALUE' => 'Halton'])->Fetch();
if ($enum) {
    echo "Значение Halton у PROIZVODITEL уже есть (ID={$enum['ID']}), пропускаю.\n";
} else {
    $busy = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $p['ID'], 'XML_ID' => 'halton'])->Fetch();
    if ($busy) {
        throw new \RuntimeException('XML_ID halton у PROIZVODITEL занят другим значением.');
    }
    $eid = (new \CIBlockPropertyEnum())->Add(['PROPERTY_ID' => $p['ID'], 'VALUE' => 'Halton', 'XML_ID' => 'halton', 'DEF' => 'N']);
    if (!$eid) {
        throw new \RuntimeException('Не создано значение Halton у PROIZVODITEL.');
    }
    echo "Создано значение Halton у PROIZVODITEL (ID=$eid)\n";
}

});
return true;
