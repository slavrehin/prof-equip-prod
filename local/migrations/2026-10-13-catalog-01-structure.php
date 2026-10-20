<?php
/**
 * Реструктуризация «Прачечного оборудования» (CatalogStandard, раздел 1):
 *   1) снимает избыточные привязки товаров к родительским разделам (товар в «Финишном» И в
 *      «Пятновыводных столах» — после переноса раздела он остался бы в обоих);
 *   2) переносит товары из «свалок» в правильные разделы (PRODUCT_MOVES);
 *   3) создаёт разделы «Оборудование для химчистки», «Сортировка белья»; переносит разделы
 *      (URL разделов плоские — /product-category/<код>/ — и не меняются);
 *   4) переименования и H1 разделов (SEO-поле «Заголовок раздела»);
 *   5) расформировывает дубли-разделы (деактивация; 301 — в .htaccess, см. README).
 * Идемпотентна: всё по CODE, повторный запуск ничего не меняет.
 */

call_user_func(static function () {

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/deploy/catalog_restructure/lib.php';
\CModule::IncludeModule('iblock');

$iblockId = CatalogMigrate::iblockId();
$root = CatalogMigrate::section('prachechnoe-oborudovanie');
if (!$root) {
    throw new \RuntimeException('Нет раздела prachechnoe-oborudovanie');
}

// Текущее дерево прачечного оборудования: ID => [CODE, LEFT, RIGHT]
$tree = [];
$rs = \CIBlockSection::GetList(['LEFT_MARGIN' => 'ASC'], [
    'IBLOCK_ID' => $iblockId, '>=LEFT_MARGIN' => (int)$root['LEFT_MARGIN'], '<=RIGHT_MARGIN' => (int)$root['RIGHT_MARGIN'],
], false, ['ID', 'CODE', 'LEFT_MARGIN', 'RIGHT_MARGIN']);
while ($s = $rs->Fetch()) {
    $tree[(int)$s['ID']] = ['CODE' => $s['CODE'], 'L' => (int)$s['LEFT_MARGIN'], 'R' => (int)$s['RIGHT_MARGIN']];
}
$codeToId = array_flip(array_map(static fn($s) => $s['CODE'], $tree));
$showcase = array_flip(CatalogStandard::SHOWCASE_SECTIONS);

$setSections = static function (int $elementId, array $sectionIds, ?int $mainId) {
    $sectionIds = array_values(array_unique(array_map('intval', $sectionIds)));
    \CIBlockElement::SetElementSection($elementId, $sectionIds, false, 0, $mainId);
    // основной раздел — SQL-ом (CIBlockElement::Update на этом инфоблоке ломает IBLOCK_SECTION_ID, см. CLAUDE.md)
    if ($mainId) {
        CatalogMigrate::db()->Query('UPDATE b_iblock_element SET IBLOCK_SECTION_ID = ' . (int)$mainId . ' WHERE ID = ' . $elementId);
    }
};

// ---- 1) избыточные привязки к предкам (по текущему дереву) ----
$unbound = 0;
// только товары каталога: к разделам исторически привязан и элемент направления «Профессиональные
// прачечные» (инфоблок направлений) — его не трогаем
$rs = CatalogMigrate::db()->Query('SELECT se.IBLOCK_ELEMENT_ID E, se.IBLOCK_SECTION_ID S FROM b_iblock_section_element se
    JOIN b_iblock_element e ON e.ID = se.IBLOCK_ELEMENT_ID AND e.IBLOCK_ID = ' . $iblockId . '
    WHERE se.IBLOCK_SECTION_ID IN (' . implode(',', array_keys($tree)) . ')');
$bindings = [];
while ($r = $rs->Fetch()) {
    $bindings[(int)$r['E']][] = (int)$r['S'];
}
foreach ($bindings as $elementId => $sectionIds) {
    $drop = [];
    foreach ($sectionIds as $sid) {
        if (isset($showcase[$tree[$sid]['CODE']])) {
            continue;
        }
        foreach ($sectionIds as $other) {
            if ($other !== $sid && !isset($showcase[$tree[$other]['CODE']])
                && $tree[$other]['L'] > $tree[$sid]['L'] && $tree[$other]['R'] < $tree[$sid]['R']) {
                $drop[$sid] = true; // есть привязка к потомку — привязка к предку лишняя
                break;
            }
        }
    }
    if (!$drop) {
        continue;
    }
    $all = array_keys(CatalogMigrate::elementSections($elementId));
    $keep = array_values(array_diff($all, array_keys($drop)));
    $el = \CIBlockElement::GetList([], ['ID' => $elementId], false, false, ['ID', 'IBLOCK_SECTION_ID'])->Fetch();
    $main = in_array((int)$el['IBLOCK_SECTION_ID'], $keep, true) ? (int)$el['IBLOCK_SECTION_ID'] : null;
    if ($main === null) {
        foreach ($keep as $k) {
            if (!isset($tree[$k]) || !isset($showcase[$tree[$k]['CODE']])) {
                $main = $k;
                break;
            }
        }
    }
    $setSections($elementId, $keep, $main);
    $unbound++;
}
echo "1) Сняты избыточные привязки к родителям у товаров: $unbound\n";

// ---- 3a) новые разделы (до переноса товаров — им нужны целевые разделы) ----
foreach (CatalogStandard::NEW_SECTIONS as $code => [$name, $parentCode, $sort]) {
    if (CatalogMigrate::section($code)) {
        continue;
    }
    $parent = CatalogMigrate::section($parentCode);
    if (!$parent) {
        throw new \RuntimeException("Нет родителя $parentCode для $code");
    }
    $id = (int)(new \CIBlockSection())->Add([
        'IBLOCK_ID' => $iblockId, 'IBLOCK_SECTION_ID' => (int)$parent['ID'], 'CODE' => $code, 'NAME' => $name,
        'SORT' => $sort, 'ACTIVE' => 'Y',
        'IPROPERTY_TEMPLATES' => [
            'SECTION_META_TITLE' => $name . ' купить в ПРОФЭКВИП',
            'SECTION_META_DESCRIPTION' => $name . ' для прачечных и химчисток: подбор, поставка, монтаж и сервис. Каталог с ценами — ПРОФЭКВИП.',
        ],
    ]);
    if (!$id) {
        throw new \RuntimeException("Не создан раздел $code");
    }
    echo "3a) Создан раздел $code (ID=$id)\n";
}

// ---- 2) перенос товаров ----
$moved = 0;
$missing = [];
foreach (CatalogStandard::PRODUCT_MOVES as $productCode => $targetCodes) {
    $el = CatalogMigrate::elementByCode($productCode);
    if (!$el) {
        $missing[] = $productCode;
        continue;
    }
    $targetIds = [];
    foreach ($targetCodes as $tc) {
        $t = CatalogMigrate::section($tc);
        if (!$t) {
            throw new \RuntimeException("Нет целевого раздела $tc");
        }
        $targetIds[] = (int)$t['ID'];
    }
    $current = CatalogMigrate::elementSections((int)$el['ID']);
    $keep = [];
    foreach ($current as $sid => $scode) {
        // привязки вне прачечного оборудования и витрины «в наличии» сохраняем
        if (!isset($tree[$sid]) || isset($showcase[$scode])) {
            $keep[] = $sid;
        }
    }
    $new = array_values(array_unique(array_merge($targetIds, $keep)));
    sort($new);
    $cur = array_keys($current);
    sort($cur);
    if ($new === $cur && (int)$el['IBLOCK_SECTION_ID'] === $targetIds[0]) {
        continue;
    }
    $setSections((int)$el['ID'], $new, $targetIds[0]);
    $moved++;
}
echo "2) Перенесено товаров: $moved" . ($missing ? '; нет на этом окружении: ' . implode(', ', $missing) : '') . "\n";

// ---- 3b) перенос разделов ----
foreach (CatalogStandard::MOVE_SECTIONS as $code => $parentCode) {
    $s = CatalogMigrate::section($code);
    $p = CatalogMigrate::section($parentCode);
    if (!$s || !$p) {
        throw new \RuntimeException("Перенос $code → $parentCode: раздел не найден");
    }
    if ((int)$s['IBLOCK_SECTION_ID'] === (int)$p['ID']) {
        continue;
    }
    $ok = (new \CIBlockSection())->Update((int)$s['ID'], ['IBLOCK_SECTION_ID' => (int)$p['ID']]);
    if (!$ok) {
        throw new \RuntimeException("Не перенесён раздел $code");
    }
    echo "3b) Раздел $code → $parentCode\n";
}

// ---- 3c) порядок разделов ----
foreach (CatalogStandard::SECTION_SORT as $code => $sort) {
    $s = CatalogMigrate::section($code);
    if ($s && (int)$s['SORT'] !== $sort) {
        (new \CIBlockSection())->Update((int)$s['ID'], ['SORT' => $sort]);
        echo "3c) SORT $code = $sort\n";
    }
}

// ---- 4) переименования и H1 ----
foreach (CatalogStandard::RENAME_SECTIONS as $code => $name) {
    $s = CatalogMigrate::section($code);
    if ($s && $s['NAME'] !== $name) {
        (new \CIBlockSection())->Update((int)$s['ID'], ['NAME' => $name]);
        echo "4) Переименован $code → «{$name}»\n";
    }
}
// H1 — в поле раздела UF_H1, а не в SEO-шаблоне «Заголовок раздела» (SECTION_PAGE_TITLE): тот наследуется
// подразделами и подставляется Битриксом в хлебные крошки — у «Барьерных» в цепочке стало бы два
// «Промышленных стиральных машины». section.php берёт H1 из UF_H1, крошки остаются по NAME.
$entityId = 'IBLOCK_' . $iblockId . '_SECTION';
if (!\CUserTypeEntity::GetList([], ['ENTITY_ID' => $entityId, 'FIELD_NAME' => 'UF_H1'])->Fetch()) {
    $ufId = (int)(new \CUserTypeEntity())->Add([
        'ENTITY_ID' => $entityId, 'FIELD_NAME' => 'UF_H1', 'USER_TYPE_ID' => 'string', 'SORT' => 50, 'MULTIPLE' => 'N',
        'MANDATORY' => 'N', 'SHOW_FILTER' => 'N', 'SHOW_IN_LIST' => 'Y', 'EDIT_IN_LIST' => 'Y', 'IS_SEARCHABLE' => 'N',
        'SETTINGS' => ['SIZE' => 80],
        'EDIT_FORM_LABEL' => ['ru' => 'H1 страницы раздела (если пусто — название раздела)'],
        'LIST_COLUMN_LABEL' => ['ru' => 'H1'],
    ]);
    if (!$ufId) {
        throw new \RuntimeException('Не создано поле UF_H1');
    }
    echo "4) Создано поле раздела UF_H1\n";
}
foreach (CatalogStandard::SECTION_H1 as $code => $h1) {
    $s = CatalogMigrate::section($code);
    if (!$s) {
        continue;
    }
    // откат прежнего варианта (H1 в SECTION_PAGE_TITLE), если он успел записаться
    $own = (new \Bitrix\Iblock\InheritedProperty\SectionTemplates($iblockId, (int)$s['ID']))->findTemplates();
    if (($own['SECTION_PAGE_TITLE']['INHERITED'] ?? 'Y') === 'N' && $own['SECTION_PAGE_TITLE']['TEMPLATE'] === $h1) {
        (new \CIBlockSection())->Update((int)$s['ID'], ['IPROPERTY_TEMPLATES' => ['SECTION_PAGE_TITLE' => '']]);
    }
    $uf = $GLOBALS['USER_FIELD_MANAGER']->GetUserFieldValue($entityId, 'UF_H1', (int)$s['ID']);
    if ($uf === $h1) {
        continue;
    }
    $GLOBALS['USER_FIELD_MANAGER']->Update($entityId, (int)$s['ID'], ['UF_H1' => $h1]);
    echo "4) H1 $code: «{$h1}»\n";
}

// ---- 5) расформирование дублей ----
foreach (CatalogStandard::DISSOLVE_SECTIONS as $code => $redirect) {
    $s = CatalogMigrate::section($code);
    if (!$s || $s['ACTIVE'] === 'N') {
        continue;
    }
    // товары, не описанные в PRODUCT_MOVES (например, добавленные только на проде), — в раздел-цель редиректа
    $targetCode = trim(str_replace('/product-category/', '', $redirect), '/');
    $target = CatalogMigrate::section($targetCode);
    $rest = CatalogMigrate::db()->Query('SELECT IBLOCK_ELEMENT_ID E FROM b_iblock_section_element WHERE IBLOCK_SECTION_ID = ' . (int)$s['ID']);
    while ($r = $rest->Fetch()) {
        $eid = (int)$r['E'];
        $secs = array_keys(CatalogMigrate::elementSections($eid));
        $secs = array_values(array_diff($secs, [(int)$s['ID']]));
        $secs[] = (int)$target['ID'];
        $setSections($eid, $secs, (int)$target['ID']);
        echo "5) Товар $eid из $code → $targetCode\n";
    }
    (new \CIBlockSection())->Update((int)$s['ID'], ['ACTIVE' => 'N']);
    echo "5) Раздел $code деактивирован (301 → $redirect)\n";
}

// Проверка запросом к базе: в расформированных разделах пусто, перенесённые разделы на месте
foreach (array_keys(CatalogStandard::DISSOLVE_SECTIONS) as $code) {
    $s = CatalogMigrate::section($code);
    if ($s && CatalogMigrate::db()->Query('SELECT 1 FROM b_iblock_section_element WHERE IBLOCK_SECTION_ID = ' . (int)$s['ID'] . ' LIMIT 1')->Fetch()) {
        throw new \RuntimeException("В расформированном разделе $code остались товары");
    }
}
foreach (CatalogStandard::MOVE_SECTIONS as $code => $parentCode) {
    $s = CatalogMigrate::section($code);
    $p = CatalogMigrate::section($parentCode);
    if ((int)$s['IBLOCK_SECTION_ID'] !== (int)$p['ID']) {
        throw new \RuntimeException("Раздел $code не под $parentCode");
    }
}

CatalogMigrate::clearCache();
echo "Структура прачечного оборудования обновлена\n";

});
return true;
