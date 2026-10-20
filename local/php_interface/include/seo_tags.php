<?php
/**
 * SEO-теги каталога — посадочные подборки /product-category/<раздел>/<тег>/ (инфоблок seotags).
 *
 *  - Маршрут local/routes/web.php: /product-category/<раздел>/<тег>/ → если тег есть, подставляет
 *    $_REQUEST['SMART_FILTER_PATH'] = FILTER тега и $_REQUEST['SEO_TAG_ID'] и отдаёт листинг раздела
 *    (catalog/index.php + section.php): умный фильтр сам отфильтрует товары и отметит значения в сайдбаре.
 *  - section.php: H1/Title/Description/SEO-текст тега, index/follow, canonical на тег; адрес фильтра
 *    /f/…/, совпадающий с фильтром тега, — 301 на тег (без дублей).
 *  - Блок «Популярные подборки» над листингом (catalog/catalog/include/catalog_top.php) и ссылки в
 *    фильтре берут теги отсюда.
 *  - Тег с числом товаров < MIN_COUNT (по умолчанию 3) — noindex и не показывается в блоке
 *    (защита от «тонких» страниц при изменении ассортимента).
 *  - Тег с NOINDEX — страница есть, но noindex, без sitemap и без ссылки <a href> в фильтре.
 */

/** Мелочи для навигации каталога (блок над листингом). */
class CatalogNav
{
    /**
     * Сколько плиток подкатегорий ставить в ряд: влезают в один ряд (≤ $max) — все в ряд; иначе число
     * колонок из [$min..$max] с наименьшим числом пустых мест в последнем ряду (при равенстве — больше
     * колонок, меньше рядов): 7–8 → 4, 9–10 → 5, 11–12 → 6.
     */
    public static function columns(int $count, int $min, int $max): int
    {
        if ($count <= $max) {
            return max(1, $count);
        }
        $best = $max;
        $bestWaste = PHP_INT_MAX;
        for ($cols = $max; $cols >= $min; $cols--) {
            $waste = (int)ceil($count / $cols) * $cols - $count;
            if ($waste < $bestWaste) {
                $best = $cols;
                $bestWaste = $waste;
            }
        }
        return $best;
    }

    /** «товар/товара/товаров» по числу. */
    public static function plural(int $n, array $forms = ['товар', 'товара', 'товаров']): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return $forms[2];
        }
        if ($n1 > 1 && $n1 < 5) {
            return $forms[1];
        }
        return $n1 === 1 ? $forms[0] : $forms[2];
    }
}

class SeoTags
{
    public const IBLOCK_CODE = 'seotags';
    public const MIN_COUNT_DEFAULT = 3;
    private const CACHE_TIME = 3600;

    /** Порядок групп в блоке подборок. */
    public const GROUP_ORDER = ['Тип' => 10, 'Назначение' => 20, 'Производитель' => 30, 'Загрузка' => 40,
        'Тип нагрева' => 50, 'Размер' => 60, 'Особенности' => 70, 'Страна' => 80];

    private static ?array $all = null;

    /** Все активные теги (кэш, сбрасывается тегом инфоблока при правке в админке). */
    public static function all(): array
    {
        if (self::$all !== null) {
            return self::$all;
        }
        \Bitrix\Main\Loader::includeModule('iblock');
        $iblockId = (int)GetIBlockIDByCode(self::IBLOCK_CODE);
        if (!$iblockId) {
            return self::$all = [];
        }
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        if ($cache->initCache(self::CACHE_TIME, 'seotags_all_v2', '/profequip/seotags')) {
            return self::$all = $cache->getVars();
        }
        $cache->startDataCache();
        $GLOBALS['CACHE_MANAGER']->StartTagCache('/profequip/seotags');
        $GLOBALS['CACHE_MANAGER']->RegisterTag('iblock_id_' . $iblockId);
        $GLOBALS['CACHE_MANAGER']->RegisterTag('iblock_id_' . (int)GetIBlockIDByCode('catalog'));

        $sectionCodes = [];
        $rsS = \CIBlockSection::GetList([], ['IBLOCK_ID' => (int)GetIBlockIDByCode('catalog')], false, ['ID', 'CODE']);
        while ($s = $rsS->Fetch()) {
            $sectionCodes[(int)$s['ID']] = $s['CODE'];
        }

        $tags = [];
        $rs = \CIBlockElement::GetList(['SORT' => 'ASC', 'ID' => 'ASC'], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'], false, false,
            ['ID', 'NAME', 'CODE', 'SORT', 'PROPERTY_SECTION', 'PROPERTY_FILTER', 'PROPERTY_LINK', 'PROPERTY_GROUP', 'PROPERTY_IN_BLOCK',
             'PROPERTY_H1', 'PROPERTY_TITLE', 'PROPERTY_DESCRIPTION', 'PROPERTY_SEO_TEXT', 'PROPERTY_MIN_COUNT', 'PROPERTY_NOINDEX']);
        while ($r = $rs->Fetch()) {
            $sectionId = (int)$r['PROPERTY_SECTION_VALUE'];
            if (!isset($sectionCodes[$sectionId])) {
                continue;
            }
            $seoText = $r['PROPERTY_SEO_TEXT_VALUE'];
            if (is_array($seoText)) {
                $seoText = $seoText['TEXT'] ?? '';
            }
            $group = (string)$r['PROPERTY_GROUP_VALUE'];
            $tags[] = [
                'ID' => (int)$r['ID'],
                'NAME' => (string)$r['NAME'],
                'SLUG' => (string)$r['CODE'],
                'SORT' => (int)$r['SORT'],
                'SECTION_ID' => $sectionId,
                'SECTION_CODE' => $sectionCodes[$sectionId],
                'FILTER' => self::normalizePath((string)$r['PROPERTY_FILTER_VALUE']),
                'LINK' => trim((string)$r['PROPERTY_LINK_VALUE']),
                'GROUP' => $group,
                'GROUP_SORT' => self::GROUP_ORDER[$group] ?? 90,
                'IN_BLOCK' => (string)$r['PROPERTY_IN_BLOCK_VALUE'] === 'Y',
                'H1' => (string)$r['PROPERTY_H1_VALUE'],
                'TITLE' => (string)$r['PROPERTY_TITLE_VALUE'],
                'DESCRIPTION' => (string)$r['PROPERTY_DESCRIPTION_VALUE'],
                'SEO_TEXT' => html_entity_decode((string)$seoText, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'MIN_COUNT' => (int)$r['PROPERTY_MIN_COUNT_VALUE'] ?: self::MIN_COUNT_DEFAULT,
                'NOINDEX' => (string)$r['PROPERTY_NOINDEX_VALUE'] === 'Y',
            ];
        }
        foreach ($tags as &$tag) {
            $tag['COUNT'] = $tag['LINK'] === '' ? self::countProducts($tag) : null;
        }
        unset($tag);

        $GLOBALS['CACHE_MANAGER']->EndTagCache();
        $cache->endDataCache($tags);
        return self::$all = $tags;
    }

    public static function find(string $sectionCode, string $slug): ?array
    {
        foreach (self::all() as $tag) {
            if ($tag['SECTION_CODE'] === $sectionCode && $tag['SLUG'] === $slug && $tag['LINK'] === '') {
                return $tag;
            }
        }
        return null;
    }

    public static function byId(int $id): ?array
    {
        foreach (self::all() as $tag) {
            if ($tag['ID'] === $id) {
                return $tag;
            }
        }
        return null;
    }

    /** Теги раздела для блока подборок: по группам и SORT, без «тонких». */
    public static function forSection(string $sectionCode, bool $onlyBlock = true): array
    {
        $out = [];
        foreach (self::all() as $tag) {
            if ($tag['SECTION_CODE'] !== $sectionCode || ($onlyBlock && !$tag['IN_BLOCK'])) {
                continue;
            }
            if ($tag['COUNT'] !== null && $tag['COUNT'] < $tag['MIN_COUNT']) {
                continue;
            }
            $out[] = $tag;
        }
        usort($out, static fn($a, $b) => [$a['GROUP_SORT'], $a['SORT'], $a['ID']] <=> [$b['GROUP_SORT'], $b['SORT'], $b['ID']]);
        return $out;
    }

    public static function url(array $tag): string
    {
        return $tag['LINK'] !== '' ? $tag['LINK'] : '/product-category/' . $tag['SECTION_CODE'] . '/' . $tag['SLUG'] . '/';
    }

    public static function isIndexable(array $tag): bool
    {
        if (!empty($tag['NOINDEX'])) {
            return false;
        }
        return $tag['COUNT'] === null || $tag['COUNT'] >= $tag['MIN_COUNT'];
    }

    /** Тег раздела, чей фильтр совпадает с путём /f/…/ (порядок сегментов и значений не важен). */
    public static function matchFilterPath(string $sectionCode, string $path): ?array
    {
        $norm = self::normalizePath($path);
        if ($norm === '') {
            return null;
        }
        foreach (self::all() as $tag) {
            if ($tag['SECTION_CODE'] === $sectionCode && $tag['LINK'] === '' && $tag['FILTER'] === $norm) {
                return $tag;
            }
        }
        return null;
    }

    /** Тег раздела с диапазоном по числовому свойству, в который попадает $value (старые /f/zagruzka_kg-is-25/). */
    public static function forNumericValue(string $sectionCode, string $propertyCode, float $value): ?array
    {
        $prefix = mb_strtolower($propertyCode) . '-from-';
        foreach (self::all() as $tag) {
            if ($tag['SECTION_CODE'] !== $sectionCode || $tag['LINK'] !== '' || !str_starts_with($tag['FILTER'], $prefix)
                || str_contains($tag['FILTER'], '/')) {
                continue;
            }
            if (preg_match('/-from-([\d.]+)-to-([\d.]+)$/', $tag['FILTER'], $m) && $value >= (float)$m[1] && $value <= (float)$m[2]) {
                return $tag;
            }
        }
        return null;
    }

    /** Тег раздела по одному значению фильтра (для ссылок в фильтре): code + xml_id. */
    public static function forFilterValue(string $sectionCode, string $propertyCode, string $urlId): ?array
    {
        return self::matchFilterPath($sectionCode, mb_strtolower($propertyCode) . '-is-' . $urlId);
    }

    /** Канонический вид пути фильтра: сегменты и значения «or» по алфавиту, без apply/clear. */
    public static function normalizePath(string $path): string
    {
        $segments = [];
        foreach (explode('/', trim($path, '/ ')) as $seg) {
            $seg = mb_strtolower(trim($seg));
            if ($seg === '' || $seg === 'apply' || $seg === 'clear') {
                continue;
            }
            if (preg_match('/^([a-z0-9_]+)-is-(.+)$/', $seg, $m)) {
                $values = explode('-or-', $m[2]);
                sort($values);
                $seg = $m[1] . '-is-' . implode('-or-', $values);
            }
            $segments[] = $seg;
        }
        sort($segments);
        return implode('/', $segments);
    }

    /** Путь фильтра → фильтр CIBlockElement::GetList (для подсчёта товаров тега). */
    public static function toElementFilter(string $path): ?array
    {
        $iblockId = (int)GetIBlockIDByCode('catalog');
        $filter = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '') {
                continue;
            }
            if (preg_match('/^([a-z0-9_]+)-is-(.+)$/', $seg, $m)) {
                $code = strtoupper($m[1]);
                $prop = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code])->Fetch();
                if (!$prop) {
                    return null;
                }
                $ids = [];
                foreach (explode('-or-', $m[2]) as $xml) {
                    $en = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $prop['ID'], 'XML_ID' => $xml])->Fetch();
                    if (!$en) {
                        return null;
                    }
                    $ids[] = (int)$en['ID'];
                }
                $filter['=PROPERTY_' . $prop['ID']] = $ids;
            } elseif (preg_match('/^([a-z0-9_]+)-(?:from-([\d.]+))?-?(?:to-([\d.]+))?$/', $seg, $m)) {
                $code = strtoupper($m[1]);
                $prop = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code])->Fetch();
                if (!$prop) {
                    return null;
                }
                if (($m[2] ?? '') !== '') {
                    $filter[] = ['>=PROPERTY_' . $prop['ID'] => (float)$m[2]];
                }
                if (($m[3] ?? '') !== '') {
                    $filter[] = ['<=PROPERTY_' . $prop['ID'] => (float)$m[3]];
                }
            } else {
                return null;
            }
        }
        return $filter;
    }

    /** Число активных товаров тега (раздел с подразделами + фильтр). */
    public static function countProducts(array $tag): int
    {
        return count(self::productIds($tag));
    }

    /** ID активных товаров тега (с учётом числовых диапазонов). @return int[] */
    public static function productIds(array $tag): array
    {
        $filter = self::toElementFilter($tag['FILTER']);
        if ($filter === null) {
            return [];
        }
        $iblockId = (int)GetIBlockIDByCode('catalog');
        $base = ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'SECTION_ID' => $tag['SECTION_ID'], 'INCLUDE_SUBSECTIONS' => 'Y', 'SECTION_GLOBAL_ACTIVE' => 'Y'];
        // числовые условия «от» и «до» должны выполняться одним значением множественного свойства —
        // GetList так не умеет, поэтому для диапазонов сверяем значения сами
        $ranges = [];
        $plain = [];
        foreach ($filter as $k => $v) {
            if (is_int($k)) {
                $ranges[] = $v;
            } else {
                $plain[$k] = $v;
            }
        }
        $rs = \CIBlockElement::GetList([], $base + $plain, false, false, ['ID']);
        $ids = [];
        while ($e = $rs->Fetch()) {
            $ids[] = (int)$e['ID'];
        }
        if (!$ids || !$ranges) {
            return $ids;
        }
        $byProp = [];
        foreach ($ranges as $r) {
            foreach ($r as $k => $v) {
                $pid = (int)preg_replace('/\D/', '', $k);
                $byProp[$pid][$k[0] === '>' ? 'min' : 'max'] = $v;
            }
        }
        $ok = [];
        foreach ($ids as $id) {
            $pass = true;
            foreach ($byProp as $pid => $lim) {
                $hit = false;
                $rsV = \CIBlockElement::GetProperty($iblockId, $id, [], ['ID' => $pid]);
                while ($pv = $rsV->Fetch()) {
                    $n = (float)$pv['VALUE'];
                    if ($pv['VALUE'] !== null && $pv['VALUE'] !== '' && $n >= ($lim['min'] ?? -INF) && $n <= ($lim['max'] ?? INF)) {
                        $hit = true;
                        break;
                    }
                }
                if (!$hit) {
                    $pass = false;
                    break;
                }
            }
            if ($pass) {
                $ok[] = $id;
            }
        }
        return $ok;
    }

    /** Сброс кэша тегов (после сида/правки). */
    public static function clearCache(): void
    {
        self::$all = null;
        \Bitrix\Main\Data\Cache::createInstance()->cleanDir('/profequip/seotags');
        BXClearCache(true, '/profequip/seotags');
    }
}
