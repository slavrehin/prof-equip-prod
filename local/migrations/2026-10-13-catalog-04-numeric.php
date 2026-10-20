<?php
/**
 * Стандарт характеристик, шаг 4: числовые свойства (CatalogStandard::NUMERIC) — тип «Список» →
 * «Число». В фильтре — ползунок «от–до» (DISPLAY_TYPE=A), теги-подборки — диапазоны.
 *   - «Загрузка, кг»: значение берётся из названия товара («загрузка 13,9 кг», «33 кг – 37 кг»),
 *     если там есть, иначе — из прежних вариантов списка (их тексты — числа);
 *   - прежние слаги /f/<код>-is-<значение>/ пишутся в историю как 'num:<число>' — section.php
 *     отдаёт 301 на тег или на диапазон /f/<код>-from-X-to-X/.
 * CATALOG_DRY=1 — только отчёт. Идемпотентна: свойство уже «Число» — пропуск.
 */

call_user_func(static function () {

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/deploy/catalog_restructure/lib.php';
\CModule::IncludeModule('iblock');

$dry = getenv('CATALOG_DRY') === '1';
$iblockId = CatalogMigrate::iblockId();
$DB = CatalogMigrate::db();

foreach (CatalogStandard::NUMERIC as $code => [$displayType, $nameRe]) {
    $prop = CatalogMigrate::property($code);
    if (!$prop) {
        continue;
    }
    if ($prop['PROPERTY_TYPE'] === 'N') {
        echo "$code уже числовое\n";
        continue;
    }

    $enums = CatalogMigrate::enums((int)$prop['ID']);
    $old = CatalogMigrate::values($code);
    $names = [];
    if ($old) {
        $rs = \CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, 'ID' => array_keys($old)], false, false, ['ID', 'NAME']);
        while ($e = $rs->Fetch()) {
            $names[(int)$e['ID']] = $e['NAME'];
        }
    }

    $new = [];
    $fromName = 0;
    foreach ($old as $eid => $texts) {
        $numbers = [];
        if ($nameRe && isset($names[$eid]) && preg_match($nameRe, mb_strtolower($names[$eid]), $m)) {
            $mul = !empty($m['mul']) ? (int)$m['mul'] : 1;
            $numbers[] = round($mul * (float)str_replace(',', '.', $m[2]), 2);
            if (!empty($m[3])) {
                $numbers[] = round($mul * (float)str_replace(',', '.', $m[3]), 2);
            }
            $fromName++;
        } else {
            foreach ($texts as $t) {
                // «3000–4000» → оба конца диапазона
                if (preg_match_all('/\d+(?:[.,]\d+)?/', str_replace(' ', '', $t), $mm)) {
                    foreach ($mm[0] as $n) {
                        $numbers[] = (float)str_replace(',', '.', $n);
                    }
                }
            }
        }
        $numbers = array_values(array_unique(array_filter($numbers, static fn($n) => $n > 0)));
        sort($numbers);
        if ($prop['MULTIPLE'] !== 'Y' && count($numbers) > 1) {
            $numbers = [max($numbers)];
        }
        if ($numbers) {
            $new[$eid] = $numbers;
        }
        if ($dry && $nameRe && isset($names[$eid])) {
            $was = implode(',', $texts);
            $now = implode(',', $numbers);
            if ($was !== $now) {
                echo "  $code #$eid «" . mb_substr($names[$eid], 0, 70) . "»: [$was] → [$now]\n";
            }
        }
    }
    echo "$code: товаров со значением " . count($old) . ", после — " . count($new) . " (из названия: $fromName)\n";
    if ($dry) {
        continue;
    }

    // история слагов: старые варианты списка → num:<число>
    foreach ($enums as $e) {
        $n = CatalogStandard::parseNumber($e['VALUE']);
        if ($n !== null) {
            CatalogMigrate::remember($code, $e['XML_ID'], 'num:' . rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'));
        }
    }

    // снять старые значения, сменить тип, записать числа
    $DB->Query('DELETE FROM b_iblock_element_property WHERE IBLOCK_PROPERTY_ID = ' . (int)$prop['ID']);
    if (!(new \CIBlockProperty())->Update((int)$prop['ID'], ['IBLOCK_ID' => $iblockId, 'PROPERTY_TYPE' => 'N', 'LIST_TYPE' => 'L', 'USER_TYPE' => false])) {
        throw new \RuntimeException("$code: не сменён тип");
    }
    foreach ($enums as $e) {
        \CIBlockPropertyEnum::Delete($e['ID']);
    }
    foreach ($new as $eid => $numbers) {
        \CIBlockElement::SetPropertyValuesEx($eid, $iblockId, [$code => $prop['MULTIPLE'] === 'Y' ? $numbers : $numbers[0]]);
    }
    $DB->Query("UPDATE b_iblock_section_property SET DISPLAY_TYPE = '" . $DB->ForSql($displayType) . "' WHERE PROPERTY_ID = " . (int)$prop['ID']);

    // проверка запросом к базе
    $check = $DB->Query('SELECT p.PROPERTY_TYPE T, (SELECT COUNT(DISTINCT IBLOCK_ELEMENT_ID) FROM b_iblock_element_property WHERE IBLOCK_PROPERTY_ID = p.ID AND VALUE_NUM > 0) N
        FROM b_iblock_property p WHERE p.ID = ' . (int)$prop['ID'])->Fetch();
    if ($check['T'] !== 'N' || (int)$check['N'] !== count($new)) {
        throw new \RuntimeException("$code: тип {$check['T']}, товаров со значением {$check['N']} из " . count($new));
    }
    echo "$code → «Число», значения у {$check['N']} товаров\n";
}

if (!$dry) {
    CatalogMigrate::clearCache();
}

});
return true;
