<?php
/**
 * Локальная пересборка: теги проектов /portfolio/ (инфоблок 8, свойство-список TYPE).
 *
 * Кнопки-теги под хлебными крошками строятся из значений TYPE (profequip_GetProjectsTypeFilters),
 * фильтрация — local/ajax/portfolio/load.php по ID значения. После импорта у TYPE не было ни одного
 * значения → выводилась только кнопка «Показать все».
 *
 * Источник: снимок прода /portfolio/ из веб-архива (archive/wayback/pages/portfolio/index.html) —
 * 13 тегов в порядке кнопок и data-filter у 116 карточек → data/projects_types_wayback.json.
 * Проектов 2025–2026, которых в снимке нет, — карта $manual ниже (по «Направлению»/«Объекту»/«Локации»
 * из «Кратко о проекте»).
 *
 * Повторный запуск безопасен: значения создаются, если их нет; TYPE перезаписывается только у пустых.
 *   php local/bootstrap/projects_types.php [--dry]
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = 'localhost';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

const PROJECTS_IBLOCK = 8;
$dry = in_array('--dry', $argv, true);

// порядок кнопок как на проде
$tags = ['Гостиница', 'Текстиль', 'Прачечная', 'Ресторан', 'Кухня', 'Санаторий', 'Крым',
    'Краснодарский край', 'Стадион', 'Аквачистка', 'Туннельная прачечная', 'Химия', 'Казино'];

$manual = [
    'enzo-by-zont-hotel-group-moskva-2026' => ['Гостиница', 'Текстиль'],
    'chastnyy-dom-tagankovo-moskovskaya-oblast-2026-god' => ['Кухня'],
    'osnashchenie-chastnogo-doma-krasnodar-2026' => ['Кухня', 'Краснодарский край'],
    'osnashchenie-chastnoy-rezidentsii-barvikha-moskovskaya-oblast-2026-god' => ['Кухня'],
    'osnashchenie-kofemanii-bez-tarelok-moskva-2026-god' => ['Кухня', 'Ресторан'],
    'kofemaniya-shkola-shefov-moskva-novinskiy-bulvar-2026-god' => ['Кухня', 'Ресторан'],
    'apart-otel-idera' => ['Гостиница', 'Прачечная'],
    'restoran-rony-oyster' => ['Ресторан', 'Текстиль', 'Краснодарский край'],   // Геленджик
    'rodina-residences-vladivostok-5' => ['Гостиница', 'Прачечная'],
    'gostevoy-dom-shale-fudzi' => ['Гостиница', 'Текстиль', 'Краснодарский край'], // Красная Поляна
    'kurortnyy-kompleks-palmira-istra-resort' => ['Гостиница', 'Ресторан', 'Текстиль'],
    'golf-klub-tseleevo' => ['Прачечная'],
    'otel-marcus' => ['Гостиница', 'Текстиль'],                                  // униформа
    'park-otel-dubki' => ['Гостиница', 'Прачечная'],
    'dacha-rakhmaninov-5' => ['Гостиница', 'Текстиль', 'Крым'],
    'sanatoriy-rossiya' => ['Санаторий', 'Текстиль'],
    'otel-neboart-ufa' => ['Гостиница', 'Прачечная'],
    'villa-vselug' => ['Гостиница', 'Текстиль'],
];
$wayback = json_decode(file_get_contents(__DIR__ . '/data/projects_types_wayback.json'), true);

$prop = CIBlockProperty::GetList([], ['IBLOCK_ID' => PROJECTS_IBLOCK, 'CODE' => 'TYPE'])->Fetch();
if (!$prop) { exit("нет свойства TYPE\n"); }
$propId = (int)$prop['ID'];

$enumIds = [];
$rs = CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propId]);
while ($e = $rs->Fetch()) { $enumIds[$e['VALUE']] = (int)$e['ID']; }
$enum = new CIBlockPropertyEnum();
foreach ($tags as $i => $tag) {
    if (isset($enumIds[$tag])) { continue; }
    echo "+ тег $tag\n";
    if ($dry) { $enumIds[$tag] = -1; continue; }
    $enumIds[$tag] = (int)$enum->Add([
        'PROPERTY_ID' => $propId, 'VALUE' => $tag, 'SORT' => ($i + 1) * 10,
        'XML_ID' => CUtil::translit($tag, 'ru', ['replace_space' => '-', 'replace_other' => '-']),
    ]);
}

$stats = ['set' => 0, 'had' => 0, 'none' => 0];
$res = CIBlockElement::GetList(['ID' => 'ASC'], ['IBLOCK_ID' => PROJECTS_IBLOCK], false, false, ['ID', 'CODE']);
while ($el = $res->Fetch()) {
    $cur = CIBlockElement::GetProperty(PROJECTS_IBLOCK, $el['ID'], [], ['CODE' => 'TYPE'])->Fetch();
    if ($cur && !empty($cur['VALUE'])) { $stats['had']++; continue; }
    $list = $manual[$el['CODE']] ?? $wayback[$el['CODE']] ?? null;
    if (!$list) { $stats['none']++; echo "  нет тегов: {$el['CODE']}\n"; continue; }
    $ids = array_values(array_filter(array_map(fn($t) => $enumIds[$t] ?? 0, $list)));
    $stats['set']++;
    if (!$dry) { CIBlockElement::SetPropertyValuesEx($el['ID'], PROJECTS_IBLOCK, ['TYPE' => $ids]); }
}
if (!$dry) {
    \CIBlock::clearIblockTagCache(PROJECTS_IBLOCK);
    BXClearCache(true, '/');
}
echo json_encode($stats), "\n";
