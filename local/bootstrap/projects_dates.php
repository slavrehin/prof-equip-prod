<?php
/**
 * Локальная пересборка: даты проектов (инфоблок 8) для сортировки «от новых к старым».
 *
 * После импорта у всех проектов SORT=500 и пустой ACTIVE_FROM — порядок в /portfolio/ случайный,
 * а навигация «Предыдущая/Следующая» (news-detail-nav) и «Другие объекты» (projects-slider)
 * сортируют именно по ACTIVE_FROM.
 *
 * ACTIVE_FROM = год реализации проекта:
 *   1) LIST «Год реализации» / «Год выполнения проекта» / «Начало (Период) сотрудничества» — последний год;
 *   2) год в названии или символьном коде (для диапазона «2014-2017» — последний);
 *   3) явная карта ниже — по папкам wp-content/uploads/<год>/ на WP-странице проекта (веб-архив).
 * Если в ACTIVE_FROM уже стоит дата этого же года (например «Конгресс отель» 05.09.2023) — не трогаем.
 * Внутри одного года время уникальное (news-detail-nav сравнивает строго < / >): позже добавленный
 * проект выше; MANTERA (Б24 #8847: «мантера и следующие за ней проекты не должны уйти вниз») — первым в своём году.
 *
 *   php local/bootstrap/projects_dates.php [--dry]
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

// год по WP-странице (веб-архив /project/<code>/): папки uploads с фото проекта
$knownYears = [
    'avstriyskiy-tsentr-zdorovya-verba-mayr-moskovskaya-oblast-g-pushkino' => 2019, // WP-код …-2019
    'kosmos-paveleckaya'           => 2023, // uploads/2023/09
    'mantera-supreme-seaside-5'    => 2025, // uploads/2025/03
    'mantera-supreme-seaside-5-2'  => 2025,
    'moscow-marriott-imperial-plaza' => 2025,
    'otel-ruma'                    => 2025,
    'riviera-wellness-resort'      => 2025,
    'vladivostok-grand-hotel-spa'  => 2025,
];
$pinnedFirst = ['mantera-supreme-seaside-5', 'mantera-supreme-seaside-5-2'];

function lastYear(string $s): ?int {
    if (!preg_match_all('~(?<!\d)(19[89]\d|20[0-3]\d)(?!\d)~', $s, $m)) { return null; }
    return max(array_map('intval', $m[1]));
}

$items = [];
$res = CIBlockElement::GetList(['ID' => 'ASC'], ['IBLOCK_ID' => PROJECTS_IBLOCK], false, false,
    ['ID', 'CODE', 'NAME', 'ACTIVE_FROM']);
while ($el = $res->Fetch()) {
    $year = null; $src = '';
    $pr = CIBlockElement::GetProperty(PROJECTS_IBLOCK, $el['ID'], [], ['CODE' => 'LIST']);
    $listYears = [];
    while ($p = $pr->Fetch()) {
        if (preg_match('~год|сотрудничеств~iu', (string)$p['DESCRIPTION']) && ($y = lastYear((string)$p['VALUE']))) {
            $listYears[] = $y;
        }
    }
    if ($listYears) { $year = max($listYears); $src = 'LIST'; }
    elseif ($y = lastYear($el['NAME'] . ' ' . $el['CODE'])) { $year = $y; $src = 'name/code'; }
    elseif (isset($knownYears[$el['CODE']])) { $year = $knownYears[$el['CODE']]; $src = 'wp uploads'; }
    $items[] = $el + ['YEAR' => $year, 'SRC' => $src];
}

// уникальное время внутри года: закреплённые — последними по времени (т.е. первыми в DESC), далее по ID
usort($items, function ($a, $b) use ($pinnedFirst) {
    $pa = in_array($a['CODE'], $pinnedFirst, true) ? 1 : 0;
    $pb = in_array($b['CODE'], $pinnedFirst, true) ? 1 : 0;
    return [$a['YEAR'], $pa, (int)$a['ID']] <=> [$b['YEAR'], $pb, (int)$b['ID']];
});
$el = new CIBlockElement();
$slot = [];
$changed = 0;
foreach ($items as $it) {
    if (!$it['YEAR']) { echo "#{$it['ID']} {$it['CODE']}: год не найден, пропуск\n"; continue; }
    $y = $it['YEAR'];
    if ($it['ACTIVE_FROM'] && (int)date('Y', MakeTimeStamp($it['ACTIVE_FROM'])) === $y
        && date('m-d', MakeTimeStamp($it['ACTIVE_FROM'])) !== '01-01') {
        echo "#{$it['ID']} {$it['CODE']}: {$it['ACTIVE_FROM']} (оставлено)\n";
        continue;
    }
    $slot[$y] = ($slot[$y] ?? 0) + 1;
    $date = ConvertTimeStamp(mktime(0, $slot[$y], 0, 1, 1, $y), 'FULL');   // 01.01.Y 00:0N:00
    printf("#%d %s: %d (%s) → %s\n", $it['ID'], $it['CODE'], $y, $it['SRC'], $date);
    if (!$dry && $it['ACTIVE_FROM'] !== $date) {
        if (!$el->Update($it['ID'], ['ACTIVE_FROM' => $date], false, false, false, false)) {
            echo "  ошибка: {$el->LAST_ERROR}\n";
        } else { $changed++; }
    }
}
if (!$dry) {
    \CIBlock::clearIblockTagCache(PROJECTS_IBLOCK);
    BXClearCache(true, '/');
}
echo "updated: $changed\n";
