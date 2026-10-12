<?php
/**
 * Заполняет «Тип» (TIP_TOVARA) всем товарам каталога по их разделам
 * (логика — ProductType::resolve() в local/php_interface/include/product_type.php).
 *
 *   php local/deploy/backfill_tip_tovara.php            # dry-run: только отчёт
 *   php local/deploy/backfill_tip_tovara.php --apply    # записать пустые значения
 *   php local/deploy/backfill_tip_tovara.php --apply --overwrite  # пересчитать все
 *
 * На test3: docker compose exec web php /var/www/html/local/deploy/backfill_tip_tovara.php
 * Требует миграцию 2026-10-12-tip-tovara-property.php. Идемпотентен.
 */

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('iblock');

$apply = in_array('--apply', $argv, true);
$overwrite = in_array('--overwrite', $argv, true);

$iblockId = (int)GetIBlockIDByCode('catalog');
$propertyId = ProductType::propertyId($iblockId);
if (!$propertyId) {
    fwrite(STDERR, "Нет свойства TIP_TOVARA — сначала local/migrations/run.php\n");
    exit(1);
}

$stats = ['set' => 0, 'kept' => 0, 'unresolved' => 0];
$byType = [];
$unresolved = [];

$rs = CIBlockElement::GetList(['ID' => 'ASC'], ['IBLOCK_ID' => $iblockId], false, false, ['ID', 'NAME']);
while ($el = $rs->Fetch()) {
    $id = (int)$el['ID'];
    $type = ProductType::resolve($id);
    if ($type === null) {
        $stats['unresolved']++;
        $unresolved[] = "$id {$el['NAME']}";
        continue;
    }
    $byType[$type] = ($byType[$type] ?? 0) + 1;

    $current = ProductType::currentEnumId($iblockId, $id);
    if ($current && !$overwrite) {
        $stats['kept']++;
        continue;
    }
    if ($apply) {
        ProductType::apply($iblockId, $id, $overwrite);
    }
    $stats['set']++;
}

ksort($byType);
foreach ($byType as $type => $n) {
    printf("%5d  %s\n", $n, $type);
}
echo "\n" . ($apply ? 'Записано' : 'Будет записано (dry-run)') . ": {$stats['set']}, уже было: {$stats['kept']}, без типа: {$stats['unresolved']}\n";
foreach ($unresolved as $line) {
    echo "  без разделов: $line\n";
}

if ($apply) {
    // проверка базой: сколько товаров реально имеют значение
    global $DB;
    $n = $DB->Query("SELECT COUNT(DISTINCT IBLOCK_ELEMENT_ID) C FROM b_iblock_element_property WHERE IBLOCK_PROPERTY_ID = $propertyId")->Fetch();
    echo "В базе с TIP_TOVARA: {$n['C']}\n";
    \CIBlock::clearIblockTagCache($iblockId);
}
