<?php
/**
 * Инфоблок «SEO-теги (посадочные подборки)» (CODE=seotags, тип seo — рядом с «SEO-правилами фильтра»).
 *
 * Тег = посадочная страница /product-category/<код раздела>/<код тега>/ — листинг раздела, отфильтрованный
 * по FILTER (путь умного фильтра: proizvoditel-is-electrolux, zagruzka_kg-from-24-to-28 …), со своими
 * H1/Title/Description/SEO-текстом, в индексе и в sitemap. Тот же тег — чипс в блоке «Популярные подборки»
 * над листингом (IN_BLOCK) и ссылка в фильтре. Если задан LINK — чипс ведёт туда (страницы нет).
 * Логика: local/php_interface/include/seo_tags.php; наполнение: local/deploy/catalog_restructure/seed_tags.php.
 * Идемпотентна.
 */

call_user_func(static function () {

global $DB;
\CModule::IncludeModule('iblock');

$code = 'seotags';
$catalogId = (int)GetIBlockIDByCode('catalog');
$ib = \CIBlock::GetList([], ['CODE' => $code, 'CHECK_PERMISSIONS' => 'N'])->Fetch();
if ($ib) {
    $iblockId = (int)$ib['ID'];
    echo "Инфоблок $code уже есть (ID=$iblockId)\n";
} else {
    $iblock = new \CIBlock();
    $iblockId = (int)$iblock->Add([
        'ACTIVE' => 'Y',
        'IBLOCK_TYPE_ID' => 'seo',
        'SITE_ID' => ['s1'],
        'CODE' => $code,
        'NAME' => 'SEO-теги (посадочные подборки)',
        'SORT' => 510,
        'GROUP_ID' => [1 => 'X', 2 => 'R'],
        'INDEX_ELEMENT' => 'N',
        'FIELDS' => ['CODE' => ['IS_REQUIRED' => 'Y', 'DEFAULT_VALUE' => ['TRANSLITERATION' => 'Y', 'TRANS_LEN' => 80,
            'TRANS_CASE' => 'L', 'TRANS_SPACE' => '-', 'TRANS_OTHER' => '-', 'TRANS_EAT' => 'Y', 'UNIQUE' => 'N']]],
        'ELEMENT_NAME' => 'Тег', 'ELEMENTS_NAME' => 'Теги', 'ELEMENT_ADD' => 'Добавить тег',
    ]);
    if (!$iblockId) {
        throw new \RuntimeException('Не создан инфоблок seotags: ' . $iblock->LAST_ERROR);
    }
    echo "Создан инфоблок $code (ID=$iblockId)\n";
}

$props = [
    ['CODE' => 'SECTION', 'NAME' => 'Раздел каталога', 'PROPERTY_TYPE' => 'G', 'LINK_IBLOCK_ID' => $catalogId, 'IS_REQUIRED' => 'Y', 'SORT' => 10],
    ['CODE' => 'FILTER', 'NAME' => 'Фильтр (путь умного фильтра: proizvoditel-is-electrolux, zagruzka_kg-from-24-to-28, несколько — через /)', 'PROPERTY_TYPE' => 'S', 'COL_COUNT' => 60, 'SORT' => 20],
    ['CODE' => 'LINK', 'NAME' => 'Ссылка вместо страницы тега (если чипс должен вести на раздел/страницу)', 'PROPERTY_TYPE' => 'S', 'COL_COUNT' => 60, 'SORT' => 25],
    ['CODE' => 'GROUP', 'NAME' => 'Группа в блоке подборок', 'PROPERTY_TYPE' => 'L', 'SORT' => 30,
        'VALUES' => ['Тип', 'Производитель', 'Загрузка', 'Назначение', 'Тип нагрева', 'Размер', 'Особенности', 'Страна']],
    ['CODE' => 'IN_BLOCK', 'NAME' => 'Показывать в «Популярных подборках»', 'PROPERTY_TYPE' => 'L', 'LIST_TYPE' => 'C', 'SORT' => 40, 'VALUES' => ['Y']],
    ['CODE' => 'H1', 'NAME' => 'H1', 'PROPERTY_TYPE' => 'S', 'COL_COUNT' => 60, 'SORT' => 50],
    ['CODE' => 'TITLE', 'NAME' => 'Title', 'PROPERTY_TYPE' => 'S', 'COL_COUNT' => 60, 'SORT' => 60],
    ['CODE' => 'DESCRIPTION', 'NAME' => 'Description', 'PROPERTY_TYPE' => 'S', 'ROW_COUNT' => 3, 'COL_COUNT' => 60, 'SORT' => 70],
    ['CODE' => 'SEO_TEXT', 'NAME' => 'SEO-текст (HTML, под товарами)', 'PROPERTY_TYPE' => 'S', 'USER_TYPE' => 'HTML', 'SORT' => 80],
    ['CODE' => 'MIN_COUNT', 'NAME' => 'Мин. товаров для индексации (по умолчанию 3; меньше — noindex и тег скрыт)', 'PROPERTY_TYPE' => 'N', 'SORT' => 90],
];

foreach ($props as $p) {
    if (\CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $p['CODE']])->Fetch()) {
        continue;
    }
    $values = $p['VALUES'] ?? null;
    unset($p['VALUES']);
    $fields = $p + ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'MULTIPLE' => 'N'];
    if ($values) {
        $fields['VALUES'] = [];
        foreach ($values as $i => $v) {
            $fields['VALUES']['n' . $i] = ['VALUE' => $v, 'XML_ID' => $v === 'Y' ? 'Y' : \CUtil::translit($v, 'ru', ['change_case' => 'L', 'replace_space' => '-', 'replace_other' => '-']), 'SORT' => ($i + 1) * 10];
        }
    }
    $id = (int)(new \CIBlockProperty())->Add($fields);
    if (!$id) {
        throw new \RuntimeException("seotags: не создано свойство {$p['CODE']}");
    }
    echo "Свойство {$p['CODE']} (ID=$id)\n";
}

$n = $DB->Query("SELECT COUNT(*) N FROM b_iblock_property WHERE IBLOCK_ID = $iblockId")->Fetch();
if ((int)$n['N'] < count($props)) {
    throw new \RuntimeException('seotags: создано свойств ' . $n['N'] . ' из ' . count($props));
}

});
return true;
