<?php
/**
 * Раздел «Решения для вентиляции» (resheniya-dlya-ventilyatsii) внутри «Оборудование
 * профессиональной кухни»; в него переносятся «Вытяжные зонты» и «Вентилируемые потолки»
 * (миграция 2026-09-29-halton-sections-props.php). URL подразделов не меняются — шаблон
 * SECTION_PAGE_URL = /product-category/#SECTION_CODE#/.
 * Тексты/SEO/картинку раздела пишет local/deploy/halton/import.php.
 * Идемпотентна: всё ищется по CODE.
 */

call_user_func(static function () {

CModule::IncludeModule('iblock');

global $DB;

$iblockId = (int) GetIBlockIDByCode('catalog');
if (!$iblockId) {
    throw new \RuntimeException('Инфоблок "catalog" не найден.');
}

$kitchen = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'oborudovanie-professionalnoj-kuhni'], false, ['ID'])->Fetch();
if (!$kitchen) {
    throw new \RuntimeException('Раздел oborudovanie-professionalnoj-kuhni не найден.');
}

$parent = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'resheniya-dlya-ventilyatsii'], false, ['ID'])->Fetch();
if ($parent) {
    $parentId = (int) $parent['ID'];
    echo "Раздел resheniya-dlya-ventilyatsii уже существует (ID=$parentId)\n";
} else {
    $obj = new \CIBlockSection();
    $parentId = (int) $obj->Add([
        'IBLOCK_ID' => $iblockId,
        'IBLOCK_SECTION_ID' => (int) $kitchen['ID'],
        'NAME' => 'Решения для вентиляции',
        'CODE' => 'resheniya-dlya-ventilyatsii',
        'ACTIVE' => 'Y',
        'SORT' => 500,
    ]);
    if (!$parentId) {
        throw new \RuntimeException('Раздел не создан: ' . $obj->LAST_ERROR);
    }
    echo "Создан раздел «Решения для вентиляции» (ID=$parentId)\n";
}

foreach (['vytyazhnye-zonty', 'ventiliruemye-potolki'] as $code) {
    $s = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code], false, ['ID', 'IBLOCK_SECTION_ID'])->Fetch();
    if (!$s) {
        throw new \RuntimeException("Раздел $code не найден (миграция 2026-09-29-halton-sections-props.php).");
    }
    if ((int) $s['IBLOCK_SECTION_ID'] === $parentId) {
        echo "Раздел $code уже внутри «Решения для вентиляции»\n";
        continue;
    }
    $obj = new \CIBlockSection();
    if (!$obj->Update((int) $s['ID'], ['IBLOCK_ID' => $iblockId, 'IBLOCK_SECTION_ID' => $parentId])) {
        throw new \RuntimeException("Раздел $code не перенесён: " . $obj->LAST_ERROR);
    }
    echo "Раздел $code перенесён в «Решения для вентиляции»\n";
}

// проверка по базе: оба подраздела на 3-м уровне под новым разделом, родитель — под кухней
$rows = $DB->Query('SELECT CODE, IBLOCK_SECTION_ID, DEPTH_LEVEL FROM b_iblock_section WHERE IBLOCK_ID=' . $iblockId
    . " AND CODE IN ('resheniya-dlya-ventilyatsii','vytyazhnye-zonty','ventiliruemye-potolki')");
while ($r = $rows->Fetch()) {
    $ok = $r['CODE'] === 'resheniya-dlya-ventilyatsii'
        ? ((int) $r['IBLOCK_SECTION_ID'] === (int) $kitchen['ID'] && (int) $r['DEPTH_LEVEL'] === 2)
        : ((int) $r['IBLOCK_SECTION_ID'] === $parentId && (int) $r['DEPTH_LEVEL'] === 3);
    if (!$ok) {
        throw new \RuntimeException("Раздел {$r['CODE']}: неверное положение в дереве (parent={$r['IBLOCK_SECTION_ID']}, depth={$r['DEPTH_LEVEL']}).");
    }
}

});
return true;
