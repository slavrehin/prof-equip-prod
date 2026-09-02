#!/usr/bin/env bash
# Деплой прода из git. Запускать на проде из корня сайта или откуда угодно —
# скрипт сам определяет корень по своему расположению.
#
#   local/deploy/deploy.sh
#
# Останавливается при первой ошибке (set -euo pipefail). Ничего не делает,
# если в отслеживаемых файлах есть незакоммиченные правки — сначала
# разберитесь, откуда они взялись.

set -euo pipefail

SITE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$SITE_ROOT"

LOG_FILE=/var/log/prof-equip-deploy.log
BACKUP_ROOT=/backup
STAMP="$(date +%F-%H%M)"
BACKUP_DIR="$BACKUP_ROOT/deploy-$STAMP"
OWNER="devuser:www-data"

log() {
    echo "[$(date '+%F %T')] $*" | tee -a "$LOG_FILE"
}

fail() {
    log "ОШИБКА: $*"
    exit 1
}

log "=== Деплой начат ==="

# 1. Проверка чистоты рабочей копии
if [ -n "$(git status --porcelain)" ]; then
    echo "На проде есть незакоммиченные изменения в отслеживаемых файлах:"
    git status --porcelain
    fail "остановлено — разберитесь, откуда правки, прежде чем деплоить"
fi

PREV_COMMIT="$(git rev-parse HEAD)"
log "Текущий коммит перед деплоем: $PREV_COMMIT"

# 2. Бэкап (БД + /local/) до каких-либо изменений
mkdir -p "$BACKUP_DIR"
log "Бэкап в $BACKUP_DIR"
php "$SITE_ROOT/local/deploy/db_dump.php" 2>>"$LOG_FILE" | gzip > "$BACKUP_DIR/db.sql.gz" \
    || fail "не удалось снять дамп БД"
tar -czf "$BACKUP_DIR/local.tar.gz" -C "$SITE_ROOT" local \
    || fail "не удалось заархивировать /local/"
echo "$PREV_COMMIT" > "$BACKUP_DIR/commit.txt"

gzip -t "$BACKUP_DIR/db.sql.gz" || fail "дамп БД повреждён сразу после снятия"
tar -tzf "$BACKUP_DIR/local.tar.gz" >/dev/null || fail "архив /local/ повреждён сразу после создания"
log "Бэкап снят и проверен"

# Храним только последние 10 бэкапов деплоя
ls -1dt "$BACKUP_ROOT"/deploy-* 2>/dev/null | tail -n +11 | xargs -r rm -rf

# 3. Обновление кода — только fast-forward
log "git fetch"
git fetch origin
if ! git merge --ff-only origin/master; then
    fail "git merge --ff-only не прошёл — история разошлась, слияние руками, не автоматически"
fi
NEW_COMMIT="$(git rev-parse HEAD)"
log "Код обновлён: $PREV_COMMIT -> $NEW_COMMIT"

# 4. Миграции
log "Миграции БД"
if ! php "$SITE_ROOT/local/migrations/run.php" 2>&1 | tee -a "$LOG_FILE"; then
    fail "миграция не прошла — код уже обновлён (commit $NEW_COMMIT), база — смотрите вывод выше. Откат: local/deploy/rollback.sh $STAMP"
fi

# 5. Сброс кэша (после файлов и миграций, не раньше)
log "Сброс кэша"
rm -rf bitrix/cache/* bitrix/managed_cache/* bitrix/stack_cache/* 2>/dev/null || true

# 6. Права на файлы
log "Владелец файлов -> $OWNER"
chown -R "$OWNER" "$SITE_ROOT/local" "$SITE_ROOT/bitrix/cache" "$SITE_ROOT/bitrix/managed_cache" "$SITE_ROOT/bitrix/stack_cache" 2>/dev/null || true

# 7. Smoke-проверка
log "Smoke-проверка"
if ! "$SITE_ROOT/local/deploy/smoke.sh"; then
    log "!!! SMOKE-ПРОВЕРКА ПРОВАЛЕНА !!!"
    log "!!! Откат: $SITE_ROOT/local/deploy/rollback.sh $STAMP !!!"
    fail "smoke-проверка не прошла после деплоя $NEW_COMMIT"
fi

log "=== Деплой $NEW_COMMIT завершён успешно ==="
