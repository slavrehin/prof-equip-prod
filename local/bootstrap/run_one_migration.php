<?php
// Применяет ОДНУ миграцию так же, как local/migrations/run.php (та же таблица b_profequip_migrations).
// Нужен для хронологического проигрыша истории (replay.sh): миграции вперемешку со скриптами local/deploy.
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
$_SERVER['SERVER_NAME'] = 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
global $DB;
$__mig = basename($argv[1]);
if (!$DB->Query("SHOW TABLES LIKE 'b_profequip_migrations'")->Fetch()) {
    $DB->Query("CREATE TABLE b_profequip_migrations (ID INT NOT NULL AUTO_INCREMENT, NAME VARCHAR(255) NOT NULL,
        APPLIED_AT DATETIME NOT NULL, PRIMARY KEY (ID), UNIQUE KEY IX_NAME (NAME)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
if ($DB->Query("SELECT 1 FROM b_profequip_migrations WHERE NAME='" . $DB->ForSql($__mig) . "'")->Fetch()) {
    echo "уже применена: $__mig\n";
    exit(0);
}
try {
    require $_SERVER['DOCUMENT_ROOT'] . '/local/migrations/' . $__mig;
    $DB->Query("INSERT INTO b_profequip_migrations (NAME, APPLIED_AT) VALUES ('" . $DB->ForSql($__mig) . "', NOW())");
    echo "=== OK: $__mig ===\n";
} catch (\Throwable $__e) {
    fwrite(STDERR, "=== ОШИБКА в $__mig: " . $__e->getMessage() . "\n" . $__e->getFile() . ':' . $__e->getLine() . "\n");
    exit(1);
}
