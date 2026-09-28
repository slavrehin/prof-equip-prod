<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Лендинг направления. Источники данных (всё правится в админке):
 *  - элемент направления (инфоблок = код направления): ВСЁ содержимое лендинга — свойства лендинга
 *    (блоки, слайды, преимущества, решения, этапы, гарантия, статьи и новости) плюс выбор проектов и брендов;
 *  - товары каталога с флагом «В наличии» (STOCK_BADGE);
 *  - контакты (инфоблок contacts, элемент с MAIN).
 * SEO (title/description) — вкладка «SEO» элемента направления.
 *
 * @var CMain $APPLICATION
 * @var array $arParams
 * @var CBitrixComponent $this
 */

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Loader;

global $APPLICATION;

foreach (['iblock', 'catalog', 'currency', 'form'] as $module) {
    Loader::includeModule($module);
}

$direction = trim((string)($arParams['DIRECTION_CODE'] ?? '')) ?: 'prachechnaya';
$dirIblockCode = trim((string)($arParams['DIRECTION_IBLOCK_CODE'] ?? '')) ?: $direction;
$ttl = (int)($arParams['CACHE_TIME'] ?? 3600);
$ttl = $ttl > 0 ? $ttl : 0;

$lp = static function (array $props, string $code, bool $multiple = false) {
    return profequip_LandingProp($props, $code, $multiple);
};

// ---------------------------------------------------------------------------
// Статическая часть (кэшируется, сбрасывается по тегам инфоблоков)
// ---------------------------------------------------------------------------

$buildStatic = static function () use ($direction, $dirIblockCode, $lp): array {
    $usedIblocks = [];
    $out = [];
    $iblockId = static function (string $code) use (&$usedIblocks): int {
        $id = (int)GetIBlockIDByCode($code);
        if ($id) {
            $usedIblocks[$id] = $id;
        }
        return $id;
    };

    // --- элемент направления: всё содержимое лендинга лежит в его свойствах ------
    $dirIblock = $iblockId($dirIblockCode);
    $dirEl = $dirIblock ? \CIBlockElement::GetList([], ['IBLOCK_ID' => $dirIblock, 'CODE' => $direction, 'ACTIVE' => 'Y'], false, ['nTopCount' => 1], ['ID', 'IBLOCK_ID', 'NAME'])->GetNextElement() : null;
    $dirFields = $dirEl ? $dirEl->GetFields() : null;
    $dirProps = $dirEl ? $dirEl->GetProperties() : [];
    $out['direction'] = $dirFields ? ['id' => (int)$dirFields['ID'], 'iblock' => $dirIblock, 'name' => (string)$dirFields['~NAME']] : null;

    $decode = static fn(string $code): array => profequip_LandingDecode($dirProps[$code]['~VALUE'] ?? '', $code);
    $text = static fn(array $row, string $key): string => (string)($row[$key] ?? '');
    $pairs = static fn(array $row, string $key): array => array_map(
        static fn($p) => ['value' => (string)$p['v'], 'desc' => (string)$p['d']],
        $row[$key] ?? []
    );

    // --- блоки страницы -------------------------------------------------
    $sections = [];
    foreach ($decode('LAND_BLOCKS') as $key => $r) {
        if (empty($r['active'])) {
            continue;
        }
        $sections[$key] = [
            'name' => $text($r, 'title'),
            'lead' => $text($r, 'lead'),
            'anchor' => $text($r, 'anchor'),
            'linkText' => $text($r, 'link_text'),
            'linkUrl' => $text($r, 'link_url'),
            'btn' => $text($r, 'btn'),
            'btn2Text' => $text($r, 'btn2_text'),
            'btn2Url' => $text($r, 'btn2_url'),
            'formTitle' => $text($r, 'form_title'),
            'formText' => $text($r, 'form_text'),
            'ok' => $text($r, 'ok'),
            'source' => $text($r, 'source'),
            'items' => $pairs($r, 'items'),
            'links' => $pairs($r, 'links'),
            'tasks' => $pairs($r, 'tasks'),
            'sectionCode' => $text($r, 'section_code'),
            'limit' => (int)($r['limit'] ?? 0),
            'priceNote' => $text($r, 'price_note'),
            'pic' => (int)($r['pic'] ?? 0),
            'featuredPic' => (int)($r['featured_pic'] ?? 0),
            'featuredName' => $text($r, 'featured_name'),
            'featuredUrl' => $text($r, 'featured_url'),
            'featuredLabel' => $text($r, 'featured_label'),
            'blogIds' => [],
            'newsIds' => [],
        ];
    }
    if (isset($sections['expert'])) { // статьи и новости — обычные свойства-привязки элемента направления
        $sections['expert']['blogIds'] = array_map('intval', $lp($dirProps, 'LAND_MAT_BLOG', true));
        $sections['expert']['newsIds'] = array_map('intval', $lp($dirProps, 'LAND_MAT_NEWS', true));
    }
    $out['sections'] = $sections;

    // --- слайды -----------------------------------------------------------
    $out['slides'] = array_map(static fn(array $r) => [
        'name' => $text($r, 'title'),
        'text' => $text($r, 'text'),
        'pic' => (int)$r['pic'],
        'picMob' => (int)$r['pic_mob'],
        'kicker' => $text($r, 'kicker'),
        'tab' => $text($r, 'tab') ?: $text($r, 'title'),
        'btn1' => ['text' => $text($r, 'btn1_text'), 'target' => $text($r, 'btn1_target'), 'source' => $text($r, 'btn1_source')],
        'btn2' => ['text' => $text($r, 'btn2_text'), 'target' => $text($r, 'btn2_target')],
        'art' => $text($r, 'art') ?: 'washer',
    ], $decode('LAND_SLIDES'));

    // --- преимущества -----------------------------------------------------
    $out['advantages'] = array_map(static fn(array $r) => [
        'name' => $text($r, 'title'), 'icon' => $text($r, 'icon') ?: 'shield',
    ], $decode('LAND_ADVANTAGES'));

    // --- решения / зоны -------------------------------------------------------
    $out['solutions'] = array_map(static fn(array $r) => [
        'name' => $text($r, 'title'),
        'sub' => $text($r, 'sub'),
        'pic' => (int)$r['pic'],
        'specs' => $pairs($r, 'specs'),
        'url' => $text($r, 'url'),
        'linkText' => $text($r, 'link_text'),
        'art' => $text($r, 'art') ?: 'washer',
        'objtypes' => array_values(array_filter(array_map('trim', explode(',', $text($r, 'objtypes'))))),
    ], $decode('LAND_SOLUTIONS'));

    // --- каталог: новинки ------------------------------------------------------
    $out['novelties'] = array_map(static fn(array $r) => [
        'name' => $text($r, 'title'), 'params' => $pairs($r, 'params'), 'url' => $text($r, 'url'), 'pic' => (int)$r['pic'],
    ], $decode('LAND_NOVELTIES'));

    // --- технологии --------------------------------------------------------
    $out['tech'] = array_map(static fn(array $r) => [
        'name' => $text($r, 'title'), 'task' => $text($r, 'task_label'), 'text' => $text($r, 'text'),
        'detail' => $text($r, 'detail'), 'featured' => $text($r, 'featured') === 'y', 'pic' => (int)$r['pic'],
    ], $decode('LAND_TECH'));

    // --- библиотека ----------------------------------------------------------
    $out['library'] = array_map(static fn(array $r) => [
        'pic' => (int)$r['pic'], 'caption' => $text($r, 'caption'),
    ], $decode('LAND_LIBRARY'));

    // --- вопросы и ответы -------------------------------------------------------
    $out['faq'] = array_map(static fn(array $r) => [
        'q' => $text($r, 'q'), 'a' => $text($r, 'a'),
    ], $decode('LAND_FAQ'));

    // --- отзывы -----------------------------------------------------------------
    $out['reviews'] = array_map(static fn(array $r) => [
        'quote' => $text($r, 'quote'), 'name' => $text($r, 'name'), 'role' => $text($r, 'role'), 'pic' => (int)$r['pic'],
    ], $decode('LAND_REVIEWS'));

    // --- этапы ----------------------------------------------------------------
    $out['steps'] = array_map(static fn(array $r) => [
        'name' => $text($r, 'title'), 'text' => $text($r, 'text'), 'result' => $text($r, 'result'), 'pic' => (int)$r['pic'],
    ], $decode('LAND_STEPS'));

    // --- круговая гарантия ---------------------------------------------------
    $out['guarantee'] = array_slice(array_map(static fn(array $r) => [
        'name' => $text($r, 'title'),
        'text' => $text($r, 'text'),
        'pic' => (int)$r['pic'],
        'url' => $text($r, 'url'),
        'label' => $text($r, 'label'),
        'icon' => $text($r, 'icon') ?: 'washer',
    ], $decode('LAND_GUARANTEE')), 0, 4);

    $linked = static function (string $propCode) use ($dirFields, $dirIblock): array {
        $ids = [];
        if ($dirFields) {
            $rs = \CIBlockElement::GetProperty($dirIblock, (int)$dirFields['ID'], ['value_id' => 'asc'], ['CODE' => $propCode]);
            while ($r = $rs->Fetch()) {
                if ((int)$r['VALUE'] > 0) {
                    $ids[] = (int)$r['VALUE'];
                }
            }
        }
        return $ids;
    };

    // проекты
    $projects = [];
    $projIblock = $iblockId('projects');
    $projIds = $linked('PROJECTS');
    if ($projIds && $projIblock) {
        $rs = \CIBlockElement::GetList([], ['IBLOCK_ID' => $projIblock, 'ID' => $projIds, 'ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y']);
        $byId = [];
        while ($ob = $rs->GetNextElement()) {
            $f = $ob->GetFields();
            $p = $ob->GetProperties();
            $meta = $lp($p, 'CARD_META', true);
            if (!$meta) { // запасной вариант: тип объекта из свойства «Тип» (без самого «Прачечная») + город
                foreach ($lp($p, 'TYPE', true) as $enumId) {
                    $en = \CIBlockPropertyEnum::GetByID((int)$enumId);
                    if ($en && $en['VALUE'] !== 'Прачечная' && count($meta) < 1) {
                        $meta[] = $en['VALUE'];
                    }
                }
                if ($lp($p, 'CITY')) {
                    $meta[] = (string)$lp($p, 'CITY');
                }
            }
            $byId[(int)$f['ID']] = [
                'name' => (string)$f['NAME'],
                'url' => (string)$f['DETAIL_PAGE_URL'],
                'pic' => (int)($f['PREVIEW_PICTURE'] ?: $f['DETAIL_PICTURE']),
                'meta' => array_map('strval', array_slice($meta, 0, 3)),
                'text' => (string)$lp($p, 'CARD_TEXT'),
                'scope' => array_map('strval', $lp($p, 'CARD_SCOPE', true)),
            ];
        }
        foreach ($projIds as $id) { // порядок — как выбрал редактор
            if (isset($byId[$id])) {
                $projects[] = $byId[$id];
            }
        }
    }
    $out['projects'] = $projects;

    // бренды
    $brands = [];
    $brandIblock = $iblockId('brands');
    $brandIds = $linked('BRANDS');
    if ($brandIds && $brandIblock) {
        $rs = \CIBlockElement::GetList([], ['IBLOCK_ID' => $brandIblock, 'ID' => $brandIds, 'ACTIVE' => 'Y']);
        $byId = [];
        while ($ob = $rs->GetNextElement()) {
            $f = $ob->GetFields();
            $p = $ob->GetProperties();
            $byId[(int)$f['ID']] = [
                'name' => (string)$f['NAME'],
                'url' => (string)$f['DETAIL_PAGE_URL'],
                'logo' => (int)($lp($p, 'LOGO') ?: $f['PREVIEW_PICTURE']),
            ];
        }
        foreach ($brandIds as $id) {
            if (isset($byId[$id])) {
                $brands[] = $byId[$id];
            }
        }
    }
    $out['brands'] = $brands;

    // --- экспертный центр: статьи + новости ------------------------------------------
    $materials = ['big' => null, 'list' => []];
    $exp = $sections['expert'] ?? null;
    if ($exp) {
        $items = [];
        foreach (['blog' => $exp['blogIds'], 'news' => $exp['newsIds']] as $type => $ids) {
            $ib = $iblockId($type);
            if (!$ids || !$ib) {
                continue;
            }
            $rs = \CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, 'ID' => $ids, 'ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y']);
            while ($ob = $rs->GetNext()) {
                $date = $ob['ACTIVE_FROM'] ?: $ob['DATE_CREATE'];
                $plain = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string)($ob['~DETAIL_TEXT'] ?? '')))));
                $preview = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string)($ob['~PREVIEW_TEXT'] ?? '')))));
                $excerpt = $preview !== '' ? $preview : $plain;
                $items[] = [
                    'type' => $type,
                    'name' => (string)$ob['~NAME'],
                    'url' => (string)$ob['DETAIL_PAGE_URL'],
                    'pic' => (int)($ob['PREVIEW_PICTURE'] ?: $ob['DETAIL_PICTURE']),
                    'ts' => (int)MakeTimeStamp($date),
                    'date' => profequip_LandingDate($date),
                    'text' => mb_strlen($excerpt) > 220 ? rtrim(mb_substr($excerpt, 0, 217), " ,.;:—-") . '…' : $excerpt,
                    'minutes' => $plain !== '' ? max(1, (int)ceil(count(preg_split('/\s+/u', $plain)) / 180)) : 0,
                ];
            }
        }
        usort($items, static fn($a, $b) => $b['ts'] <=> $a['ts']);
        // крупная карточка — самая свежая статья блога (если блога нет — самый свежий материал)
        $bigKey = null;
        foreach ($items as $k => $item) {
            if ($item['type'] === 'blog') {
                $bigKey = $k;
                break;
            }
        }
        $bigKey ??= ($items ? 0 : null);
        if ($bigKey !== null) {
            $materials['big'] = $items[$bigKey];
            unset($items[$bigKey]);
        }
        $materials['list'] = array_slice(array_values($items), 0, 3);
    }
    $out['materials'] = $materials;

    // --- контакты ------------------------------------------------------------------
    $contacts = ['phone' => '', 'email' => '', 'address' => ''];
    $contactId = getMainIdFromContactIblock();
    if ($contactId) {
        $ob = \CIBlockElement::GetList([], ['ID' => $contactId, 'IBLOCK_ID' => 1], false, false, ['ID', 'IBLOCK_ID'])->GetNextElement();
        if ($ob) {
            $p = $ob->GetProperties();
            $contacts['phone'] = (string)$lp($p, 'PHONE');
            $contacts['email'] = (string)$lp($p, 'EMAIL');
            $addr = $p['ADDRESS']['~VALUE'] ?? '';
            $contacts['address'] = trim(strip_tags((string)(is_array($addr) ? ($addr['TEXT'] ?? '') : $addr)));
        }
        $usedIblocks[1] = 1;
    }
    $out['contacts'] = $contacts;

    // --- форма --------------------------------------------------------------------
    $out['form'] = profequip_LandingFormFields();

    $out['_iblocks'] = array_values($usedIblocks);
    return $out;
};

$cache = Cache::createInstance();
$cachePath = '/profequip/direction_landing/' . $direction;
$static = null;
if ($ttl > 0 && $cache->initCache($ttl, 'v2_' . $direction, $cachePath)) {
    $static = $cache->getVars();
} else {
    if ($ttl > 0) {
        $cache->startDataCache();
        $tagged = Application::getInstance()->getTaggedCache();
        $tagged->startTagCache($cachePath);
    }
    $static = $buildStatic();
    if ($ttl > 0) {
        foreach ($static['_iblocks'] as $ibId) {
            $tagged->registerTag('iblock_id_' . $ibId);
        }
        $tagged->endTagCache();
        $cache->endDataCache($static);
    }
}

$arResult = $static;
$arResult['DIRECTION_CODE'] = $direction;

// ---------------------------------------------------------------------------
// Товары «В наличии» — без кэша (цена зависит от курса валют)
// ---------------------------------------------------------------------------

$arResult['stock'] = [];
$stockSec = $arResult['sections']['stock'] ?? null;
if ($stockSec) {
    $catIblock = (int)GetIBlockIDByCode('catalog');
    $filter = ['IBLOCK_ID' => $catIblock, 'ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y', '!PROPERTY_STOCK_BADGE' => false];
    if ($stockSec['sectionCode'] !== '') {
        $sec = \CIBlockSection::GetList([], ['IBLOCK_ID' => $catIblock, 'CODE' => $stockSec['sectionCode']], false, ['ID'])->Fetch();
        if ($sec) {
            $filter['SECTION_ID'] = (int)$sec['ID'];
            $filter['INCLUDE_SUBSECTIONS'] = 'Y';
        }
    }
    $limit = $stockSec['limit'] > 0 ? $stockSec['limit'] : 6;
    $rs = \CIBlockElement::GetList(
        ['SORT' => 'ASC', 'ID' => 'ASC'],
        $filter,
        false,
        false,
        ['ID', 'IBLOCK_ID', 'NAME', 'CODE', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']
    );
    $brandCache = [];
    $seen = [];
    while ($el = $rs->GetNext()) {
        if (isset($seen[$el['ID']])) { // товар может лежать в нескольких разделах
            continue;
        }
        $seen[$el['ID']] = true;

        $rows = [];
        $pr = \CIBlockElement::GetProperty($catIblock, (int)$el['ID'], ['sort' => 'asc'], []);
        while ($row = $pr->Fetch()) {
            if ($row['VALUE'] !== '' && $row['VALUE'] !== null) {
                $rows[] = $row;
            }
        }
        $get = static function (string $code) use ($rows): string {
            foreach ($rows as $r) {
                if ($r['CODE'] === $code) {
                    return trim((string)($r['PROPERTY_TYPE'] === 'L' ? $r['VALUE_ENUM'] : $r['VALUE']));
                }
            }
            return '';
        };

        $brandId = (int)$get('BRAND');
        if ($brandId && !isset($brandCache[$brandId])) {
            $b = \CIBlockElement::GetByID($brandId)->Fetch();
            $brandCache[$brandId] = $b ? (string)$b['NAME'] : '';
        }
        $brand = $brandId ? mb_strtoupper($brandCache[$brandId]) : mb_strtoupper($get('PROIZVODITEL'));
        $model = $get('MODEL');
        $producer = $get('PROIZVODITEL');
        $title = ($producer !== '' && $model !== '') ? $producer . ' ' . $model : (string)$el['NAME'];

        $arResult['stock'][] = [
            'id' => (int)$el['ID'],
            'name' => $title,
            'fullName' => (string)$el['NAME'],
            'brand' => $brand,
            'type' => $get('TIP_OBORUDOVANIYA') ?: '',
            'url' => (string)$el['DETAIL_PAGE_URL'],
            'pic' => (int)($el['PREVIEW_PICTURE'] ?: $el['DETAIL_PICTURE']),
            'specs' => profequip_LandingProductSpecs($rows),
            'price' => profequip_LandingProductPrice((int)$el['ID']),
        ];
        if (count($arResult['stock']) >= $limit) {
            break;
        }
    }
}

// ---------------------------------------------------------------------------
// SEO: вкладка «SEO» элемента направления. H1 — поле «Заголовок элемента»
// (ELEMENT_PAGE_TITLE), выводится отдельно от слайдов (визуально скрыт); если
// поле пустое — H1 по-старому = заголовок первого слайда
// ---------------------------------------------------------------------------

$arResult['h1'] = '';
if (!empty($arResult['direction'])) {
    $ip = (new \Bitrix\Iblock\InheritedProperty\ElementValues($arResult['direction']['iblock'], $arResult['direction']['id']))->getValues();
    $arResult['h1'] = trim((string)($ip['ELEMENT_PAGE_TITLE'] ?? ''));
    if (!empty($ip['ELEMENT_META_TITLE'])) {
        $APPLICATION->SetPageProperty('title', $ip['ELEMENT_META_TITLE']);
    }
    if (!empty($ip['ELEMENT_META_DESCRIPTION'])) {
        $APPLICATION->SetPageProperty('description', $ip['ELEMENT_META_DESCRIPTION']);
    }
}
$h1 = $arResult['h1'] !== '' ? $arResult['h1'] : ($arResult['slides'][0]['name'] ?? ($arResult['direction']['name'] ?? ''));
if ($h1 !== '') {
    $APPLICATION->SetTitle($h1);
}

// Preload первого слайда (LCP): картинка — главный кандидат
if (!empty($arResult['slides'][0]['pic'])) {
    $lcp = \CFile::ResizeImageGet($arResult['slides'][0]['pic'], ['width' => 1920, 'height' => 1200], BX_RESIZE_IMAGE_PROPORTIONAL, true);
    if (!empty($lcp['src'])) {
        $APPLICATION->AddHeadString('<link rel="preload" as="image" href="' . htmlspecialcharsbx($lcp['src']) . '" fetchpriority="high">', true);
    }
}

$this->IncludeComponentTemplate();
