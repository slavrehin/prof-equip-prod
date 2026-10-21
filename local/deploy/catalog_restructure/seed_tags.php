<?php
/**
 * Наполнение SEO-тегов (инфоблок seotags) для прачечного оборудования, запчастей и текстиля.
 *
 *   php local/deploy/catalog_restructure/seed_tags.php            # отчёт: какие теги будут, сколько товаров
 *   php local/deploy/catalog_restructure/seed_tags.php --apply    # создать/обновить
 *   … --apply --keep-texts   # не перезаписывать H1/Title/Description/SEO-текст, отредактированные в админке
 *
 * На test3: docker compose exec web php /var/www/html/local/deploy/catalog_restructure/seed_tags.php
 *
 * Кандидаты — по спецификации SPEC ниже (раздел → группы подборок). Тег создаётся, только если
 * в нём ≥ 3 товаров и он заметно уже раздела (< 90% товаров) — иначе это дубль раздела или «тонкая»
 * страница. Ключ тега — (раздел, CODE): повторный запуск обновляет, лишние теги из прошлых запусков
 * (которые больше не проходят порог) деактивируются, а не удаляются.
 * Тексты генерируются из данных (число моделей, бренды, загрузка) — уникальны для каждой подборки;
 * при правке в админке запускать с --keep-texts. Ручные тексты из texts.php применяются всегда.
 */

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once __DIR__ . '/lib.php';
\Bitrix\Main\Loader::includeModule('iblock');

$apply = in_array('--apply', $argv, true);
$keepTexts = in_array('--keep-texts', $argv, true);

$catalogId = CatalogMigrate::iblockId();
$tagsIblockId = (int)GetIBlockIDByCode('seotags');
if (!$tagsIblockId) {
    fwrite(STDERR, "Нет инфоблока seotags — сначала local/migrations/run.php\n");
    exit(1);
}

/* ------------------------------------------------------------------ *
 *  Справочники формулировок
 * ------------------------------------------------------------------ */

// Тип предприятия → [чипс, хвост H1, слаг]
const ENTERPRISE = [
    'Прачечная гостиницы' => ['Для гостиниц', 'для гостиниц и отелей', 'dlya-gostinic'],
    'Прачечная больницы' => ['Для больниц', 'для больниц и медучреждений', 'dlya-bolnic'],
    'Промышленная прачечная' => ['Для промышленных прачечных', 'для промышленной прачечной', 'dlya-promyshlennoj-prachechnoj'],
    'Мини-прачечная' => ['Для мини-прачечной', 'для мини-прачечной', 'dlya-mini-prachechnoj'],
    'Прачечная самообслуживания' => ['Для самообслуживания', 'для прачечной самообслуживания', 'dlya-prachechnoj-samoobsluzhivaniya'],
    'Коммерческая прачечная' => ['Для коммерческих прачечных', 'для коммерческой прачечной', 'dlya-kommercheskoj-prachechnoj'],
    'Прачечная на предприятии' => ['Для спецодежды', 'для стирки спецодежды на предприятии', 'dlya-spetsodezhdy'],
    'Химчистка' => ['Для химчистки', 'для химчистки', 'dlya-himchistki'],
    'Аквачистка' => ['Для аквачистки', 'для аквачистки', 'dlya-akvachistki'],
];

// Теги «Тип предприятия» закрыты от индексации (страница есть, но noindex, без sitemap и ссылок в фильтре)
// везде, кроме разделов ENTERPRISE_INDEX_SECTIONS, а там — кроме типов NOINDEX_ENTERPRISE.
// 2026-10-21: индексируются только мини-прачечная, самообслуживание, гостиницы, больницы и химчистка
// в корне прачечного (тексты — texts.php).
const ENTERPRISE_INDEX_SECTIONS = ['prachechnoe-oborudovanie'];
const NOINDEX_ENTERPRISE = ['Коммерческая прачечная', 'Промышленная прачечная', 'Прачечная на предприятии', 'Аквачистка'];

// Тип нагрева → [чипс, прилагательное мн.ч., ср.р., слаг]
const HEATING = [
    'Электрический' => ['Электрические', 'Электрические', 'Электрическое', 'elektricheskie'],
    'Паровой' => ['Паровые', 'Паровые', 'Паровое', 'parovye'],
    'Газовый' => ['Газовые', 'Газовые', 'Газовое', 'gazovye'],
    'Электрический/паровой' => ['Электро/пар', 'Комбинированные (электричество/пар)', 'Комбинированное (электричество/пар)', 'elektro-par'],
];

const COUNTRY_GEN = ['Италия' => 'Италии', 'Германия' => 'Германии', 'Турция' => 'Турции', 'Испания' => 'Испании',
    'Китай' => 'Китая', 'Бельгия' => 'Бельгии', 'Россия' => 'России', 'Франция' => 'Франции', 'Дания' => 'Дании'];

// Назначение (что обрабатывают) → [чипс, хвост H1, слаг]
const PURPOSE = [
    'Рубашки' => ['Для рубашек', 'для рубашек', 'dlya-rubashek'],
    'Верхняя одежда' => ['Для верхней одежды', 'для верхней одежды', 'dlya-verhnej-odezhdy'],
    'Брюки' => ['Для брюк', 'для брюк', 'dlya-bryuk'],
    'Полотенца' => ['Для полотенец', 'для полотенец', 'dlya-polotenets'],
    'Постельное белье' => ['Для постельного белья', 'для постельного белья', 'dlya-postelnogo-belya'],
    'Мелкие изделия' => ['Для мелких изделий', 'для мелких изделий', 'dlya-melkih-izdelij'],
    'Крупные изделия' => ['Для крупных изделий', 'для крупных изделий', 'dlya-krupnyh-izdelij'],
    'МОПы' => ['Для стирки МОПов', 'для стирки МОПов', 'dlya-stirki-mopov'],
];

// Загрузка: подпись → [от, до] (кг). Диапазоны вокруг частотных запросов («стиральная машина 25 кг»).
const LOAD_WASH = ['8 кг' => [7, 9.9], '10 кг' => [10, 10.9], '11 кг' => [11, 12.9], '15 кг' => [13, 16.9], '18 кг' => [17, 19.9],
    '20 кг' => [20, 22.9], '25 кг' => [23, 26.9], '30 кг' => [27, 32.9], '35 кг' => [33, 37.9], '40 кг' => [38, 45.9],
    '50 кг' => [46, 57.9], '60 кг' => [58, 65.9], '70 кг' => [66, 79.9], '90 кг' => [80, 95.9], '100 кг' => [96, 110.9],
    '120 кг' => [111, 130.9], 'от 140 кг' => [131, 1000]];
const LOAD_DRY = ['8 кг' => [6, 9.9], '10 кг' => [10, 10.9], '11 кг' => [11, 12.9], '15 кг' => [13, 16.9], '20 кг' => [17, 22.9],
    '25 кг' => [23, 27.9], '30 кг' => [28, 32.9], '35 кг' => [33, 37.9], '40 кг' => [38, 47.9], '50 кг' => [48, 57.9],
    '60 кг' => [58, 65.9], '100 кг' => [90, 110.9], '120 кг' => [111, 130.9]];
// Порядок загрузок в блоке подборок — по спросу (KeySo: «стиральная машина 25 кг» 575, «50 кг» 504, «11 кг» 162…)
const LOAD_DEMAND = ['25 кг', '50 кг', '11 кг', '20 кг', '10 кг', '18 кг', '30 кг', '15 кг', '100 кг', '40 кг', '60 кг', '8 кг'];
const LOAD_DRYCLEAN = ['до 12 кг' => [0, 12.9], '15–20 кг' => [13, 22.9], 'от 25 кг' => [23, 100]];
// Рабочая ширина вала, мм: подпись → [от, до]
const WIDTH = ['до 1,5 м' => [0, 1599], '2 м' => [1600, 2299], '2,5 м' => [2300, 2899], '3 м' => [2900, 3250],
    '3,3–3,5 м' => [3251, 3600], 'от 4 м' => [3601, 6000]];

/**
 * Спецификация: код раздела => [группы]. Группа:
 *  brand | country | enterprise | heating | voltage | purpose | aqua | load:<WASH|DRY|DRYCLEAN> | width | type
 */
const SPEC = [
    'prachechnoe-oborudovanie' => ['enterprise', 'brand'],
    'stiralnye-mashiny' => ['enterprise', 'load:WASH', 'brand', 'heating', 'voltage', 'aqua', 'country'],
    'vysokoskorostnye-stiralnye-mashiny' => ['purpose', 'load:WASH', 'brand', 'enterprise'],
    'nizko-i-sredneskorostnye-stiralnye-mashiny' => ['load:WASH', 'brand'],
    'barernye-stiralnye-mashiny' => ['load:WASH', 'brand'],
    'sushilnoe-oborudovanie' => ['enterprise', 'load:DRY', 'brand', 'heating', 'aqua', 'country'],
    'sushilnye-mashiny' => ['load:DRY', 'brand', 'heating', 'country'],
    'gladilnoe-oborudovanie' => ['width', 'brand', 'heating'],
    'gladilnye-katki' => ['width', 'brand', 'heating'],
    'gladilnye-kalandry' => ['width', 'brand', 'heating'],
    'professionalnye-skladyvateli' => ['purpose', 'brand'],
    'finishnoe-oborudovanie' => ['brand'],
    'paromanekeny' => ['purpose', 'brand'],
    'gladilnye-stoly' => ['brand', 'boiler'],
    'parogeneratory' => ['brand'],
    'oborudovanie-dlya-himchistki' => ['brand'],
    'mashiny-himchistki' => ['load:DRYCLEAN', 'brand'],
    'pyatnovyvodnye-stoly' => ['brand'],
    'zapasnye-chasti' => ['type', 'brand'],
    'zapasnye-chasti-dlya-prachechnogo-oborudovaniya' => ['type', 'brand'],
    'zapasnye-chasti-dlya-kuhonnogo-oborudovaniya' => ['type', 'brand'],
    'zapasnye-chasti-tolon' => ['type'],
    'zapasnye-chasti-imesa' => ['type'],
    'professionalnyj-tekstil' => ['type', 'brand'],
    'forma-dlya-personala' => ['type'],
];

// Падеж/форма для H1 разделов, у которых имя в единственном числе («оборудование»)
const NEUTER_SECTIONS = ['prachechnoe-oborudovanie', 'sushilnoe-oborudovanie', 'gladilnoe-oborudovanie', 'finishnoe-oborudovanie', 'oborudovanie-dlya-himchistki'];
const UNIFORM_TYPES = ['Брюки', 'Рубашки и блузы', 'Кители', 'Фартуки', 'Жилеты', 'Пиджаки и жакеты', 'Юбки', 'Платья', 'Свитеры и кардиганы', 'Головные уборы', 'Аксессуары для формы'];

/* ------------------------------------------------------------------ */

$enumXml = static function (string $propCode, string $value): ?string {
    $p = CatalogMigrate::property($propCode);
    if (!$p) {
        return null;
    }
    foreach (CatalogMigrate::enums((int)$p['ID']) as $e) {
        if ($e['VALUE'] === $value) {
            return mb_strtolower($e['XML_ID']);
        }
    }
    return null;
};
$fmt = static fn(float $n): string => rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
$lcFirst = static fn(string $s): string => mb_strtolower(mb_substr($s, 0, 1)) . mb_substr($s, 1);

/** Значения свойства у товаров раздела: value => count (для списков) */
$sectionValueCounts = static function (array $section, string $propCode) use ($catalogId): array {
    $DB = CatalogMigrate::db();
    $p = CatalogMigrate::property($propCode);
    if (!$p || $p['PROPERTY_TYPE'] !== 'L') {
        return [];
    }
    $rs = $DB->Query("SELECT en.VALUE V, COUNT(DISTINCT e.ID) N
        FROM b_iblock_section s
        JOIN b_iblock_section_element se ON se.IBLOCK_SECTION_ID = s.ID
        JOIN b_iblock_element e ON e.ID = se.IBLOCK_ELEMENT_ID AND e.ACTIVE = 'Y'
        JOIN b_iblock_element_property v ON v.IBLOCK_ELEMENT_ID = e.ID AND v.IBLOCK_PROPERTY_ID = {$p['ID']}
        JOIN b_iblock_property_enum en ON en.ID = v.VALUE_ENUM
        WHERE s.IBLOCK_ID = $catalogId AND s.ACTIVE = 'Y' AND s.LEFT_MARGIN >= {$section['LEFT_MARGIN']} AND s.RIGHT_MARGIN <= {$section['RIGHT_MARGIN']}
        GROUP BY en.VALUE ORDER BY N DESC");
    $out = [];
    while ($r = $rs->Fetch()) {
        $out[$r['V']] = (int)$r['N'];
    }
    return $out;
};

/** Сведения о товарах подборки для текста: бренды, загрузка, ширина. */
$tagFacts = static function (array $tag) use ($catalogId): array {
    $ids = SeoTags::productIds($tag);
    if (!$ids) {
        return ['brands' => [], 'loads' => [], 'widths' => [], 'types' => []];
    }
    $brands = array_count_values(array_merge(...array_values(CatalogMigrate::values('PROIZVODITEL', $ids) ?: [[]])));
    arsort($brands);
    $types = array_count_values(array_merge(...array_values(CatalogMigrate::values('TIP_TOVARA', $ids) ?: [[]])));
    arsort($types);
    $loads = array_map('floatval', array_merge(...array_values(CatalogMigrate::values('ZAGRUZKA_KG', $ids) ?: [[]])));
    $widths = array_map('floatval', array_merge(...array_values(CatalogMigrate::values('SHIRINA_ZONY_GLAZHENIYA_MM', $ids) ?: [[]])));
    return ['brands' => array_keys($brands), 'loads' => $loads, 'widths' => $widths, 'types' => array_keys($types)];
};

/* ------------------------------------------------------------------ *
 *  Кандидаты
 * ------------------------------------------------------------------ */
$candidates = [];
foreach (SPEC as $sectionCode => $groups) {
    $section = CatalogMigrate::section($sectionCode);
    if (!$section || $section['ACTIVE'] !== 'Y') {
        echo "— раздел $sectionCode не найден/неактивен, пропуск\n";
        continue;
    }
    $uf = $GLOBALS['USER_FIELD_MANAGER']->GetUserFieldValue('IBLOCK_' . $catalogId . '_SECTION', 'UF_H1', (int)$section['ID']);
    $h1 = trim((string)$uf) ?: $section['NAME'];
    $name = $section['NAME'];
    $neuter = in_array($sectionCode, NEUTER_SECTIONS, true);
    $sort = 100;

    $add = static function (string $group, string $chip, string $slug, string $filter, string $tagH1, array $extra = []) use (&$candidates, $section, $sectionCode, &$sort) {
        $candidates[] = [
            'SECTION' => $section, 'SECTION_CODE' => $sectionCode, 'GROUP' => $group, 'NAME' => $chip, 'SLUG' => $slug,
            'FILTER' => SeoTags::normalizePath($filter), 'H1' => $tagH1, 'SORT' => $sort += 10,
            'IN_BLOCK' => $group !== 'Страна', // страны — страницы есть (ссылки из фильтра), в блок не выводим
        ] + $extra;
        if ($group === 'Загрузка' && ($rank = array_search($chip, LOAD_DEMAND, true)) !== false) {
            $candidates[array_key_last($candidates)]['SORT'] = 10 + $rank;
        }
    };

    foreach ($groups as $group) {
        [$g, $arg] = array_pad(explode(':', $group, 2), 2, null);
        switch ($g) {
            case 'brand':
                foreach ($sectionValueCounts($section, 'PROIZVODITEL') as $brand => $n) {
                    if (($xml = $enumXml('PROIZVODITEL', $brand)) === null) {
                        continue;
                    }
                    $add('Производитель', $brand, CatalogStandard::slug($brand), "proizvoditel-is-$xml", "$h1 $brand", ['VALUE' => $brand]);
                }
                break;
            case 'country':
                foreach ($sectionValueCounts($section, 'COUNTRY') as $country => $n) {
                    if (!isset(COUNTRY_GEN[$country]) || ($xml = $enumXml('COUNTRY', $country)) === null) {
                        continue;
                    }
                    $add('Страна', $country, 'iz-' . CatalogStandard::slug(COUNTRY_GEN[$country]), "country-is-$xml", "$name из " . COUNTRY_GEN[$country], ['VALUE' => $country]);
                }
                break;
            case 'enterprise':
                foreach (ENTERPRISE as $value => [$chip, $tail, $slug]) {
                    if (($xml = $enumXml('TIP_PREDPRIYATIYA', $value)) === null) {
                        continue;
                    }
                    $add('Назначение', $chip, $slug, "tip_predpriyatiya-is-$xml", "$name $tail",
                        ['VALUE' => $value, 'NOINDEX' => !in_array($sectionCode, ENTERPRISE_INDEX_SECTIONS, true) || in_array($value, NOINDEX_ENTERPRISE, true)]);
                }
                break;
            case 'heating':
                foreach (HEATING as $value => [$chip, $adjPl, $adjN, $slug]) {
                    if (($xml = $enumXml('TIP_NAGREVA', $value)) === null) {
                        continue;
                    }
                    $adj = $neuter ? $adjN : $adjPl;
                    $add('Тип нагрева', $chip, $slug, "tip_nagreva-is-$xml", $adj . ' ' . $lcFirst($name), ['VALUE' => $value]);
                }
                break;
            case 'voltage':
                foreach (['220' => '220v', '380' => '380v'] as $value => $slug) {
                    if (($xml = $enumXml('NAPRYAZHENIE_V', $value)) === null) {
                        continue;
                    }
                    $add('Особенности', "$value В", $slug, "napryazhenie_v-is-$xml", "$name $value В", ['VALUE' => $value]);
                }
                break;
            case 'purpose':
                foreach (PURPOSE as $value => [$chip, $tail, $slug]) {
                    if (($xml = $enumXml('NAZNACHENIE', $value)) === null) {
                        continue;
                    }
                    $add('Назначение', $chip, $slug, "naznachenie-is-$xml", "$name $tail", ['VALUE' => $value]);
                }
                break;
            case 'aqua':
                $yes = $enumXml('AKVACHISTKA', 'Есть');
                $opt = $enumXml('AKVACHISTKA', 'Опционально');
                $vals = array_filter([$yes, $opt]);
                if ($vals) {
                    $add('Особенности', 'С функцией аквачистки', 's-funktsiej-akvachistki', 'akvachistka-is-' . implode('-or-', $vals), "$name с функцией аквачистки");
                }
                break;
            case 'boiler':
                if ($xml = $enumXml('BOJLER', 'Есть')) {
                    $add('Особенности', 'С бойлером', 's-bojlerom', "bojler-is-$xml", "$name с бойлером");
                }
                break;
            case 'load':
                $bins = ['WASH' => LOAD_WASH, 'DRY' => LOAD_DRY, 'DRYCLEAN' => LOAD_DRYCLEAN][$arg];
                foreach ($bins as $label => [$from, $to]) {
                    $num = preg_replace('/\D+/', '', explode('–', $label)[0]);
                    $slug = str_starts_with($label, 'до ') ? 'do-' . $num . '-kg' : (str_starts_with($label, 'от ') ? 'ot-' . $num . '-kg' : (str_contains($label, '–') ? CatalogStandard::slug($label) : $num . '-kg'));
                    $phrase = (str_starts_with($label, 'до ') || str_starts_with($label, 'от ')) ? "с загрузкой $label" : "на $label";
                    $add('Загрузка', $label, $slug, 'zagruzka_kg-from-' . $fmt($from) . '-to-' . $fmt($to), "$name $phrase", ['RANGE' => [$from, $to]]);
                }
                break;
            case 'width':
                foreach (WIDTH as $label => [$from, $to]) {
                    $slug = 'shirina-' . CatalogStandard::slug(str_replace(',', '-', $label));
                    $phrase = str_starts_with($label, 'до ') || str_starts_with($label, 'от ') ? "с шириной вала $label" : "с шириной вала $label";
                    $add('Размер', 'Ширина ' . $label, $slug, "shirina_zony_glazheniya_mm-from-$from-to-$to", "$name $phrase", ['RANGE' => [$from, $to]]);
                }
                break;
            case 'type':
                foreach ($sectionValueCounts($section, 'TIP_TOVARA') as $type => $n) {
                    if (($xml = $enumXml('TIP_TOVARA', $type)) === null) {
                        continue;
                    }
                    if (str_starts_with($sectionCode, 'zapasnye-chasti')) {
                        $for = match ($sectionCode) {
                            'zapasnye-chasti-dlya-prachechnogo-oborudovaniya' => ' для прачечного оборудования',
                            'zapasnye-chasti-dlya-kuhonnogo-oborudovaniya' => ' для кухонного оборудования',
                            'zapasnye-chasti-tolon' => ' для оборудования TOLON',
                            'zapasnye-chasti-imesa' => ' для оборудования IMESA',
                            default => ' — запчасти для профессионального оборудования',
                        };
                        $tagH1 = $type . $for;
                    } else {
                        $tagH1 = $type . (in_array($type, UNIFORM_TYPES, true) ? ' для персонала' : ' для гостиниц и ресторанов');
                    }
                    $add('Тип', $type, CatalogStandard::slug($type), "tip_tovara-is-$xml", $tagH1, ['VALUE' => $type]);
                }
                break;
        }
    }
}

/* ------------------------------------------------------------------ *
 *  Отбор по числу товаров + тексты
 * ------------------------------------------------------------------ */
$sectionTotals = [];
$accepted = [];
$rejected = [];
foreach ($candidates as $c) {
    $sid = (int)$c['SECTION']['ID'];
    $sectionTotals[$sid] ??= (int)\CIBlockElement::GetList([], ['IBLOCK_ID' => $catalogId, 'ACTIVE' => 'Y', 'SECTION_ID' => $sid, 'INCLUDE_SUBSECTIONS' => 'Y'], []);
    $c['SECTION_ID'] = $sid;
    $c['COUNT'] = SeoTags::countProducts($c);
    $total = $sectionTotals[$sid];
    if ($c['COUNT'] < SeoTags::MIN_COUNT_DEFAULT) {
        $rejected[] = "{$c['SECTION_CODE']}/{$c['SLUG']} ({$c['COUNT']}) — мало товаров";
        continue;
    }
    if ($c['COUNT'] >= 0.9 * $total) {
        $rejected[] = "{$c['SECTION_CODE']}/{$c['SLUG']} ({$c['COUNT']} из $total) — дублирует раздел";
        continue;
    }
    // уникальность слага в разделе
    foreach ($accepted as $a) {
        if ($a['SECTION_CODE'] === $c['SECTION_CODE'] && $a['SLUG'] === $c['SLUG']) {
            $c['SLUG'] .= '-' . CatalogStandard::slug($c['GROUP']);
        }
    }
    $accepted[] = $c;
}

/** Текст подборки из данных. */
$buildTexts = static function (array $c) use ($tagFacts, $fmt, $lcFirst): array {
    $facts = $tagFacts($c);
    $h1 = $c['H1'];
    $n = $c['COUNT'];
    $models = $n . ' ' . CatalogNav::plural($n, ['модель', 'модели', 'моделей']);
    $brands = array_slice($facts['brands'], 0, 5);
    $brandText = $brands ? implode(', ', $brands) : '';
    $loads = array_filter($facts['loads'], static fn($v) => $v > 0);
    $loadText = $loads ? ($fmt(min($loads)) === $fmt(max($loads)) ? 'загрузка ' . $fmt(min($loads)) . ' кг' : 'загрузка от ' . $fmt(min($loads)) . ' до ' . $fmt(max($loads)) . ' кг') : '';
    $widths = array_filter($facts['widths'], static fn($v) => $v > 0);
    $widthText = $widths ? 'рабочая ширина вала от ' . $fmt(min($widths) / 1000) . ' до ' . $fmt(max($widths) / 1000) . ' м' : '';
    $types = array_slice(array_values(array_filter($facts['types'], static fn($t) => $t !== $c['VALUE'] ?? null)), 0, 4);

    $intro = match ($c['GROUP']) {
        'Производитель' => "Подборка оборудования {$c['NAME']} в разделе «{$c['SECTION']['NAME']}»: модели, которые ПРОФЭКВИП поставляет напрямую и обслуживает собственной сервисной службой. Подберём модель под объём белья, тип нагрева и условия помещения, рассчитаем окупаемость и смонтируем «под ключ».",
        'Загрузка' => "Подборка по загрузке барабана — главному параметру при выборе. Ориентир: на каждые 10 номеров гостиницы или 20–25 кг белья в смену нужен барабан около 10–12 кг. Если не уверены в расчёте, инженер ПРОФЭКВИП посчитает производительность прачечной по вашему объекту бесплатно.",
        'Назначение' => "Оборудование, которое подходит " . preg_replace('/^.*? (для .+)$/u', '$1', $h1) . ": подобрано по производительности, типу загрузки и требованиям к обработке белья. Для таких объектов мы проектируем прачечные целиком — от технологической планировки до запуска и обучения персонала.",
        'Тип нагрева' => "Тип нагрева определяет требования к помещению и стоимость цикла: электрический проще в подключении, паровой выгоден при наличии котельной или парогенератора, газовый — самый экономичный в эксплуатации на больших объёмах. Поможем выбрать вариант под ваши коммуникации.",
        'Размер' => "Рабочая ширина вала определяет, какое бельё можно гладить в один проход и с какой скоростью: для гостиничных простыней и пододеяльников обычно берут вал от 2,5–3 м, для салфеток и наволочек достаточно узкого катка.",
        'Особенности' => "Подборка по особенностям исполнения. Все модели — профессиональные, рассчитаны на интенсивную работу и сервисное обслуживание.",
        'Страна' => "Оборудование, произведённое в " . (COUNTRY_GEN[$c['VALUE'] ?? ''] ?? '') . ". Поставляем с гарантией производителя, со склада и под заказ.",
        'Тип' => "Подборка «{$c['NAME']}» — выберите нужную позицию по модели оборудования, артикулу или параметрам в фильтре. Если не нашли позицию — пришлите шильдик или артикул, подберём оригинал или аналог.",
        default => '',
    };
    $dataParts = array_filter([
        "в каталоге $models",
        $brandText !== '' && $c['GROUP'] !== 'Производитель' ? "бренды: $brandText" : '',
        $loadText, $widthText,
        $types && in_array($c['GROUP'], ['Производитель', 'Назначение', 'Страна', 'Тип нагрева'], true) && !str_starts_with($c['SECTION_CODE'], 'zapasnye') ? 'в подборке: ' . mb_strtolower(implode(', ', $types)) : '',
    ]);
    $seo = '<h2>' . htmlspecialcharsbx($h1) . '</h2>'
        . '<p>' . htmlspecialcharsbx($intro) . '</p>'
        . '<p>' . htmlspecialcharsbx(mb_strtoupper(mb_substr(implode('; ', $dataParts), 0, 1)) . mb_substr(implode('; ', $dataParts), 1)) . '.</p>'
        . '<p>ПРОФЭКВИП — комплексное оснащение прачечных и химчисток: подбор и поставка оборудования, проектирование, монтаж, пусконаладка, гарантийный и сервисный ремонт, запчасти и химия. Работаем по всей России. Оставьте заявку — пришлём коммерческое предложение с ценами и сроками.</p>';

    $descBrands = $brandText !== '' && $c['GROUP'] !== 'Производитель' ? ' от ' . implode(', ', array_slice($brands, 0, 3)) : '';
    return [
        'TITLE' => "$h1 — купить по выгодной цене в ПРОФЭКВИП",
        'DESCRIPTION' => "$h1: $models$descBrands" . ($loadText ? ", $loadText" : '') . '. Подбор, поставка, монтаж и сервис по всей России. Цены и КП — ПРОФЭКВИП.',
        'SEO_TEXT' => $seo,
    ];
};

/* ------------------------------------------------------------------ *
 *  Отчёт / запись
 * ------------------------------------------------------------------ */
$bySection = [];
foreach ($accepted as $c) {
    $bySection[$c['SECTION_CODE']][] = "{$c['NAME']} ({$c['COUNT']})";
}
foreach ($bySection as $code => $list) {
    echo "$code: " . implode(', ', $list) . "\n";
}
echo "Итого тегов: " . count($accepted) . ", отброшено кандидатов: " . count($rejected) . "\n";
if (in_array('--verbose', $argv, true)) {
    echo "Отброшены:\n  " . implode("\n  ", $rejected) . "\n";
}
if (!$apply) {
    echo "Отчёт. Для записи: --apply\n";
    exit(0);
}

$groupEnum = [];
$rsE = \CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $tagsIblockId, 'CODE' => 'GROUP']);
while ($e = $rsE->Fetch()) {
    $groupEnum[$e['VALUE']] = (int)$e['ID'];
}
$inBlock = \CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $tagsIblockId, 'CODE' => 'IN_BLOCK'])->Fetch();
$noindex = \CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $tagsIblockId, 'CODE' => 'NOINDEX'])->Fetch();
if (!$noindex) {
    fwrite(STDERR, "Нет свойства NOINDEX у seotags — сначала local/migrations/run.php\n");
    exit(1);
}

// существующие теги: (раздел, CODE) => ID
$existing = [];
$rs = \CIBlockElement::GetList([], ['IBLOCK_ID' => $tagsIblockId], false, false, ['ID', 'CODE', 'ACTIVE', 'PROPERTY_SECTION']);
while ($r = $rs->Fetch()) {
    $existing[(int)$r['PROPERTY_SECTION_VALUE'] . '|' . $r['CODE']] = (int)$r['ID'];
}

$manualTexts = require __DIR__ . '/texts.php';

$created = $updated = 0;
$seen = [];
$el = new \CIBlockElement();
foreach ($accepted as $c) {
    $key = $c['SECTION_ID'] . '|' . $c['SLUG'];
    $seen[$key] = true;
    $props = [
        'SECTION' => $c['SECTION_ID'],
        'FILTER' => $c['FILTER'],
        'GROUP' => $groupEnum[$c['GROUP']] ?? false,
        'IN_BLOCK' => $inBlock && $c['IN_BLOCK'] ? (int)$inBlock['ID'] : false,
        'NOINDEX' => !empty($c['NOINDEX']) ? (int)$noindex['ID'] : false,
        'H1' => $c['H1'],
    ];
    if ($manual = $manualTexts[$c['SECTION_CODE'] . '/' . $c['SLUG']] ?? null) {
        $props['H1'] = $manual['H1'];
        $props['TITLE'] = $manual['TITLE'];
        $props['DESCRIPTION'] = $manual['DESCRIPTION'];
        $props['SEO_TEXT'] = ['VALUE' => ['TEXT' => $manual['SEO_TEXT'], 'TYPE' => 'html']];
    } elseif (!isset($existing[$key]) || !$keepTexts) {
        $texts = $buildTexts($c);
        $props['TITLE'] = $texts['TITLE'];
        $props['DESCRIPTION'] = $texts['DESCRIPTION'];
        $props['SEO_TEXT'] = ['VALUE' => ['TEXT' => $texts['SEO_TEXT'], 'TYPE' => 'html']];
    } else {
        unset($props['H1']);
    }
    $fields = ['IBLOCK_ID' => $tagsIblockId, 'NAME' => $c['NAME'], 'CODE' => $c['SLUG'], 'SORT' => $c['SORT'], 'ACTIVE' => 'Y'];
    if (isset($existing[$key])) {
        $id = $existing[$key];
        if (!$el->Update($id, $fields)) {
            throw new \RuntimeException("Тег {$key}: " . $el->LAST_ERROR);
        }
        \CIBlockElement::SetPropertyValuesEx($id, $tagsIblockId, $props);
        $updated++;
    } else {
        $id = (int)$el->Add($fields + ['PROPERTY_VALUES' => $props]);
        if (!$id) {
            throw new \RuntimeException("Тег {$key}: " . $el->LAST_ERROR);
        }
        $created++;
    }
}
// теги прошлых запусков, не прошедшие порог сейчас, — деактивировать (ручные теги без слага из SPEC не трогаем
// только если их раздел не входит в SPEC)
$deactivated = 0;
$specSectionIds = array_unique(array_map(static fn($c) => $c['SECTION_ID'], $candidates));
$candidateKeys = [];
foreach ($candidates as $c) {
    $candidateKeys[(int)$c['SECTION']['ID'] . '|' . $c['SLUG']] = true;
}
foreach ($existing as $key => $id) {
    if (!isset($seen[$key]) && isset($candidateKeys[$key])) {
        $el->Update($id, ['ACTIVE' => 'N']);
        $deactivated++;
    }
}
SeoTags::clearCache();
CatalogMigrate::clearCache();

$check = CatalogMigrate::db()->Query("SELECT COUNT(*) N FROM b_iblock_element WHERE IBLOCK_ID = $tagsIblockId AND ACTIVE = 'Y'")->Fetch();
echo "Создано $created, обновлено $updated, деактивировано $deactivated; активных тегов в базе: {$check['N']}\n";
