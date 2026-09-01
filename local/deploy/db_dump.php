<?php
/**
 * Дамп боевой БД через mysqli, в stdout (SQL). Используется deploy.sh и rollback.sh.
 *
 * На этом сервере CLI mysqldump не проходит аутентификацию с рабочими учётными
 * данными (проверено: тот же логин/пароль из .settings.php через mysqli подключается
 * нормально, а через mysql/mysqldump CLI — Access denied при любых комбинациях
 * сокет/TCP/SSL). Причина не выяснена, поэтому дамп сделан напрямую через mysqli.
 *
 * Использование:
 *   php local/deploy/db_dump.php | gzip > backup.sql.gz
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Только из командной строки.\n");
    exit(1);
}

$documentRoot = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2);
$settings = include $documentRoot . '/bitrix/.settings.php';
$c = $settings['connections']['value']['default'];

$link = mysqli_connect($c['host'], $c['login'], $c['password'], $c['database']);
if (!$link) {
    fwrite(STDERR, "Connect failed: " . mysqli_connect_error() . "\n");
    exit(1);
}
mysqli_set_charset($link, 'utf8mb4');
mysqli_query($link, "SET FOREIGN_KEY_CHECKS=0");

echo "-- Dump of {$c['database']} via mysqli, " . date('c') . "\n";
echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

$tablesRes = mysqli_query($link, "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
$tables = [];
while ($row = mysqli_fetch_array($tablesRes)) {
    $tables[] = $row[0];
}
fwrite(STDERR, "Tables to dump: " . count($tables) . "\n");

foreach ($tables as $table) {
    $tEsc = "`" . str_replace("`", "``", $table) . "`";

    echo "\n-- ----------------------------\n-- Table: $table\n-- ----------------------------\n";
    echo "DROP TABLE IF EXISTS $tEsc;\n";
    $createRow = mysqli_fetch_assoc(mysqli_query($link, "SHOW CREATE TABLE $tEsc"));
    echo $createRow['Create Table'] . ";\n\n";

    $colsRes = mysqli_query($link, "SHOW COLUMNS FROM $tEsc");
    $cols = [];
    $isBinary = [];
    while ($col = mysqli_fetch_assoc($colsRes)) {
        $cols[] = $col['Field'];
        $type = strtolower($col['Type']);
        $isBinary[$col['Field']] = (strpos($type, 'blob') !== false || strpos($type, 'binary') !== false);
    }
    $colListEsc = implode(',', array_map(fn($c2) => "`" . str_replace("`", "``", $c2) . "`", $cols));

    $countRow = mysqli_fetch_row(mysqli_query($link, "SELECT COUNT(*) FROM $tEsc"));
    $total = (int)$countRow[0];
    fwrite(STDERR, "  $table: $total rows\n");
    if ($total === 0) {
        continue;
    }

    $batchSize = 500;
    for ($offset = 0; $offset < $total; $offset += $batchSize) {
        $res = mysqli_query($link, "SELECT * FROM $tEsc LIMIT $batchSize OFFSET $offset");
        $rowsSql = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $vals = [];
            foreach ($cols as $col) {
                $v = $row[$col];
                if ($v === null) {
                    $vals[] = 'NULL';
                } elseif (is_numeric($v) && !$isBinary[$col] && (string)(float)$v === $v) {
                    $vals[] = $v;
                } else {
                    $vals[] = "'" . mysqli_real_escape_string($link, $v) . "'";
                }
            }
            $rowsSql[] = '(' . implode(',', $vals) . ')';
        }
        if ($rowsSql) {
            echo "INSERT INTO $tEsc ($colListEsc) VALUES\n" . implode(",\n", $rowsSql) . ";\n";
        }
    }
}

echo "\nSET FOREIGN_KEY_CHECKS=1;\n";
fwrite(STDERR, "Done.\n");
