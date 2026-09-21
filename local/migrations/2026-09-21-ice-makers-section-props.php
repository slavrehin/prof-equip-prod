<?php
/**
 * Раздел «Льдогенераторы» (внутри «Барное оборудование») и свойства-характеристики
 * для льдогенераторов Hoshizaki IM (тип L, значения создаёт скрипт импорта товаров
 * local/deploy/hoshizaki_icemakers/import.php).
 *
 * «Бренд» отдельного свойства не получает: используется существующее PROIZVODITEL
 * («Производитель») + BRAND (привязка к инфоблоку брендов), как у остальных товаров.
 *
 * Порядок создания = порядок строк в таблице «Характеристики» на карточке
 * (у всех новых SORT=500, порядок задаёт ID).
 * Идемпотентна: всё ищется по CODE.
 */

CModule::IncludeModule('iblock');

global $DB;

$iblockId = (int) GetIBlockIDByCode('catalog');
if (!$iblockId) {
    throw new \RuntimeException('Инфоблок "catalog" не найден.');
}

// --- 1. Раздел ------------------------------------------------------------

$parent = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'barnoe-oborudovanie'], false, ['ID'])->Fetch();
if (!$parent) {
    throw new \RuntimeException('Раздел barnoe-oborudovanie не найден.');
}

$section = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'ldogeneratory'], false, ['ID', 'IBLOCK_SECTION_ID'])->Fetch();
if (!$section) {
    $obj = new \CIBlockSection();
    $sectionId = $obj->Add([
        'IBLOCK_ID' => $iblockId,
        'IBLOCK_SECTION_ID' => (int) $parent['ID'],
        'NAME' => 'Льдогенераторы',
        'CODE' => 'ldogeneratory',
        'ACTIVE' => 'Y',
        'SORT' => 500,
    ]);
    if (!$sectionId) {
        throw new \RuntimeException('Раздел не создан: ' . $obj->LAST_ERROR);
    }
    $check = $DB->Query('SELECT IBLOCK_SECTION_ID, DEPTH_LEVEL FROM b_iblock_section WHERE ID = ' . (int) $sectionId)->Fetch();
    if (!$check || (int) $check['IBLOCK_SECTION_ID'] !== (int) $parent['ID']) {
        throw new \RuntimeException('Раздел ldogeneratory не найден под barnoe-oborudovanie после Add().');
    }
    echo "Создан раздел «Льдогенераторы» (ID=$sectionId) в «Барное оборудование» (ID={$parent['ID']})\n";
} else {
    echo "Раздел ldogeneratory уже существует (ID={$section['ID']}), пропускаю.\n";
}

// --- 2. Свойства ------------------------------------------------------------

// CODE => [название, показывать в умном фильтре]
$props = [
    'MODEL_ARTIKUL'                => ['Модель / артикул', false],
    'SERIYA_LEDOGENERATORA'        => ['Серия', true],
    'TIP_LDA'                      => ['Тип льда', true],
    'FRAKTSIYA_LDA'                => ['Фракция льда', true],
    'TIP_OHLAZHDENIYA'             => ['Тип охлаждения', true],
    'PROIZVODITELNOST_KG_24_CH'    => ['Производительность, кг/24 ч', false],
    'EMKOST_BUNKERA_KG'            => ['Ёмкость бункера, кг', false],
    'GABARITY_SH_G_V_MM'           => ['Габариты Ш×Г×В, мм', false],
    'NOZHKI'                       => ['Ножки', false],
    'ELEKTROPITANIE'               => ['Электропитание', false],
    'POTREBLYAEMAYA_MOSHHNOST_KVT' => ['Потребляемая мощность, кВт', false],
    'HLADAGENT'                    => ['Хладагент', false],
    'UF_OBEZZARAZHIVANIE'          => ['УФ-обеззараживание', true],
    'VSTROENNYJ_DRENAZHNYJ_NASOS'  => ['Встроенный дренажный насос', true],
    'MATERIAL_VNESHNEGO_KORPUSA'   => ['Материал внешнего корпуса', false],
    'VES_NETTO_KG'                 => ['Вес нетто, кг', false],
    'VES_BRUTTO_KG'                => ['Вес брутто (в упаковке), кг', false],
];

foreach ($props as $code => [$name, $smart]) {
    $existing = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code])->Fetch();
    if ($existing) {
        echo "Свойство $code уже существует (ID={$existing['ID']}), пропускаю.\n";
        continue;
    }
    $prop = new \CIBlockProperty();   // PHP 8.4: только через new
    $id = $prop->Add([
        'IBLOCK_ID' => $iblockId,
        'CODE' => $code,
        'NAME' => $name,
        'PROPERTY_TYPE' => 'L',
        'MULTIPLE' => 'N',
        'SORT' => 500,
        'ACTIVE' => 'Y',
    ]);
    if (!$id) {
        throw new \RuntimeException("Не создано свойство $code: " . $prop->LAST_ERROR);
    }
    if ($smart) {
        // IBLOCK_ID в массиве обязателен, иначе привязка к умному фильтру тихо не создаётся
        $upd = new \CIBlockProperty();
        $upd->Update($id, ['IBLOCK_ID' => $iblockId, 'SMART_FILTER' => 'Y']);
        $sf = $DB->Query('SELECT 1 FROM b_iblock_section_property WHERE PROPERTY_ID = ' . (int) $id . ' AND SMART_FILTER = "Y"')->Fetch();
        if (!$sf) {
            throw new \RuntimeException("Свойство $code: не включён умный фильтр.");
        }
    }
    $check = $DB->Query('SELECT ID FROM b_iblock_property WHERE ID = ' . (int) $id)->Fetch();
    if (!$check) {
        throw new \RuntimeException("Свойство $code не найдено в базе после Add().");
    }
    echo "Создано свойство $code «{$name}» (ID=$id)" . ($smart ? ', умный фильтр' : '') . "\n";
}

return true;
