<?php
/**
 * Откат Halton: удаляет 8 товаров halton-* (вместе с их файлами), разделы
 * ventiliruemye-potolki / vytyazhnye-zonty (только если пусты), свойство SPECS (только если
 * ни у кого не заполнено), значение Halton у PROIZVODITEL (если не используется), строку
 * со ссылками и SEO-шаблоны бренда. Бренд Halton и его исходный текст НЕ трогаются.
 * После — удалить запись миграции 2026-09-29-halton-sections-props.php из журнала
 * (см. local/migrations/README.md), если планируется повторное применение.
 *
 *   php local/deploy/halton/rollback.php [--dry]
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'test3.prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');
global $DB;
$dry = in_array('--dry', $argv, true);

$data = json_decode(file_get_contents(__DIR__ . '/products.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($data['products'] as $p) {
    $e = \CIBlockElement::GetList([], ['IBLOCK_ID' => 11, 'CODE' => $p['slug']], false, false, ['ID'])->Fetch();
    if (!$e) { continue; }
    echo ($dry ? '[dry] ' : '') . "удаляю товар {$p['slug']} (ID={$e['ID']})\n";
    if (!$dry && !\CIBlockElement::Delete((int) $e['ID'])) { throw new RuntimeException("Не удалён {$p['slug']}"); }
}
foreach (array_keys($data['sections']) as $code) {
    $s = \CIBlockSection::GetList([], ['IBLOCK_ID' => 11, 'CODE' => $code], true, ['ID'])->Fetch();
    if (!$s) { continue; }
    if ((int) $s['ELEMENT_CNT'] > 0 && !$dry) { echo "раздел $code не пуст — оставлен\n"; continue; }
    echo ($dry ? '[dry] ' : '') . "удаляю раздел $code (ID={$s['ID']})\n";
    if (!$dry) { \CIBlockSection::Delete((int) $s['ID']); }
}
$prop = \CIBlockProperty::GetList([], ['IBLOCK_ID' => 11, 'CODE' => 'SPECS'])->Fetch();
if ($prop) {
    $used = $DB->Query('SELECT COUNT(*) C FROM b_iblock_element_property WHERE IBLOCK_PROPERTY_ID=' . (int) $prop['ID'])->Fetch();
    if ((int) $used['C'] === 0 || $dry) {
        echo ($dry ? '[dry] ' : '') . "удаляю свойство SPECS\n";
        if (!$dry) { \CIBlockProperty::Delete((int) $prop['ID']); }
    }
}
// фасеты фильтра VENT_* (миграция 2026-09-29-halton-filter-props.php) — если ни у кого не заполнены
$rsVent = \CIBlockProperty::GetList([], ['IBLOCK_ID' => 11, 'CODE' => 'VENT_%']);
while ($vp = $rsVent->Fetch()) {
    if (strpos($vp['CODE'], 'VENT_') !== 0) { continue; }
    $used = $DB->Query('SELECT COUNT(*) C FROM b_iblock_element_property WHERE IBLOCK_PROPERTY_ID=' . (int) $vp['ID'])->Fetch();
    if ((int) $used['C'] === 0 || $dry) {
        echo ($dry ? '[dry] ' : '') . "удаляю свойство {$vp['CODE']}\n";
        if (!$dry) { \CIBlockProperty::Delete((int) $vp['ID']); }
    }
}
$pz = \CIBlockProperty::GetList([], ['IBLOCK_ID' => 11, 'CODE' => 'PROIZVODITEL'])->Fetch();
$enum = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $pz['ID'], 'XML_ID' => 'halton'])->Fetch();
if ($enum) {
    $used = $DB->Query('SELECT COUNT(*) C FROM b_iblock_element_property WHERE IBLOCK_PROPERTY_ID=' . (int) $pz['ID'] . ' AND VALUE_ENUM=' . (int) $enum['ID'])->Fetch();
    if ((int) $used['C'] === 0 || $dry) {
        echo ($dry ? '[dry] ' : '') . "удаляю значение Halton у PROIZVODITEL\n";
        if (!$dry) { \CIBlockPropertyEnum::Delete((int) $enum['ID']); }
    }
}
$brand = \CIBlockElement::GetList([], ['IBLOCK_ID' => 14, 'CODE' => 'halton'], false, false, ['ID', 'PREVIEW_TEXT'])->Fetch();
if ($brand && !$dry) {
    $text = preg_replace('~\s*<p>Оборудование Halton в каталоге ПРОФЭКВИП:.*?</p>~su', '', $brand['PREVIEW_TEXT']);
    (new \CIBlockElement())->Update((int) $brand['ID'], ['PREVIEW_TEXT' => $text, 'PREVIEW_TEXT_TYPE' => 'html']);
    (new \Bitrix\Iblock\InheritedProperty\ElementTemplates(14, (int) $brand['ID']))->delete();
    echo "бренд: убраны ссылки на разделы и SEO-шаблоны\n";
}
if (!$dry) {
    foreach (['cache', 'managed_cache', 'stack_cache'] as $d) {
        foreach (glob($_SERVER['DOCUMENT_ROOT'] . "/bitrix/$d/*") ?: [] as $f) { \Bitrix\Main\IO\Directory::deleteDirectory($f); }
    }
    echo "Кэш очищен\n";
}
