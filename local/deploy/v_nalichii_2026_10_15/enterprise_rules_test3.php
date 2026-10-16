<?php
// Разово: «Тип предприятия» по правилам CatalogStandard::enterpriseRules() только для ID из env IDS
// (копия шага b) миграции 2026-10-13-catalog-05-types.php; test3, где стандарт уже применён)
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = 'test3.prof-equip.ru';
define('NO_KEEP_STATISTIC', true); define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/deploy/catalog_restructure/lib.php';
\CModule::IncludeModule('iblock');
$dry = getenv('CATALOG_DRY') === '1';
$iblockId = CatalogMigrate::iblockId();
$DB = CatalogMigrate::db();
// ---------- b) «Тип предприятия» ----------
$rules = CatalogStandard::enterpriseRules();
$ruleTypes = array_flip(CatalogStandard::enterpriseRuleTypes());
$enterpriseProp = CatalogMigrate::property('TIP_PREDPRIYATIYA');

$types = CatalogMigrate::values('TIP_TOVARA');
$candidates = array_values(array_filter(array_map('intval', explode(',', getenv('IDS'))), static fn($id) => isset($types[$id]) && isset($ruleTypes[$types[$id][0]])));
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

if (!$dry) { CatalogMigrate::clearCache(); }
