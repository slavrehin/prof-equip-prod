<?php
/**
 * Стандарт характеристик, шаг 5:
 *  a) «Тип» (TIP_TOVARA) пересчитывается всем товарам каталога (ProductType::apply(..., overwrite)):
 *     оборудование — по новому дереву разделов, запчасти — по группам деталей
 *     (CatalogStandard::PART_GROUPS), текстиль — по виду изделия (CatalogStandard::TEXTILE_TYPES).
 *     Неиспользуемые варианты «Типа» удаляются;
 *  b) «Тип предприятия» пересчитывается по правилам (CatalogStandard::enterpriseRules()) вместо
 *     «отмечено всё» — у 52 товаров стояли все 9 вариантов, фильтр и теги «для гостиниц» и т.п.
 *     были бессмысленны. Товары, к «Типу» которых правил нет (комплекты, вспомогательное), не трогаются;
 *     если по правилам ничего не подошло — значение остаётся прежним (в отчёт).
 * CATALOG_DRY=1 — только отчёт. Идемпотентна (повторный запуск даёт тот же результат).
 */

call_user_func(static function () {

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/deploy/catalog_restructure/lib.php';
\CModule::IncludeModule('iblock');

$dry = getenv('CATALOG_DRY') === '1';
$iblockId = CatalogMigrate::iblockId();
$DB = CatalogMigrate::db();

// ---------- a) «Тип» ----------
$typeProp = CatalogMigrate::property('TIP_TOVARA');
$changed = 0;
$byType = [];
$rs = \CIBlockElement::GetList(['ID' => 'ASC'], ['IBLOCK_ID' => $iblockId], false, false, ['ID']);
while ($e = $rs->Fetch()) {
    $id = (int)$e['ID'];
    if ($dry) {
        $t = ProductType::resolve($id);
        $byType[$t ?? '—']++;
        continue;
    }
    if (ProductType::apply($iblockId, $id, true) !== null) {
        $changed++;
    }
}
if ($dry) {
    arsort($byType);
    echo "a) «Тип» (прогноз): " . json_encode($byType, JSON_UNESCAPED_UNICODE) . "\n";
} else {
    $removed = 0;
    foreach (CatalogMigrate::enums((int)$typeProp['ID']) as $en) {
        $used = $DB->Query('SELECT 1 FROM b_iblock_element_property WHERE IBLOCK_PROPERTY_ID = ' . (int)$typeProp['ID'] . ' AND VALUE_ENUM = ' . $en['ID'] . ' LIMIT 1')->Fetch();
        if (!$used) {
            CatalogMigrate::remember('TIP_TOVARA', $en['XML_ID'], '');
            \CIBlockPropertyEnum::Delete($en['ID']);
            $removed++;
        }
    }
    echo "a) «Тип» обновлён у $changed товаров, удалено неиспользуемых вариантов: $removed\n";
}

// ---------- b) «Тип предприятия» ----------
$rules = CatalogStandard::enterpriseRules();
$ruleTypes = array_flip(CatalogStandard::enterpriseRuleTypes());
$enterpriseProp = CatalogMigrate::property('TIP_PREDPRIYATIYA');

$types = CatalogMigrate::values('TIP_TOVARA');
$candidates = array_keys(array_filter($types, static fn($v) => isset($ruleTypes[$v[0]])));
$loads = CatalogMigrate::values('ZAGRUZKA_KG', $candidates ?: [0]);
$widths = CatalogMigrate::values('SHIRINA_ZONY_GLAZHENIYA_MM', $candidates ?: [0]);
$aquaProp = CatalogMigrate::values('AKVACHISTKA', $candidates ?: [0]);
$current = CatalogMigrate::values('TIP_PREDPRIYATIYA', $candidates ?: [0]);
$names = [];
if ($candidates) {
    $rsN = \CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, 'ID' => $candidates], false, false, ['ID', 'NAME']);
    while ($n = $rsN->Fetch()) {
        $names[(int)$n['ID']] = $n['NAME'];
    }
}

$dist = [];
$kept = [];
$updated = 0;
foreach ($candidates as $eid) {
    $type = $types[$eid][0];
    $l = array_map('floatval', $loads[$eid] ?? []);
    $w = array_map('floatval', $widths[$eid] ?? []);
    $isAqua = in_array('Есть', $aquaProp[$eid] ?? [], true) || in_array('Опционально', $aquaProp[$eid] ?? [], true)
        || preg_match('/аквачист|аква-чист|lagoon|aqua/ui', $names[$eid] ?? '');
    $result = [];
    foreach ($rules as $enterprise => $conditions) {
        foreach ($conditions as $c) {
            if (!in_array($type, $c['types'], true)) {
                continue;
            }
            if (!empty($c['aqua']) && !$isAqua) {
                continue;
            }
            if (isset($c['min']) || isset($c['max'])) {
                $ok = false;
                foreach ($l as $kg) {
                    if ($kg >= ($c['min'] ?? 0) && $kg <= ($c['max'] ?? PHP_INT_MAX)) {
                        $ok = true;
                        break;
                    }
                }
                if (!$ok) {
                    continue;
                }
            }
            if (isset($c['width_max']) && $w && min($w) > $c['width_max']) {
                continue;
            }
            $result[] = $enterprise;
            break;
        }
    }
    if (!$result) {
        $kept[] = $eid . ' ' . mb_substr($names[$eid] ?? '', 0, 60);
        continue;
    }
    foreach ($result as $r) {
        $dist[$r] = ($dist[$r] ?? 0) + 1;
    }
    $before = $current[$eid] ?? [];
    sort($before);
    $sorted = $result;
    sort($sorted);
    if ($before === $sorted) {
        continue;
    }
    if (!$dry) {
        $ids = array_map(static fn($v) => CatalogMigrate::enumIdFor($enterpriseProp, $v), $result);
        \CIBlockElement::SetPropertyValuesEx($eid, $iblockId, ['TIP_PREDPRIYATIYA' => $ids]);
    }
    $updated++;
}
arsort($dist);
echo "b) «Тип предприятия»: кандидатов " . count($candidates) . ", изменено $updated, без совпадений (оставлены как были) " . count($kept) . "\n";
echo "   распределение: " . json_encode($dist, JSON_UNESCAPED_UNICODE) . "\n";
if ($kept) {
    echo "   без совпадений: " . implode('; ', array_slice($kept, 0, 30)) . "\n";
}

if (!$dry) {
    CatalogMigrate::clearCache();
}

});
return true;
