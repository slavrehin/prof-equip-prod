<?php
/**
 * Раннер миграций БД. Запуск только из CLI:
 *   php local/migrations/run.php            — применить все непримененные
 *   php local/migrations/run.php --dry-run  — только показать список, ничего не выполнять
 *
 * Каждая миграция — файл local/migrations/YYYY-MM-DD-описание.php, который:
 *   - сам проверяет, что делать нечего (идемпотентность), и просто ничего не делает в этом случае;
 *   - бросает исключение при ошибке;
 *   - возвращает true при успехе (значение отбрасывается, миграция считается применённой
 *     по самому факту отсутствия исключения — согласно правилу "проверять результат
 *     запросом к базе, а не доверять коду возврата", финальная проверка успеха — дело
 *     самой миграции внутри неё, до того как она отметится как применённая).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Только из командной строки.\n");
    exit(1);
}

// DOCUMENT_ROOT вычисляется из расположения файла — работает одинаково
// на проде (/var/www/wordpress) и в контейнере test3 (/var/www/html).
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2);
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$dryRun = in_array('--dry-run', $argv, true);

global $DB;

function profequip_ensure_migrations_table($DB)
{
    $tableExists = $DB->Query("SHOW TABLES LIKE 'b_profequip_migrations'")->Fetch();
    if (!$tableExists) {
        $DB->Query("
            CREATE TABLE b_profequip_migrations (
                ID INT NOT NULL AUTO_INCREMENT,
                NAME VARCHAR(255) NOT NULL,
                APPLIED_AT DATETIME NOT NULL,
                PRIMARY KEY (ID),
                UNIQUE KEY IX_NAME (NAME)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "Создана таблица b_profequip_migrations.\n";
    }
}

// В --dry-run таблицу не создаём — только читаем, если она уже есть,
// иначе считаем, что применённых миграций нет.
$applied = [];
$tableExists = $DB->Query("SHOW TABLES LIKE 'b_profequip_migrations'")->Fetch();
if ($tableExists) {
    $rs = $DB->Query("SELECT NAME FROM b_profequip_migrations");
    while ($row = $rs->Fetch()) {
        $applied[$row['NAME']] = true;
    }
}

$files = glob(__DIR__ . '/*.php');
sort($files);

$pending = [];
foreach ($files as $file) {
    $name = basename($file);
    if ($name === 'run.php') {
        continue;
    }
    if (isset($applied[$name])) {
        continue;
    }
    $pending[] = $file;
}

if (!$pending) {
    echo "Непримененных миграций нет.\n";
    exit(0);
}

if ($dryRun) {
    echo "Будут применены (--dry-run, ничего не выполнено):\n";
    foreach ($pending as $file) {
        echo "  " . basename($file) . "\n";
    }
    exit(0);
}

profequip_ensure_migrations_table($DB);

foreach ($pending as $file) {
    $name = basename($file);
    echo "=== Применяю: $name ===\n";
    try {
        require $file;

        $nameEsc = $DB->ForSql($name);
        $DB->Query("INSERT INTO b_profequip_migrations (NAME, APPLIED_AT) VALUES ('$nameEsc', NOW())");
        echo "=== OK: $name ===\n\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, "=== ОШИБКА в $name ===\n");
        fwrite(STDERR, $e->getMessage() . "\n");
        fwrite(STDERR, $e->getTraceAsString() . "\n");
        exit(1);
    }
}

echo "Все миграции применены.\n";
