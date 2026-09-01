<?php
/**
 * CLI-обёртка для запуска импорта без браузера, напр.:
 *   docker compose exec web php /var/www/html/local/modules/profequip.import/cli/run_import.php /path/to/file.csv
 */

// DOCUMENT_ROOT вычисляется из расположения файла, чтобы не хардкодить
// разный путь на проде (/var/www/wordpress) и в контейнере test3 (/var/www/html).
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 4);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/modules/profequip.import/lib/ProductImporter.php');

use ProfEquip\Import\ProductImporter;

$csvPath = $argv[1] ?? null;
if (!$csvPath || !is_readable($csvPath)) {
    fwrite(STDERR, "Использование: php run_import.php /путь/к/файлу.csv\n");
    exit(1);
}

$importer = new ProductImporter();
$result = $importer->importFile($csvPath);

echo "=== Итог ===\n";
printf(
    "Всего строк: %d, создано: %d, обновлено: %d, ошибок: %d\n",
    $result['summary']['total'],
    $result['summary']['created'],
    $result['summary']['updated'],
    $result['summary']['errors']
);
echo "\n=== Детально ===\n";
foreach ($result['log'] as $entry) {
    printf(
        "row=%-3d name=%-45s status=%-8s id=%-6s %s\n",
        $entry['row'],
        mb_substr($entry['name'], 0, 45),
        $entry['status'],
        $entry['id'] ?? '-',
        $entry['message']
    );
}

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
