#!/usr/bin/env bash
# Smoke-проверка публичной части сайта. Возвращает 0 если всё зелёное, 1 иначе.
# SMOKE_BASE_URL задаёт окружение, SMOKE_AUTH — необязательные "user:password" Basic Auth.

set -uo pipefail

SITE_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
URLS_FILE="$SITE_ROOT/local/deploy/smoke-urls.txt"
BASE_URL="${SMOKE_BASE_URL:-https://prof-equip.ru}"
BODY_FILE="$(mktemp "${TMPDIR:-/tmp}/profequip-smoke.XXXXXX")"
trap 'rm -f "$BODY_FILE"' EXIT
CURL_ARGS=(-sS --max-time "${SMOKE_TIMEOUT:-60}")
if [ -n "${SMOKE_AUTH:-}" ]; then
    CURL_ARGS+=(--user "$SMOKE_AUTH")
fi

FAILED=0

check_url() {
    local path="$1"
    local url="$BASE_URL$path"
    local code

    code="$(curl "${CURL_ARGS[@]}" -o "$BODY_FILE" -w '%{http_code}' "$url")"

    if [ "$code" != "200" ]; then
        echo "FAIL  [$code]  $url"
        FAILED=1
        return
    fi

    if [ ! -s "$BODY_FILE" ]; then
        echo "FAIL  [200, пустой ответ]  $url"
        FAILED=1
    elif grep -q '<title>Title</title>' "$BODY_FILE"; then
        echo "FAIL  [200, но <title>Title</title> — заглушка Bitrix]  $url"
        FAILED=1
    elif grep -qi 'Description</' "$BODY_FILE"; then
        echo "FAIL  [200, но встречается литеральный Description — заглушка Bitrix]  $url"
        FAILED=1
    else
        echo "OK    [200]  $url"
    fi
}

if [ ! -f "$URLS_FILE" ]; then
    echo "Нет файла $URLS_FILE"
    exit 1
fi

while IFS= read -r path || [ -n "$path" ]; do
    [ -z "$path" ] && continue
    [[ "$path" == \#* ]] && continue
    check_url "$path"
done < "$URLS_FILE"

echo ""
echo "Свежие фатальные ошибки в bitrix/php_interface (если есть лог):"
if [ -f "$SITE_ROOT/error.log" ]; then
    RECENT_ERRORS="$(tail -n 200 "$SITE_ROOT/error.log" | grep -i 'fatal error' || true)"
    if [ -n "$RECENT_ERRORS" ]; then
        echo "$RECENT_ERRORS"
        echo "FAIL  свежие fatal error в error.log"
        FAILED=1
    else
        echo "OK    фатальных ошибок не найдено"
    fi
fi

exit "$FAILED"
