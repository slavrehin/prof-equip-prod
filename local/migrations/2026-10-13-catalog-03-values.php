<?php
/**
 * Стандарт характеристик, шаг 3 — значения:
 *  a) слияние свойств-дублей (STRANA → COUNTRY, «Ширина вала» → «Рабочая ширина вала», кПа/кг·см² → бар
 *     и т.д., CatalogStandard::PROPERTY_MERGES): значение переносится, где у цели пусто; источник скрывается;
 *  b) точечные замены значений (CatalogStandard::VALUE_MAP): «220 / 380» = «220/380», «электрический» →
 *     «Электрический», «Фермест» = «FERMEST»…;
 *  c) «Производитель» = страна (Россия) → в «Страну»;
 *  d) дубли вариантов с одинаковым текстом (с точностью до регистра/пробелов) — сливаются;
 *  e) числовые списки — единый формат («1,5», «до 70», «55–65») и сортировка по числу;
 *  f) ЧПУ-слаги вместо хэшей XML_ID у всех вариантов списков каталога.
 * Каждая смена XML_ID пишется в b_profequip_slug_history → section.php отдаёт 301 со старых адресов фильтра.
 * Идемпотентна.
 */

call_user_func(static function () {

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/deploy/catalog_restructure/lib.php';
\CModule::IncludeModule('iblock');

$iblockId = CatalogMigrate::iblockId();
$DB = CatalogMigrate::db();
CatalogMigrate::ensureHistoryTable();

$formatNumber = static function (float $n): string {
    $s = rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',');
    return $s === '' ? '0' : $s;
};

// ---------- a) слияние свойств-дублей ----------
foreach (CatalogStandard::PROPERTY_MERGES as $srcCode => [$dstCode, $factor]) {
    $src = CatalogMigrate::property($srcCode);
    $dst = CatalogMigrate::property($dstCode);
    if (!$src || !$dst) {
        continue;
    }
    $srcValues = CatalogMigrate::values($srcCode);
    $dstValues = CatalogMigrate::values($dstCode, array_keys($srcValues) ?: [0]);
    $copied = 0;
    foreach ($srcValues as $eid => $vals) {
        if (!empty($dstValues[$eid])) {
            continue;
        }
        $enumIds = [];
        foreach ($vals as $v) {
            $v = trim($v);
            if ($v === '') {
                continue;
            }
            if ($factor !== null) {
                $n = CatalogStandard::parseNumber($v);
                if ($n === null) {
                    continue;
                }
                $v = $formatNumber(round($n * $factor, $factor >= 1 ? 1 : 2));
            }
            $enumIds[] = $dst['PROPERTY_TYPE'] === 'L' ? CatalogMigrate::enumIdFor($dst, $v) : $v;
        }
        if (!$enumIds) {
            continue;
        }
        \CIBlockElement::SetPropertyValuesEx($eid, $iblockId, [$dstCode => $dst['MULTIPLE'] === 'Y' ? $enumIds : $enumIds[0]]);
        $copied++;
    }
    if ($src['ACTIVE'] !== 'N') {
        (new \CIBlockProperty())->Update((int)$src['ID'], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'N']);
    }
    echo "a) $srcCode → $dstCode: перенесено у $copied товаров, источник скрыт\n";
}

// ---------- b) точечные замены ----------
foreach (CatalogStandard::VALUE_MAP as $code => $map) {
    $prop = CatalogMigrate::property($code);
    if (!$prop) {
        continue;
    }
    foreach ($map as $old => $new) {
        $enums = CatalogMigrate::enums((int)$prop['ID']);
        $oldId = $newId = null;
        foreach ($enums as $e) {
            if ($e['VALUE'] === $old) {
                $oldId = $e['ID'];
            }
            if ($e['VALUE'] === $new) {
                $newId = $e['ID'];
            }
        }
        if ($oldId === null) {
            continue;
        }
        if ($newId !== null) {
            $n = CatalogMigrate::mergeEnum($prop, $oldId, $newId);
            echo "b) $code: «{$old}» слит с «{$new}» ($n)\n";
        } else {
            CatalogMigrate::updateEnum($prop, $oldId, ['VALUE' => $new, 'XML_ID' => CatalogStandard::slug($new)]);
            echo "b) $code: «{$old}» → «{$new}»\n";
        }
    }
}

// ---------- c) «Производитель» = страна ----------
$manufacturer = CatalogMigrate::property('PROIZVODITEL');
$country = CatalogMigrate::property('COUNTRY');
foreach (CatalogStandard::MANUFACTURER_IS_COUNTRY as $value) {
    $enumId = null;
    foreach (CatalogMigrate::enums((int)$manufacturer['ID']) as $e) {
        if ($e['VALUE'] === $value) {
            $enumId = $e['ID'];
        }
    }
    if ($enumId === null) {
        continue;
    }
    $rs = $DB->Query('SELECT IBLOCK_ELEMENT_ID E FROM b_iblock_element_property WHERE IBLOCK_PROPERTY_ID = ' . (int)$manufacturer['ID'] . ' AND VALUE_ENUM = ' . $enumId);
    $n = 0;
    while ($r = $rs->Fetch()) {
        $eid = (int)$r['E'];
        $countries = CatalogMigrate::values('COUNTRY', [$eid])[$eid] ?? [];
        if (!in_array($value, $countries, true)) {
            $ids = [];
            foreach ($countries as $c) {
                $ids[] = CatalogMigrate::enumIdFor($country, $c);
            }
            $ids[] = CatalogMigrate::enumIdFor($country, $value);
            \CIBlockElement::SetPropertyValuesEx($eid, $iblockId, ['COUNTRY' => $ids]);
        }
        $n++;
    }
    $DB->Query('DELETE FROM b_iblock_element_property WHERE IBLOCK_PROPERTY_ID = ' . (int)$manufacturer['ID'] . ' AND VALUE_ENUM = ' . $enumId);
    CatalogMigrate::remember('PROIZVODITEL', CatalogMigrate::enums((int)$manufacturer['ID'])[$enumId]['XML_ID'], '');
    \CIBlockPropertyEnum::Delete($enumId);
    echo "c) Производитель «{$value}» → Страна у $n товаров\n";
}

// ---------- d, e, f) по всем спискам каталога ----------
$normKey = static function (string $v): string {
    $v = mb_strtolower(trim($v));
    $v = str_replace(['ё', ' '], ['е', ' '], $v);
    $v = preg_replace('/\s*([\/\-–])\s*/u', '$1', $v);
    $v = preg_replace('/\s+/u', ' ', $v);
    return rtrim($v, '. ');
};
$isNumericLike = static fn(string $v): bool => (bool)preg_match('/^\s*(?:[<≤>≥]|до|от)?\s*\d+(?:[.,]\d+)?(?:\s*[-–]\s*\d+(?:[.,]\d+)?)?\s*$/u', $v);
$formatNumericValue = static function (string $v) use ($formatNumber): string {
    $v = trim(str_replace(' ', ' ', $v));
    if (preg_match('/^(?:<|≤|до)\s*(\d+(?:[.,]\d+)?)$/u', $v, $m)) {
        return 'до ' . $formatNumber((float)str_replace(',', '.', $m[1]));
    }
    if (preg_match('/^(?:>|≥|от)\s*(\d+(?:[.,]\d+)?)$/u', $v, $m)) {
        return 'от ' . $formatNumber((float)str_replace(',', '.', $m[1]));
    }
    if (preg_match('/^(\d+(?:[.,]\d+)?)\s*[-–]\s*(\d+(?:[.,]\d+)?)$/u', $v, $m)) {
        return $formatNumber((float)str_replace(',', '.', $m[1])) . '–' . $formatNumber((float)str_replace(',', '.', $m[2]));
    }
    if (preg_match('/^\d+(?:[.,]\d+)?$/u', $v)) {
        return $formatNumber((float)str_replace(',', '.', $v));
    }
    return $v;
};
$skipSlug = ['TIP_TOVARA']; // у «Типа» слаги уже человеческие (ProductType)

$rsProps = \CIBlockProperty::GetList(['ID' => 'ASC'], ['IBLOCK_ID' => $iblockId, 'PROPERTY_TYPE' => 'L']);
$stats = ['dedup' => 0, 'format' => 0, 'slug' => 0, 'sort' => 0];
while ($prop = $rsProps->Fetch()) {
    $pid = (int)$prop['ID'];
    $enums = CatalogMigrate::enums($pid);
    if (!$enums) {
        continue;
    }
    $usage = [];
    $rs = $DB->Query("SELECT VALUE_ENUM, COUNT(*) N FROM b_iblock_element_property WHERE IBLOCK_PROPERTY_ID = $pid GROUP BY VALUE_ENUM");
    while ($r = $rs->Fetch()) {
        $usage[(int)$r['VALUE_ENUM']] = (int)$r['N'];
    }

    // e) числовые списки — формат значений (только если ≥90% вариантов числовые; артикулы/модели —
    // идентификаторы, их не трогаем: «0123» не число)
    $identifiers = ['ARTIKUL_ZAPCHASTI', 'ORIGINAL_ZAPCHASTI', 'MODEL', 'MODEL_ARTIKUL', 'MODELI_OBORUDOVANIYA', 'SERIYA_LEDOGENERATORA', 'LINEYKA'];
    $numericCount = count(array_filter($enums, static fn($e) => $isNumericLike($e['VALUE'])));
    $numeric = !in_array($prop['CODE'], $identifiers, true) && $numericCount >= 0.9 * count($enums);
    if ($numeric) {
        foreach ($enums as $e) {
            $formatted = $formatNumericValue($e['VALUE']);
            if ($formatted !== $e['VALUE']) {
                CatalogMigrate::updateEnum($prop, $e['ID'], ['VALUE' => $formatted]);
                $stats['format']++;
            }
        }
        $enums = CatalogMigrate::enums($pid);
    }

    // d) дубли
    $groups = [];
    foreach ($enums as $e) {
        $groups[$normKey($e['VALUE'])][] = $e['ID'];
    }
    foreach ($groups as $ids) {
        if (count($ids) < 2) {
            continue;
        }
        usort($ids, static fn($a, $b) => ($usage[$b] ?? 0) <=> ($usage[$a] ?? 0));
        $keep = array_shift($ids);
        foreach ($ids as $dup) {
            CatalogMigrate::mergeEnum($prop, $dup, $keep);
            $stats['dedup']++;
        }
    }
    $enums = CatalogMigrate::enums($pid);

    // e) сортировка числовых по значению
    if ($numeric) {
        foreach ($enums as $e) {
            $n = CatalogStandard::parseNumber($e['VALUE']);
            $sort = $n === null ? 100000 : (int)min(99999, round($n * 10));
            if ($e['SORT'] !== $sort) {
                $DB->Query("UPDATE b_iblock_property_enum SET SORT = $sort WHERE ID = {$e['ID']}");
                $stats['sort']++;
            }
        }
    }

    // g) лишние пробелы в тексте варианта
    foreach ($enums as $e) {
        $trimmed = trim(preg_replace('/\s+/u', ' ', str_replace(' ', ' ', $e['VALUE'])));
        if ($trimmed !== $e['VALUE']) {
            CatalogMigrate::updateEnum($prop, $e['ID'], ['VALUE' => $trimmed]);
        }
    }
    $enums = CatalogMigrate::enums($pid);

    // f) слаги вместо хэшей / пустых; у числовых — слаг строго по тексту («до 70» → do-70, «220/380» → 220-380)
    if (!in_array($prop['CODE'], $skipSlug, true)) {
        foreach ($enums as $e) {
            $base = CatalogStandard::slug($e['VALUE']);
            if (preg_match('/^' . preg_quote($base, '/') . '-\d+$/', $e['XML_ID'])
                && CatalogMigrate::uniqueSlug($pid, $base, $e['ID']) === $base) {
                // суффикс -2 от слитого дубля больше не нужен
                CatalogMigrate::updateEnum($prop, $e['ID'], ['XML_ID' => $base]);
                $stats['slug']++;
                continue;
            }
            if ($numeric && $e['XML_ID'] !== $base) {
                CatalogMigrate::updateEnum($prop, $e['ID'], ['XML_ID' => CatalogStandard::slug($e['VALUE'])]);
                $stats['slug']++;
                continue;
            }
            if ($e['XML_ID'] === '' || preg_match('/^[0-9a-f]{32}$/', $e['XML_ID'])) {
                CatalogMigrate::updateEnum($prop, $e['ID'], ['XML_ID' => CatalogStandard::slug($e['VALUE'])]);
                $stats['slug']++;
            }
        }
    }
}
echo "d) слито дублей вариантов: {$stats['dedup']}; e) переформатировано: {$stats['format']}, отсортировано: {$stats['sort']}; f) слагов: {$stats['slug']}\n";

// проверка запросом к базе: хэш-слагов у списков каталога не осталось
$left = $DB->Query("SELECT COUNT(*) N FROM b_iblock_property_enum en JOIN b_iblock_property p ON p.ID = en.PROPERTY_ID
    WHERE p.IBLOCK_ID = $iblockId AND p.CODE <> 'TIP_TOVARA' AND en.XML_ID REGEXP '^[0-9a-f]{32}$'")->Fetch();
if ((int)$left['N'] > 0) {
    throw new \RuntimeException("Остались хэш-слаги: {$left['N']}");
}
$dupVoltage = $DB->Query("SELECT COUNT(*) N FROM b_iblock_property_enum en JOIN b_iblock_property p ON p.ID = en.PROPERTY_ID
    WHERE p.IBLOCK_ID = $iblockId AND p.CODE = 'NAPRYAZHENIE_V' AND en.VALUE = '220 / 380'")->Fetch();
if ((int)$dupVoltage['N'] > 0) {
    throw new \RuntimeException('Не слит «220 / 380»');
}

CatalogMigrate::clearCache();

});
return true;
