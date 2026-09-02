#!/usr/bin/env bash
# Откат последнего деплоя. Требует явного подтверждения.
#
#   local/deploy/rollback.sh                  — откат к последнему бэкапу деплоя
#   local/deploy/rollback.sh deploy-2026-09-01-2130   — откат к конкретному бэкапу
#
# Откатывает код (git reset --hard к коммиту ДО того деплоя — только если нет
# незакоммиченных правок) и базу (полная перезаливка дампа того бэкапа).
# Ничего не делает без интерактивного "да".

set -uo pipefail

SITE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$SITE_ROOT"

BACKUP_ROOT=/backup

if [ -n "${1:-}" ]; then
    BACKUP_DIR="$BACKUP_ROOT/$1"
else
    BACKUP_DIR="$(ls -1dt "$BACKUP_ROOT"/deploy-* 2>/dev/null | head -1)"
fi

if [ -z "$BACKUP_DIR" ] || [ ! -d "$BACKUP_DIR" ]; then
    echo "Бэкап не найден: ${BACKUP_DIR:-<нет ни одного deploy-бэкапа>}"
    exit 1
fi

if [ ! -f "$BACKUP_DIR/commit.txt" ] || [ ! -f "$BACKUP_DIR/db.sql.gz" ]; then
    echo "В $BACKUP_DIR нет commit.txt или db.sql.gz — это не полный бэкап деплоя"
    exit 1
fi

TARGET_COMMIT="$(cat "$BACKUP_DIR/commit.txt")"
CURRENT_COMMIT="$(git rev-parse HEAD)"

echo "=== План отката ==="
echo "Бэкап:               $BACKUP_DIR"
echo "Текущий коммит:      $CURRENT_COMMIT"
echo "Коммит к откату:     $TARGET_COMMIT"
echo "База будет ПОЛНОСТЬЮ перезалита из: $BACKUP_DIR/db.sql.gz"
echo ""

if [ -n "$(git status --porcelain)" ]; then
    echo "На проде есть незакоммиченные изменения в отслеживаемых файлах:"
    git status --porcelain
    echo "Откат кода НЕ будет выполнен (git reset --hard стёр бы эти правки)."
    echo "База данных тоже не будет тронута, пока это не разрешено руками."
    exit 1
fi

read -r -p "Продолжить? Это необратимо. Введите 'да' для подтверждения: " CONFIRM
if [ "$CONFIRM" != "да" ]; then
    echo "Отменено."
    exit 1
fi

echo "Откат кода к $TARGET_COMMIT"
git reset --hard "$TARGET_COMMIT"

echo "Перезаливка базы из $BACKUP_DIR/db.sql.gz"
DOCUMENT_ROOT="$SITE_ROOT" php -r '
$settings = include $argv[1] . "/bitrix/.settings.php";
$c = $settings["connections"]["value"]["default"];
$link = mysqli_connect($c["host"], $c["login"], $c["password"], $c["database"]);
if (!$link) { fwrite(STDERR, "connect failed: " . mysqli_connect_error() . "\n"); exit(1); }
mysqli_report(MYSQLI_REPORT_OFF);

// Дроп ВСЕХ текущих таблиц перед restore: таблицы, созданные после снятия
// бэкапа (например, при первом запуске миграций b_profequip_migrations),
// иначе переживают откат и рассинхронизируются с реально восстановленными
// данными — раннер миграций посчитает такую миграцию уже применённой.
mysqli_query($link, "SET FOREIGN_KEY_CHECKS=0");
$existingTables = [];
$tr = mysqli_query($link, "SHOW TABLES");
while ($row = mysqli_fetch_row($tr)) {
    $existingTables[] = $row[0];
}
foreach ($existingTables as $t) {
    mysqli_query($link, "DROP TABLE IF EXISTS `" . str_replace("`", "``", $t) . "`");
}

$sql = "";
$fh = popen("gzip -dc " . escapeshellarg($argv[2]), "r");
while (!feof($fh)) {
    $sql .= fread($fh, 1024 * 1024);
}
pclose($fh);
// mysqli_multi_query — разбор statement-ов делает сам MySQL, а не наивный
// split по ";\n" (в текстовых полях запросто может быть точка с запятой).
if (!mysqli_multi_query($link, $sql)) {
    fwrite(STDERR, "SQL error: " . mysqli_error($link) . "\n");
    exit(1);
}
do {
    if ($res = mysqli_store_result($link)) {
        mysqli_free_result($res);
    }
    if (mysqli_errno($link)) {
        fwrite(STDERR, "SQL error: " . mysqli_error($link) . "\n");
        exit(1);
    }
} while (mysqli_next_result($link));
echo "База восстановлена.\n";
' "$SITE_ROOT" "$BACKUP_DIR/db.sql.gz"

echo "Сброс кэша"
rm -rf bitrix/cache/* bitrix/managed_cache/* bitrix/stack_cache/* 2>/dev/null || true
chown -R devuser:www-data "$SITE_ROOT/local" "$SITE_ROOT/bitrix/cache" "$SITE_ROOT/bitrix/managed_cache" "$SITE_ROOT/bitrix/stack_cache" 2>/dev/null || true

echo "=== Откат завершён: код на $TARGET_COMMIT, база из $BACKUP_DIR ==="
echo "Проверьте вручную: local/deploy/smoke.sh"
