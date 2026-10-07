<?php
/**
 * Поиск по каталогу и услугам: страница /search/ и живые подсказки в шапке
 * (/local/ajax/search.php).
 *
 * Почему не модуль search / фильтр ?NAME: подстрочный LIKE не понимает
 * «TWE18» = «TWE 18» = «ТВЕ 18», а порядок результатов не отражает
 * релевантность. Здесь — свой индекс (≈1.8 тыс. товаров, кэш по тегу
 * инфоблока, сбрасывается сам при правке элементов) и ранжирование в PHP.
 *
 * Нормализация (одинаково для индекса и запроса):
 *  - нижний регистр, кириллица → латиница по упрощённой схеме, затем
 *    сведение латиницы к «фонетическому» виду (w→v, c→k, x→ks, y/j→i, th→t,
 *    kh→h, двойные буквы → одинарные). Так «Толон» = «Tolon»,
 *    «Электролюкс» = «Electrolux», «Хошизаки» = «Hoshizaki», а кириллические
 *    «двойники» латинских букв в артикулах (Т, Е, В…) не мешают;
 *  - граница буква/цифра — граница слова: «twe18» → «twe 18»;
 *  - «склеенная» форма без пробелов для сравнения моделей целиком.
 * Если ничего не нашлось — повтор с другой раскладкой («ещдщт» → «tolon»)
 * и с опечатками (Левенштейн).
 */

class ProfEquipSearch
{
    const CATALOG_IBLOCK_CODE = 'catalog';
    const SERVICES_IBLOCK_CODE = 'services';
    const SPARE_PARTS_SECTION_CODE = 'zapasnye-chasti';
    const CACHE_TTL = 86400;
    const CACHE_VERSION = 'v3';

    /** Поля товара и их вес. digits=false — цифры из поля не учитываются (описания полны «18 кг»). */
    const FIELDS = [
        'name'    => ['weight' => 1.0,  'digits' => true],
        'ident'   => ['weight' => 1.0,  'digits' => true],  // модель, артикул
        'brand'   => ['weight' => 0.9,  'digits' => true],
        'section' => ['weight' => 0.6,  'digits' => false],
        'compat'  => ['weight' => 0.5,  'digits' => true],  // «Модели оборудования» у запчастей
        'text'    => ['weight' => 0.25, 'digits' => false], // анонс
    ];

    /** Ключевые слова услуг (по CODE элемента инфоблока services) — помимо названия. */
    const SERVICE_KEYWORDS = [
        'servisnoe-obsluzhivanie' => 'сервис обслуживание ремонт починка диагностика неисправность поломка техобслуживание то',
        'montazhnye-i-puskonaladochnye-raboty' => 'монтаж установка подключение пусконаладка пусконаладочные запуск',
        'tehnologicheskoe-proektirovanie' => 'проект проектирование планировка технологический чертеж',
        'konsalting' => 'консалтинг консультация аудит подбор помощь',
        'postavka-aksessuarov-i-zapasnyh-chastej' => 'запчасти запасные части аксессуары деталь комплектующие тэн ремень датчик клапан плата контроллер',
        'prodazha-oborudovaniya' => 'продажа купить оборудование поставка цена',
        'kompleksnoe-osnashhenie-otelej' => 'отель гостиница хостел оснащение комплексное номерной фонд',
    ];

    const CYR = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh', 'ъ' => '',
        'ы' => 'i', 'ь' => '', 'э' => 'e', 'ю' => 'u', 'я' => 'ia',
    ];

    const LAYOUT_EN = "qwertyuiop[]asdfghjkl;'zxcvbnm,.`";
    const LAYOUT_RU = "йцукенгшщзхъфывапролджэячсмитьбюё";

    private static $index = null;

    /* ===================== нормализация ===================== */

    /** Строка → нормализованный текст из слов через пробел. */
    public static function normalize($s)
    {
        $s = mb_strtolower(html_entity_decode(strip_tags((string)$s), ENT_QUOTES, 'UTF-8'));
        $s = strtr($s, self::CYR);
        $s = str_replace('ch', "\x01", $s);           // «ch» не трогаем заменой c→k
        $s = strtr($s, ['ph' => 'f', 'th' => 't', 'kh' => 'h', 'ck' => 'k', 'tz' => 'ts', 'x' => 'ks', 'w' => 'v', 'q' => 'k', 'c' => 'k', 'y' => 'i', 'j' => 'i']);
        $s = str_replace("\x01", 'ch', $s);
        $s = str_replace('tsi', 'ti', $s);              // «рационал» = «rational», «станция» = «stantion»
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        $s = preg_replace('/([a-z])\1+/', '$1', $s);  // ll→l, ss→s
        $s = preg_replace('/(?<=[a-z])(?=[0-9])|(?<=[0-9])(?=[a-z])/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    public static function tokens($norm)
    {
        return $norm === '' ? [] : explode(' ', $norm);
    }

    /** Грубая основа слова: «stiralnaia»/«stiralnie» → «stiraln». */
    public static function stem($t)
    {
        if (strlen($t) < 5 || ctype_digit($t)) {
            return $t;
        }
        $s = rtrim($t, 'aeiou');
        return strlen($s) >= 4 ? $s : $t;
    }

    private static function switchLayout($q)
    {
        $en = preg_split('//u', self::LAYOUT_EN, -1, PREG_SPLIT_NO_EMPTY);
        $ru = preg_split('//u', self::LAYOUT_RU, -1, PREG_SPLIT_NO_EMPTY);
        $q = mb_strtolower($q);
        $map = preg_match('/[а-яё]/u', $q) ? array_combine($ru, $en) : array_combine($en, $ru);
        return strtr($q, $map);
    }

    /* ===================== индекс ===================== */

    private static function field($text, $allowDigits = true)
    {
        $norm = self::normalize($text);
        $tokens = array_values(array_unique(self::tokens($norm)));
        if (!$allowDigits) {
            $tokens = array_values(array_filter($tokens, fn($t) => !ctype_digit($t)));
        }
        return [
            't' => $tokens,
            's' => array_values(array_unique(array_map([self::class, 'stem'], $tokens))),
            'c' => str_replace(' ', '', $norm),
        ];
    }

    public static function getIndex()
    {
        if (self::$index !== null) {
            return self::$index;
        }
        $iblockId = (int)GetIBlockIDByCode(self::CATALOG_IBLOCK_CODE);
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        if ($cache->initCache(self::CACHE_TTL, 'profequip_search_' . self::CACHE_VERSION, '/profequip/search')) {
            return self::$index = $cache->getVars();
        }
        $cache->startDataCache();
        $taggedCache = \Bitrix\Main\Application::getInstance()->getTaggedCache();
        $taggedCache->startTagCache('/profequip/search');
        $taggedCache->registerTag('iblock_id_' . $iblockId);
        $taggedCache->registerTag('iblock_id_' . (int)GetIBlockIDByCode('brands'));
        $taggedCache->registerTag('iblock_id_' . (int)GetIBlockIDByCode(self::SERVICES_IBLOCK_CODE));

        self::$index = [
            'products' => self::buildProducts($iblockId),
            'sections' => self::buildSections($iblockId),
            'services' => self::buildServices(),
        ];
        $taggedCache->endTagCache();
        $cache->endDataCache(self::$index);
        return self::$index;
    }

    private static function buildSections($iblockId)
    {
        $sections = [];
        $res = CIBlockSection::GetList(['LEFT_MARGIN' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'GLOBAL_ACTIVE' => 'Y', 'CNT_ACTIVE' => 'Y'],
            true, ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'SECTION_PAGE_URL', 'DEPTH_LEVEL']);
        while ($s = $res->GetNext()) {
            if ((int)$s['ELEMENT_CNT'] === 0) {
                continue;
            }
            $sections[(int)$s['ID']] = [
                'id' => (int)$s['ID'],
                'parent' => (int)$s['IBLOCK_SECTION_ID'],
                'name' => $s['~NAME'],
                'url' => $s['SECTION_PAGE_URL'],
                'count' => (int)$s['ELEMENT_CNT'],
                'f' => self::field($s['~NAME']),
            ];
        }
        return $sections;
    }

    private static function buildProducts($iblockId)
    {
        // Названия брендов (свойство BRAND — привязка к инфоблоку brands)
        $brands = [];
        $res = CIBlockElement::GetList([], ['IBLOCK_ID' => (int)GetIBlockIDByCode('brands')], false, false, ['ID', 'NAME']);
        while ($b = $res->Fetch()) {
            $brands[(int)$b['ID']] = $b['NAME'];
        }

        // Все разделы (с родителями) — для названий категорий и корня ветки
        $allSections = [];
        $res = CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId], false, ['ID', 'NAME', 'CODE', 'IBLOCK_SECTION_ID']);
        while ($s = $res->Fetch()) {
            $allSections[(int)$s['ID']] = ['name' => $s['NAME'], 'code' => $s['CODE'], 'parent' => (int)$s['IBLOCK_SECTION_ID']];
        }

        $products = [];
        $res = CIBlockElement::GetList(['ID' => 'ASC'], [
            'IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y', 'SECTION_GLOBAL_ACTIVE' => 'Y',
        ], false, false, ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'PREVIEW_TEXT']);
        while ($e = $res->GetNext()) {
            $id = (int)$e['ID'];
            if (isset($products[$id])) {
                continue;
            }
            $sectionNames = [];
            $root = 0;
            for ($sid = (int)$e['IBLOCK_SECTION_ID'], $guard = 0; $sid && isset($allSections[$sid]) && $guard < 10; $guard++) {
                $sectionNames[] = $allSections[$sid]['name'];
                $root = $sid;
                $sid = $allSections[$sid]['parent'];
            }
            $preview = mb_substr(trim(html_entity_decode(strip_tags($e['~PREVIEW_TEXT'] ?? ''))), 0, 400);
            $products[$id] = [
                'id' => $id,
                'name' => $e['~NAME'],
                'url' => $e['DETAIL_PAGE_URL'],
                'pic' => (int)$e['PREVIEW_PICTURE'],
                'section' => $sectionNames[0] ?? '',
                'parts' => $root && $allSections[$root]['code'] === self::SPARE_PARTS_SECTION_CODE,
                'raw' => ['section' => implode(' ', $sectionNames), 'text' => $preview],
                'props' => ['ident' => [], 'brand' => [], 'compat' => []],
            ];
        }

        // Свойства одним проходом по всем товарам
        $propMap = [
            'MODEL' => 'ident', 'MODEL_ARTIKUL' => 'ident', 'ARTIKUL_ZAPCHASTI' => 'ident',
            'PROIZVODITEL' => 'brand', 'BRAND' => 'brand', 'MODELI_OBORUDOVANIYA' => 'compat',
        ];
        $propIds = [];
        foreach (array_keys($propMap) as $code) {
            $p = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $code])->Fetch();
            if ($p) {
                $propIds[$p['ID']] = $p;
            }
        }
        if ($products && $propIds) {
            global $DB;
            $enumValues = [];
            $res = $DB->Query('SELECT ID, VALUE FROM b_iblock_property_enum WHERE PROPERTY_ID IN (' . implode(',', array_map('intval', array_keys($propIds))) . ')');
            while ($r = $res->Fetch()) {
                $enumValues[(int)$r['ID']] = $r['VALUE'];
            }
            $res = $DB->Query('SELECT IBLOCK_ELEMENT_ID, IBLOCK_PROPERTY_ID, VALUE, VALUE_ENUM FROM b_iblock_element_property'
                . ' WHERE IBLOCK_PROPERTY_ID IN (' . implode(',', array_map('intval', array_keys($propIds))) . ')');
            while ($r = $res->Fetch()) {
                $id = (int)$r['IBLOCK_ELEMENT_ID'];
                if (!isset($products[$id])) {
                    continue;
                }
                $prop = $propIds[$r['IBLOCK_PROPERTY_ID']];
                if ($prop['PROPERTY_TYPE'] === 'L') {
                    $value = $enumValues[(int)$r['VALUE_ENUM']] ?? '';
                } elseif ($prop['PROPERTY_TYPE'] === 'E') {
                    $value = $brands[(int)$r['VALUE']] ?? '';
                } else {
                    $value = $r['VALUE'];
                }
                if ($value !== '') {
                    $products[$id]['props'][$propMap[$prop['CODE']]][] = $value;
                }
            }
        }

        foreach ($products as $id => $p) {
            $f = [
                'name' => self::field($p['name']),
                'ident' => self::field(implode(' ', $p['props']['ident'])),
                'brand' => self::field(implode(' ', array_unique($p['props']['brand']))),
                'section' => self::field($p['raw']['section'], false),
                'compat' => self::field(implode(' ', $p['props']['compat'])),
                'text' => self::field($p['raw']['text'], false),
            ];
            // «Полные имена» товара: модель, артикул, бренд+модель — для точного совпадения
            $exact = [$f['name']['c']];
            foreach ($p['props']['ident'] as $v) {
                $c = str_replace(' ', '', self::normalize($v));
                $exact[] = $c;
                foreach ($p['props']['brand'] as $b) {
                    $exact[] = str_replace(' ', '', self::normalize($b)) . $c;
                }
            }
            $compatExact = [];
            foreach ($p['props']['compat'] as $v) {
                $compatExact[] = str_replace(' ', '', self::normalize($v));
            }
            // Позиция «для …»/«for …» в названии: всё, что после — совместимость, а не сам товар
            $nameNorm = self::normalize($p['name']);
            $forPos = -1;
            if (preg_match('/(^| )(dlia|for)( |$)/', $nameNorm, $m, PREG_OFFSET_CAPTURE)) {
                $forPos = strlen(str_replace(' ', '', substr($nameNorm, 0, $m[0][1])));
            }
            $products[$id] = [
                'id' => $id,
                'name' => $p['name'],
                'url' => $p['url'],
                'pic' => $p['pic'],
                'section' => $p['section'],
                'parts' => $p['parts'],
                'f' => $f,
                'exact' => array_values(array_unique(array_filter($exact))),
                'compatExact' => array_values(array_unique(array_filter($compatExact))),
                'forPos' => $forPos,
            ];
        }
        return $products;
    }

    private static function buildServices()
    {
        $services = [];
        $res = CIBlockElement::GetList(['SORT' => 'ASC', 'ID' => 'ASC'],
            ['IBLOCK_ID' => (int)GetIBlockIDByCode(self::SERVICES_IBLOCK_CODE), 'ACTIVE' => 'Y'],
            false, false, ['ID', 'NAME', 'CODE', 'DETAIL_PAGE_URL']);
        while ($e = $res->GetNext()) {
            $services[] = [
                'name' => $e['~NAME'],
                'url' => '/' . $e['CODE'] . '/',
                'f' => self::field($e['~NAME'] . ' ' . (self::SERVICE_KEYWORDS[$e['CODE']] ?? '')),
            ];
        }
        return $services;
    }

    /* ===================== сопоставление ===================== */

    /**
     * Насколько хорошо слово запроса совпадает с полем: 1 — целиком,
     * 0.9 — по основе, 0.7 — начало слова, 0.4 — внутри склеенного поля,
     * 0.5 — опечатка (только в «мягком» режиме).
     */
    private static function tokenMatch($qt, $qs, array $f, $isLast, $soft)
    {
        $isDigit = ctype_digit($qt);
        if (in_array($qt, $f['t'], true)) {
            return 1.0;
        }
        if ($isDigit) {
            // Число — только целиком (18 ≠ 180), кроме набираемого слова и длинных артикулов
            if ($isLast || $soft || strlen($qt) >= 4) {
                foreach ($f['t'] as $t) {
                    if (strncmp($t, $qt, strlen($qt)) === 0) {
                        return 0.6;
                    }
                }
            }
            return 0;
        }
        if (strlen($qs) >= 4 && in_array($qs, $f['s'], true)) {
            return 0.9;
        }
        $best = 0;
        foreach ($f['t'] as $t) {
            if (strncmp($t, $qt, strlen($qt)) === 0 || (strlen($qs) >= 4 && strncmp($t, $qs, strlen($qs)) === 0)) {
                $best = 0.7;
                break;
            }
        }
        if (!$best && strlen($qt) >= 4 && strpos($f['c'], $qt) !== false) {
            $best = 0.4;
        }
        if (!$best && $soft && strlen($qt) >= 5) {
            $max = strlen($qt) >= 8 ? 2 : 1;
            foreach ($f['t'] as $t) {
                if (abs(strlen($t) - strlen($qt)) <= $max && levenshtein($t, $qt) <= $max) {
                    return 0.5;
                }
            }
        }
        return $best;
    }

    /**
     * Ищет склеенный запрос внутри склеенного поля так, чтобы число не
     * обрывалось посередине («twe18» не находится в «twe180»).
     * Возвращает позицию или -1.
     */
    private static function compactFind($haystack, $needle, $soft)
    {
        $len = strlen($needle);
        for ($pos = strpos($haystack, $needle); $pos !== false; $pos = strpos($haystack, $needle, $pos + 1)) {
            $before = $pos > 0 ? $haystack[$pos - 1] : '';
            $after = $haystack[$pos + $len] ?? '';
            if ($before !== '' && ctype_digit($before) && ctype_digit($needle[0])) {
                continue;
            }
            if (!$soft && $after !== '' && ctype_digit($after) && ctype_digit($needle[$len - 1])) {
                continue;
            }
            return $pos;
        }
        return -1;
    }

    private static function scoreProduct(array $p, array $q, $soft)
    {
        $total = 0;
        $onlyText = true;
        $last = count($q['tokens']) - 1;
        foreach ($q['tokens'] as $i => $qt) {
            $best = 0;
            $bestField = '';
            foreach (self::FIELDS as $code => $cfg) {
                if (!$cfg['digits'] && ctype_digit($qt)) {
                    continue;
                }
                $m = self::tokenMatch($qt, $q['stems'][$i], $p['f'][$code], $i === $last, $soft) * $cfg['weight'];
                if ($m > $best) {
                    $best = $m;
                    $bestField = $code;
                    if ($best >= 1.0) {
                        break;
                    }
                }
            }
            if ($best <= 0) {
                return 0;   // каждое слово запроса должно где-то найтись
            }
            $total += $best;
            if ($bestField !== 'text') {
                $onlyText = false;
            }
        }
        if ($onlyText) {
            return 1;   // нашлось только в описании — показываем, лишь если больше ничего нет
        }
        $score = 100 * $total / count($q['tokens']);

        $qc = $q['compact'];
        if (in_array($qc, $p['exact'], true)) {
            $score += 1000;                               // точное название / модель / бренд+модель
        } elseif (preg_match('/[0-9]/', $qc) && in_array($qc, $p['compatExact'], true)) {
            $score += 250;                                // запчасть именно к этой модели (не просто «к TOLON»)
        }
        if ((strlen($qc) >= 3 && count($q['tokens']) > 1) || preg_match('/[a-z][0-9]|[0-9][a-z]/', $q['raw'])) {
            $pos = self::compactFind($p['f']['name']['c'], $qc, $soft);
            if ($pos >= 0) {
                // фраза целиком в названии; после «для …» — это совместимость, не сам товар
                $score += ($p['forPos'] >= 0 && $pos >= $p['forPos']) ? 80 : 300;
            } elseif (self::compactFind($p['f']['ident']['c'], $qc, $soft) >= 0) {
                $score += 300;
            }
        }
        // короче название при прочих равных — ближе к запросу
        $score += 30 * min(1, strlen($qc) / max(1, strlen($p['f']['name']['c'])));
        if ($p['forPos'] >= 0) {
            // «… для TOLON»: если запрос встречается в названии только после «для» — это не сам товар
            $head = substr($p['f']['name']['c'], 0, $p['forPos']);
            $inHead = false;
            foreach ($q['stems'] as $st) {
                if (strlen($st) >= 2 && strpos($head, $st) !== false) {
                    $inHead = true;
                    break;
                }
            }
            $score -= $inHead ? 10 : 60;
        }
        if ($p['parts']) {
            $score -= 15;
        }
        return $score;
    }

    private static function prepareQuery($query)
    {
        $norm = self::normalize($query);
        $tokens = self::tokens($norm);
        return [
            'raw' => $norm,
            'tokens' => $tokens,
            'stems' => array_map([self::class, 'stem'], $tokens),
            'compact' => str_replace(' ', '', $norm),
        ];
    }

    private static function rank(array $q, $soft)
    {
        $scores = [];
        foreach (self::getIndex()['products'] as $id => $p) {
            $s = self::scoreProduct($p, $q, $soft);
            if ($s > 0) {
                $scores[$id] = $s;
            }
        }
        $products = self::getIndex()['products'];
        uksort($scores, function ($a, $b) use ($scores, $products) {
            return [$scores[$b], strlen($products[$a]['name']), $a] <=> [$scores[$a], strlen($products[$b]['name']), $b];
        });
        return $scores;
    }

    /* ===================== публичное API ===================== */

    /**
     * ID товаров по убыванию релевантности.
     * @param bool $suggest режим подсказок: недописанное число ищется по началу
     */
    public static function searchProductIds($query, $suggest = false)
    {
        return array_keys(self::searchScores($query, $suggest));
    }

    /** [ID => релевантность], по убыванию. 1 — совпало только в описании. */
    private static function searchScores($query, $suggest)
    {
        $q = self::prepareQuery($query);
        if (!$q['tokens'] || strlen($q['compact']) < 2) {
            return [];
        }
        $scores = self::rank($q, false);
        if (!$scores) {
            $scores = self::rank($q, true);
        }
        if (!$scores) {
            $alt = self::prepareQuery(self::switchLayout($query));
            if ($alt['tokens']) {
                $scores = self::rank($alt, true);
            }
        }
        if ($scores) {
            $top = reset($scores);
            if ($top >= 1000 && !$suggest) {
                // есть точное совпадение названия/модели — хвост слабых совпадений не нужен
                $scores = array_filter($scores, fn($s) => $s >= 300);
            } elseif ($top > 1) {
                $scores = array_filter($scores, fn($s) => $s > 1);
            }
        }
        return $scores;
    }

    /** Категории каталога, подходящие под запрос (по названию раздела). */
    public static function searchSections($query, $limit = 4)
    {
        $q = self::prepareQuery($query);
        if (!$q['tokens']) {
            return [];
        }
        $found = [];
        foreach (self::getIndex()['sections'] as $s) {
            $sum = 0;
            foreach ($q['tokens'] as $i => $qt) {
                $m = self::tokenMatch($qt, $q['stems'][$i], $s['f'], $i === count($q['tokens']) - 1, false);
                if ($m <= 0) {
                    continue 2;
                }
                $sum += $m;
            }
            $found[] = ['name' => $s['name'], 'url' => $s['url'], 'count' => $s['count'], 'score' => $sum];
        }
        usort($found, fn($a, $b) => [$b['score'], $b['count']] <=> [$a['score'], $a['count']]);
        return array_slice($found, 0, $limit);
    }

    /**
     * Услуги: сначала подходящие под запрос, затем остальные.
     * @param string $context 'parts' — в выдаче в основном запчасти, 'equipment' — оборудование
     */
    public static function suggestServices($query, $context = '')
    {
        $boost = [
            'parts' => ['zapasnyh-chastej' => 0.5],
            'equipment' => ['prodazha-oborudovaniya' => 0.4, 'servisnoe-obsluzhivanie' => 0.3, 'montazhnye' => 0.2],
        ][$context] ?? [];
        $q = self::prepareQuery($query);
        $list = [];
        foreach (self::getIndex()['services'] as $i => $s) {
            $score = 0;
            foreach ($q['tokens'] as $j => $qt) {
                $score += self::tokenMatch($qt, $q['stems'][$j], $s['f'], $j === count($q['tokens']) - 1, false);
            }
            $match = $score > 0;
            foreach ($boost as $code => $add) {
                if (strpos($s['url'], $code) !== false) {
                    $score += $add;
                }
            }
            $list[] = ['name' => $s['name'], 'url' => $s['url'], 'match' => $match, 'score' => $score, 'sort' => $i];
        }
        usort($list, fn($a, $b) => [$b['score'], $a['sort']] <=> [$a['score'], $b['sort']]);
        return array_map(fn($s) => ['name' => $s['name'], 'url' => $s['url'], 'match' => $s['match']], $list);
    }

    /** Данные для выпадающих подсказок в шапке. */
    public static function suggest($query, $limit = 6)
    {
        $scores = self::searchScores($query, true);
        $ids = array_keys($scores);
        $index = self::getIndex()['products'];
        if ($scores && reset($scores) <= 1) {
            $limit = 0;   // только совпадения в описаниях — в подсказках шум, оставляем ссылку «все результаты»
        }
        $products = [];
        $parts = 0;
        foreach (array_slice($ids, 0, $limit) as $id) {
            $p = $index[$id];
            $img = '';
            if ($p['pic']) {
                // тот же размер, что в карточке листинга — ресайз уже лежит в resize_cache
                $r = CFile::ResizeImageGet($p['pic'], ['width' => 600, 'height' => 600], BX_RESIZE_IMAGE_PROPORTIONAL, true);
                $img = $r['src'] ?? '';
            }
            if ($p['parts']) {
                $parts++;
            }
            $products[] = ['name' => $p['name'], 'url' => $p['url'], 'img' => $img, 'section' => $p['section']];
        }
        return [
            'query' => $query,
            'total' => count($ids),
            'products' => $products,
            'sections' => self::searchSections($query),
            'services' => self::suggestServices($query, !$products ? '' : ($parts * 2 > count($products) ? 'parts' : 'equipment')),
            'allUrl' => '/search/?s=' . rawurlencode($query),
        ];
    }
}
