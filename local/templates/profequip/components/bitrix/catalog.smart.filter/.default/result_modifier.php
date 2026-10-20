<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */

// Код раздела из адреса (/product-category/<код>/…) — для набора фильтров и ссылок на теги.
$filterSectionCode = null;
if (preg_match('#^/product-category/([^/]+)/#', (string)($_SERVER['REQUEST_URI'] ?? ''), $filterSectionMatch)) {
    $filterSectionCode = $filterSectionMatch[1];
}

// Набор и порядок свойств фильтра по разделу (CatalogStandard::FILTERS, с учётом родителей).
// Разделы без настройки — как раньше: всё, кроме HIDE_PROPERTY_CODES.
$configuredCodes = ($filterSectionCode !== null && empty($arParams['HIDE_PROPERTY_CODES']) && class_exists('CatalogStandard'))
    ? CatalogStandard::filterCodes($filterSectionCode)
    : null;

if ($configuredCodes !== null) {
    $order = array_flip($configuredCodes);
    $kept = [];
    foreach ($arResult['ITEMS'] as $key => $arItem) {
        if (isset($arItem['PRICE'])) {
            $kept[$key] = $arItem;
            continue;
        }
        if (isset($arItem['CODE'], $order[$arItem['CODE']])) {
            $kept[$key] = $arItem;
        }
    }
    uasort($kept, static fn($a, $b) => ($order[$a['CODE'] ?? ''] ?? -1) <=> ($order[$b['CODE'] ?? ''] ?? -1));
    $arResult['ITEMS'] = $kept;
} else {
    // Свойства, которые не показываем в этом фильтре (по CODE). По умолчанию — сквозной «Тип» товара
    // (TIP_TOVARA): на страницах брендов фильтр передаёт свой HIDE_PROPERTY_CODES.
    $hiddenCodes = $arParams['HIDE_PROPERTY_CODES'] ?? ['TIP_TOVARA'];
    if (is_array($hiddenCodes) && $hiddenCodes) {
        foreach ($arResult['ITEMS'] as $key => $arItem) {
            if (isset($arItem['CODE']) && in_array($arItem['CODE'], $hiddenCodes, true)) {
                unset($arResult['ITEMS'][$key]);
            }
        }
    }
}

// Ползунок без разброса значений (числовое свойство, которого у товаров здесь нет или одно значение) — убрать
// везде, в т.ч. в разделах без настройки, на брендах и в поиске (иначе там висят «Загрузка», «G-фактор»).
foreach ($arResult['ITEMS'] as $key => $arItem) {
    if (!isset($arItem['PRICE']) && in_array($arItem['DISPLAY_TYPE'] ?? '', ['A', 'B'], true)
        && (!isset($arItem['VALUES']['MIN']['VALUE'], $arItem['VALUES']['MAX']['VALUE'])
            || (float)$arItem['VALUES']['MAX']['VALUE'] <= (float)$arItem['VALUES']['MIN']['VALUE'])) {
        unset($arResult['ITEMS'][$key]);
    }
}

// Переименование свойств только в этом фильтре: CODE => подпись. На страницах
// брендов так отличаем узкие «Тип» разделов от сквозного «Тип» товара.
$renamedCodes = $arParams['RENAME_PROPERTY_CODES'] ?? [];
if (is_array($renamedCodes) && $renamedCodes) {
    foreach ($arResult['ITEMS'] as $key => $arItem) {
        if (isset($arItem['CODE'], $renamedCodes[$arItem['CODE']])) {
            $arResult['ITEMS'][$key]['NAME'] = $renamedCodes[$arItem['CODE']];
        }
    }
}

if ($configuredCodes !== null) {
    $expandedLeft = class_exists('CatalogStandard') ? CatalogStandard::FILTER_EXPANDED_COUNT : 4;
    foreach ($arResult['ITEMS'] as $key => $arItem) {
        if (isset($arItem['PRICE'])) {
            continue;
        }
        $isRange = in_array($arItem['DISPLAY_TYPE'] ?? '', ['A', 'B'], true);
        if ($isRange) {
            $hasChecked = (string)($arItem['VALUES']['MIN']['HTML_VALUE'] ?? '') !== '' || (string)($arItem['VALUES']['MAX']['HTML_VALUE'] ?? '') !== '';
        } else {
            // одно значение в списке — фильтровать нечем (если оно не выбрано)
            $values = $arItem['VALUES'] ?? [];
            $hasChecked = (bool)array_filter($values, static fn($v) => !empty($v['CHECKED']));
            if (count($values) < 2 && !$hasChecked) {
                unset($arResult['ITEMS'][$key]);
                continue;
            }
            // числовые списки — по возрастанию числа (SORT варианта = число×10, см. миграцию catalog-03)
            uasort($values, static function ($a, $b) {
                $na = is_numeric(str_replace(',', '.', $a['VALUE'] ?? '')) ? (float)str_replace(',', '.', $a['VALUE']) : null;
                $nb = is_numeric(str_replace(',', '.', $b['VALUE'] ?? '')) ? (float)str_replace(',', '.', $b['VALUE']) : null;
                if ($na !== null && $nb !== null) {
                    return $na <=> $nb;
                }
                return 0;
            });
            $arResult['ITEMS'][$key]['VALUES'] = $values;
        }
        // первые N блоков раскрыты, остальные свернуты (кроме тех, где что-то выбрано)
        $arResult['ITEMS'][$key]['DISPLAY_EXPANDED'] = ($expandedLeft > 0 || $hasChecked) ? 'Y' : 'N';
        $expandedLeft--;
    }
}

// SEO-ссылки значений фильтра: есть тег-подборка с этим значением — ссылка на тег; «Тип» с названием
// подраздела — ссылка на подраздел. Шаблон выводит <a href> (бот доходит до посадочной без JS),
// для остальных значений — прежняя логика по SEO-правилам (template.php).
if ($filterSectionCode !== null) {
    $childSections = [];
    $sectionRow = \CIBlockSection::GetList([], ['IBLOCK_ID' => (int)$arParams['IBLOCK_ID'], '=CODE' => $filterSectionCode], false, ['ID'])->Fetch();
    if ($sectionRow) {
        $rsChildren = \CIBlockSection::GetList([], ['IBLOCK_ID' => (int)$arParams['IBLOCK_ID'], 'SECTION_ID' => (int)$sectionRow['ID'], 'ACTIVE' => 'Y', 'GLOBAL_ACTIVE' => 'Y'], false, ['NAME', 'CODE']);
        while ($child = $rsChildren->Fetch()) {
            $childSections[mb_strtolower(trim($child['NAME']))] = '/product-category/' . $child['CODE'] . '/';
        }
    }
    foreach ($arResult['ITEMS'] as $key => $arItem) {
        if (empty($arItem['CODE']) || empty($arItem['VALUES']) || in_array($arItem['DISPLAY_TYPE'] ?? '', ['A', 'B'], true)) {
            continue;
        }
        foreach ($arItem['VALUES'] as $valKey => $ar) {
            $href = null;
            if (class_exists('SeoTags') && !empty($ar['URL_ID']) && ($tag = SeoTags::forFilterValue($filterSectionCode, $arItem['CODE'], $ar['URL_ID']))) {
                // закрытый от индексации тег — без ссылки для бота (фильтр всё равно приведёт на страницу тега)
                $href = empty($tag['NOINDEX']) ? SeoTags::url($tag) : null;
            } elseif ($arItem['CODE'] === 'TIP_TOVARA' && isset($childSections[mb_strtolower(trim($ar['VALUE']))])) {
                $href = $childSections[mb_strtolower(trim($ar['VALUE']))];
            }
            if ($href !== null) {
                $arResult['ITEMS'][$key]['VALUES'][$valKey]['SEO_HREF'] = $href;
            }
        }
    }
}

// Выбранная комбинация совпадает с SEO-тегом — в адресную строку сразу чистый адрес тега
// (/product-category/stiralnye-mashiny/imesa/ вместо /f/proizvoditel-is-imesa/, который всё равно 301 на тег).
if ($filterSectionCode !== null && class_exists('SeoTags') && !empty($arResult['FILTER_URL'])
    && preg_match('#/f/(.+?)/?$#', (string)$arResult['FILTER_URL'], $filterPathMatch)
    && ($filterTag = SeoTags::matchFilterPath($filterSectionCode, $filterPathMatch[1]))) {
    $arResult['FILTER_URL'] = SeoTags::url($filterTag);
}
