<?php
/**
 * «Тип предприятия» для оборудования профессиональной кухни (2026-10-21):
 *  a) варианты CatalogStandard::KITCHEN_ENTERPRISE в сквозном свойстве TIP_PREDPRIYATIYA (по VALUE,
 *     XML_ID — слаг значения), SORT 600+ — после прачечных;
 *  b) каждому активному товару раздела oborudovanie-professionalnoj-kuhni — значения по правилам
 *     CatalogStandard::kitchenEnterprise() (подразделы + правки по названию). Прачечные значения товара,
 *     если есть, сохраняются; кухонные пересчитываются целиком.
 * CATALOG_DRY=1 — только отчёт. Идемпотентна. Всё по CODE/VALUE — ID на проде и test3 разные.
 */

call_user_func(static function () {

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/deploy/catalog_restructure/lib.php';
\CModule::IncludeModule('iblock');

$dry = getenv('CATALOG_DRY') === '1';
$iblockId = CatalogMigrate::iblockId();
$DB = CatalogMigrate::db();
$prop = CatalogMigrate::property('TIP_PREDPRIYATIYA');
if (!$prop) {
    throw new \RuntimeException('Нет свойства TIP_PREDPRIYATIYA');
}
$root = CatalogMigrate::section('oborudovanie-professionalnoj-kuhni');
if (!$root) {
    throw new \RuntimeException('Нет раздела oborudovanie-professionalnoj-kuhni');
}

// ---------- a) варианты ----------
$kitchenValues = array_values(CatalogStandard::KITCHEN_ENTERPRISE);
$enumIds = [];
foreach ($kitchenValues as $i => $value) {
    $enumIds[$value] = $dry ? 0 : CatalogMigrate::enumIdFor($prop, $value, 600 + $i * 10);
}
if (!$dry) {
    CatalogMigrate::clearCache();
}

// ---------- b) товары ----------
$rs = $DB->Query("SELECT e.ID, e.NAME, GROUP_CONCAT(s.CODE) CODES
    FROM b_iblock_element e
    JOIN b_iblock_section_element se ON se.IBLOCK_ELEMENT_ID = e.ID
    JOIN b_iblock_section s ON s.ID = se.IBLOCK_SECTION_ID
    WHERE e.IBLOCK_ID = $iblockId AND s.IBLOCK_ID = $iblockId AND e.ACTIVE = 'Y'
      AND s.LEFT_MARGIN >= {$root['LEFT_MARGIN']} AND s.RIGHT_MARGIN <= {$root['RIGHT_MARGIN']}
    GROUP BY e.ID ORDER BY e.ID");
$products = [];
while ($r = $rs->Fetch()) {
    $products[(int)$r['ID']] = ['NAME' => $r['NAME'], 'CODES' => explode(',', $r['CODES'])];
}
$current = CatalogMigrate::values('TIP_PREDPRIYATIYA', array_keys($products) ?: [0]);

$dist = [];
$none = [];
$updated = 0;
foreach ($products as $id => $p) {
    $result = CatalogStandard::kitchenEnterprise($p['CODES'], $p['NAME']);
    if (!$result) {
        $none[] = "$id {$p['NAME']}";
        continue;
    }
    foreach ($result as $v) {
        $dist[$v] = ($dist[$v] ?? 0) + 1;
    }
    $before = $current[$id] ?? [];
    $other = array_values(array_diff($before, $kitchenValues));
    $target = array_merge($other, $result);
    $a = $before;
    $b = $target;
    sort($a);
    sort($b);
    if ($a === $b) {
        continue;
    }
    if (!$dry) {
        $ids = array_map(static fn($v) => $enumIds[$v] ?? CatalogMigrate::enumIdFor($prop, $v), $target);
        \CIBlockElement::SetPropertyValuesEx($id, $iblockId, ['TIP_PREDPRIYATIYA' => $ids]);
    }
    $updated++;
}
echo "Кухня, «Тип предприятия»: товаров " . count($products) . ", изменено $updated, без правил " . count($none) . "\n";
echo "   распределение: " . json_encode($dist, JSON_UNESCAPED_UNICODE) . "\n";
if ($none) {
    echo "   без правил: " . implode('; ', $none) . "\n";
}

if (!$dry) {
    CatalogMigrate::clearCache();
}

});
return true;
