<?php
/**
 * Фасеты умного фильтра для разделов «Вентилируемые потолки» / «Вытяжные зонты» (Halton):
 * свойства-списки VENT_* с фиксированными значениями и человекочитаемыми XML_ID (ЧПУ фильтра
 * вида /product-category/vytyazhnye-zonty/f/vent_komp_vozdukh-is-vstroennaya/).
 *
 * Привязка к умному фильтру — на весь инфоблок (SECTION_ID=0), как у остальных свойств;
 * фильтр раздела показывает только свойства, у которых есть значения в этом разделе, поэтому
 * в других категориях фасеты не появляются.
 * В таблице «Характеристики» карточки VENT_* не выводятся (дублировали бы SPECS) — см.
 * catalog.element/.default/template.php. Значения товарам ставит local/deploy/halton/import.php.
 * Идемпотентна: свойства ищутся по CODE, значения — по XML_ID.
 */

call_user_func(static function () {

CModule::IncludeModule('iblock');

global $DB;

$iblockId = (int) GetIBlockIDByCode('catalog');
if (!$iblockId) {
    throw new \RuntimeException('Инфоблок "catalog" не найден.');
}

// CODE => [название, множественное, SORT, [XML_ID => значение]]; порядок значений = порядок в фильтре
$props = [
    'VENT_KONSTRUKTSIYA' => ['Конструкция', false, 510, [
        'ventiliruemyj-potolok' => 'Вентилируемый потолок',
        'vytyazhnoj-zont' => 'Вытяжной зонт',
        'pristennyj-nizkij' => 'Пристенный зонт с низкой боковой панелью',
    ]],
    'VENT_TEKHNOLOGII' => ['Технология', true, 520, [
        'capture-jet' => 'Capture Jet™',
        'capture-ray' => 'Capture Ray™ (UV-обработка)',
    ]],
    'VENT_KOMP_VOZDUKH' => ['Подача компенсационного воздуха', false, 530, [
        'vstroennaya' => 'Встроенная',
        'opcionalno' => 'Опционально',
        'otdelnoj-sistemoj' => 'Отдельной системой вентиляции',
    ]],
    'VENT_SNIZHENIE' => ['Снижение расхода вытяжного воздуха', false, 540, [
        'do-30' => 'до 30%',
        'do-30-40' => 'до 30–40%',
        'do-40' => 'до 40%',
        'do-50' => 'до 50%',
    ]],
    'VENT_NIZKIE_POTOLKI' => ['Исполнение для низких потолков', false, 550, [
        'est' => 'Есть',
    ]],
    'VENT_OPTSII' => ['Опции и интеграции', true, 560, [
        'pozharotushenie' => 'Встроенная система пожаротушения',
        'marvel' => 'Система M.A.R.V.E.L.',
        'osveshchenie' => 'Встроенное освещение',
        'touch-screen' => 'Сенсорная панель Halton Touch Screen',
        'pollustop' => 'Блок очистки воздуха Pollustop',
        'ugolnyj-filtr' => 'Угольный фильтр',
    ]],
];

foreach ($props as $code => [$name, $multiple, $sort, $values]) {
    $existing = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code])->Fetch();
    if ($existing) {
        $id = (int) $existing['ID'];
        echo "Свойство $code уже существует (ID=$id)\n";
    } else {
        $prop = new \CIBlockProperty();   // PHP 8.4: только через new
        $id = (int) $prop->Add([
            'IBLOCK_ID' => $iblockId,
            'CODE' => $code,
            'NAME' => $name,
            'PROPERTY_TYPE' => 'L',
            'LIST_TYPE' => 'C',
            'MULTIPLE' => $multiple ? 'Y' : 'N',
            'SORT' => $sort,
            'ACTIVE' => 'Y',
        ]);
        if (!$id) {
            throw new \RuntimeException("Не создано свойство $code: " . $prop->LAST_ERROR);
        }
        // IBLOCK_ID в массиве обязателен, иначе привязка к умному фильтру тихо не создаётся
        (new \CIBlockProperty())->Update($id, ['IBLOCK_ID' => $iblockId, 'SMART_FILTER' => 'Y', 'DISPLAY_EXPANDED' => 'Y']);
        $sf = $DB->Query('SELECT 1 FROM b_iblock_section_property WHERE PROPERTY_ID = ' . $id . ' AND SMART_FILTER = "Y"')->Fetch();
        if (!$sf) {
            throw new \RuntimeException("Свойство $code: не включён умный фильтр.");
        }
        echo "Создано свойство $code «{$name}» (ID=$id), умный фильтр\n";
    }

    $valueSort = 0;
    foreach ($values as $xmlId => $value) {
        $valueSort += 10;
        $enum = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $id, 'XML_ID' => $xmlId])->Fetch();
        if ($enum) {
            continue;
        }
        $eid = (new \CIBlockPropertyEnum())->Add(['PROPERTY_ID' => $id, 'VALUE' => $value, 'XML_ID' => $xmlId, 'SORT' => $valueSort, 'DEF' => 'N']);
        if (!$eid) {
            throw new \RuntimeException("$code: не создано значение «{$value}»");
        }
        echo "  $code: значение «{$value}» ($xmlId)\n";
    }
}

});
return true;
