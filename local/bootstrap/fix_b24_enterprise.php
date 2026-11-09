<?php
/**
 * Разово после import_content.php --data=content_b24.json (до правки импорта 2026-11-07):
 * «Тип предприятия» из Bitrix24 записался одним вариантом списка через запятую
 * («Коммерческая прачечная, Прачечная в гостинице, …»), и фильтр показывал эту строку как один пункт.
 * Скрипт разбивает такие варианты на стандартные значения, переписывает товары и удаляет склеенные варианты.
 * Затем для этих товаров запустить пересчёт по правилам:
 *   IDS=<список> php local/deploy/v_nalichii_2026_10_15/enterprise_rules_test3.php
 * Запуск: docker compose exec -T web php /var/www/html/local/bootstrap/fix_b24_enterprise.php [--dry]
 */
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');
$dry = in_array('--dry', $argv, true);

$prop = \CIBlockProperty::GetList([], ['CODE' => 'TIP_PREDPRIYATIYA'])->Fetch();
if (!$prop) throw new RuntimeException('Нет свойства TIP_PREDPRIYATIYA');
$pid = (int) $prop['ID'];
$iblockId = (int) $prop['IBLOCK_ID'];

$byValue = [];
$glued = [];
$r = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $pid]);
while ($e = $r->Fetch()) {
    if (str_contains($e['VALUE'], ',')) $glued[(int) $e['ID']] = $e['VALUE'];
    else $byValue[mb_strtolower($e['VALUE'])] = (int) $e['ID'];
}
if (!$glued) { echo "Склеенных вариантов нет\n"; return; }

$parts = function (string $value) use ($byValue): array {
    $ids = [];
    foreach (preg_split('/,\s*/u', $value) as $t) {
        $t = trim($t);
        if ($t === '') continue;
        $t = mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
        $t = CatalogStandard::VALUE_MAP['TIP_PREDPRIYATIYA'][$t] ?? $t;
        $id = $byValue[mb_strtolower($t)] ?? null;
        if (!$id) throw new RuntimeException("Нет стандартного значения «{$t}»");
        $ids[$id] = $id;
    }
    return array_values($ids);
};

$touched = [];
foreach ($glued as $gid => $value) {
    $rs = \CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, 'PROPERTY_TIP_PREDPRIYATIYA' => $gid], false, false, ['ID']);
    while ($el = $rs->Fetch()) {
        $eid = (int) $el['ID'];
        $cur = [];
        $rv = \CIBlockElement::GetProperty($iblockId, $eid, [], ['ID' => $pid]);
        while ($v = $rv->Fetch()) if ($v['VALUE']) $cur[] = (int) $v['VALUE'];
        $new = [];
        foreach ($cur as $c) foreach (isset($glued[$c]) ? $parts($glued[$c]) : [$c] as $x) $new[$x] = $x;
        echo "товар $eid: " . implode(',', $cur) . ' → ' . implode(',', $new) . "\n";
        if (!$dry) \CIBlockElement::SetPropertyValuesEx($eid, $iblockId, ['TIP_PREDPRIYATIYA' => array_values($new)]);
        $touched[$eid] = $eid;
    }
}
foreach ($glued as $gid => $value) {
    echo "удаляю вариант $gid «{$value}»\n";
    if (!$dry && !\CIBlockPropertyEnum::Delete($gid)) throw new RuntimeException("Вариант $gid не удалён");
}
if (!$dry) \CIBlock::clearIblockTagCache($iblockId);
echo 'IDS=' . implode(',', $touched) . "\n";
