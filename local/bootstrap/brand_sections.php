<?php
/**
 * Локальная пересборка: разделы инфоблока «Бренды» (14) для страницы /brends/.
 *
 * Шаблон news.list/brands-list (result_modifier) выводит бренды ТОЛЬКО сгруппированными по
 * разделам инфоблока; бренды без раздела не показываются. Пересобранная база создавала бренды
 * без разделов, поэтому страница выдавала «Бренды не найдены».
 *
 * Данные: data/brand_sections.json — 6 разделов и их бренды со снимка прода в веб-архиве
 * (порядок разделов как на проде) и бренды, добавленные после снимка (поле "added").
 * Создаёт недостающие разделы и привязывает бренд к разделу (один раздел на бренд).
 * Повторный запуск безопасен.
 *
 *   php local/bootstrap/brand_sections.php [--dry]
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = 'localhost';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

const BRANDS_IBLOCK = 14;
$dry = in_array('--dry', $argv, true);
$data = json_decode(file_get_contents(__DIR__ . '/data/brand_sections.json'), true);

$brands = [];
$res = CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK], false, false, ['ID', 'CODE', 'IBLOCK_SECTION_ID']);
while ($b = $res->Fetch()) { $brands[$b['CODE']] = $b; }

$bs = new CIBlockSection();
$stats = ['sections_new' => 0, 'linked' => 0, 'already' => 0, 'missing' => []];
$assigned = [];
foreach ($data['sections'] as $s) {
    $sec = CIBlockSection::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK, '=CODE' => $s['code']], false, ['ID'])->Fetch();
    $sid = $sec['ID'] ?? 0;
    if (!$sid) {
        $stats['sections_new']++;
        if (!$dry) {
            $sid = $bs->Add(['IBLOCK_ID' => BRANDS_IBLOCK, 'NAME' => $s['name'], 'CODE' => $s['code'],
                'SORT' => $s['sort'], 'ACTIVE' => 'Y']);
            if (!$sid) { fwrite(STDERR, "section {$s['code']}: {$bs->LAST_ERROR}\n"); exit(1); }
        }
    } elseif (!$dry) {
        $bs->Update($sid, ['NAME' => $s['name'], 'SORT' => $s['sort']]);
    }
    foreach (array_merge($s['wayback'], $s['added']) as $code) {
        if (isset($assigned[$code])) { continue; }
        $assigned[$code] = true;
        if (!isset($brands[$code])) { $stats['missing'][] = $code; continue; }
        if ((int)$brands[$code]['IBLOCK_SECTION_ID'] === (int)$sid && $sid) { $stats['already']++; continue; }
        $stats['linked']++;
        if (!$dry) { CIBlockElement::SetElementSection($brands[$code]['ID'], [$sid]); }
    }
}
$stats['unassigned'] = array_values(array_diff(array_keys($brands), array_keys($assigned)));
if (!$dry) { CIBlock::clearIblockTagCache(BRANDS_IBLOCK); BXClearCache(true, '/s1/bitrix/news.list/'); }
echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
