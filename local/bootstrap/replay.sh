#!/bin/bash
# Хронологический проигрыш истории сентября–октября 2026 поверх восстановленного контента:
# миграции local/migrations (по одной) вперемешку со скриптами local/deploy по датам добавления в git.
# Миграция упала -> стоп. Скрипт упал -> откладывается и повторяется после следующей миграции.
# Запуск из site/:  bash www/local/bootstrap/replay.sh 2>&1 | tee replay.log
cd "$(dirname "$0")/../../.." || exit 1
X() { docker compose exec -T web php "$@" < /dev/null; }
SCRIPTS="
2026-09-02 local/deploy/seed_seo_filter_rules.php
2026-09-04 local/deploy/backfill_tip_zapchasti_slugs.php
2026-09-21 local/deploy/hoshizaki_icemakers/import.php
2026-09-21 local/deploy/seed_section_seo_texts.php --force
2026-09-21 local/deploy/fix_section_meta.php
2026-09-28 local/deploy/landing_prachechnaya/import.php
2026-09-29 local/deploy/halton/import.php
2026-09-30 local/deploy/catalog_fixes_2026_09_30.php
2026-10-02 local/deploy/sayl/import.php
2026-10-05 local/deploy/tolon_parts_2026_10_05/import.php
2026-10-08 local/deploy/hobart_dishwashers/import.php --photos=/tmp/hobart_photos --created=/tmp/hobart_created.json
2026-10-12 local/deploy/backfill_tip_tovara.php --apply
2026-10-13 local/deploy/hobart_conveyor/import.php --photos=/tmp/hobart_conveyor_photos --created=/tmp/hobart_conveyor_created.json
# 2026-10-13 tolon_photos_2026_10_13 — НЕ запускаем: фото с погибшего сервера, заменило бы архивные фото TOLON заглушками
2026-10-16 local/deploy/v_nalichii_2026_10_15/import.php --photos=/tmp/v_puti_photos --log=/tmp/v_nalichii_rollback.json
2026-10-16 local/deploy/landing_tekstil/import.php
2026-10-20 local/deploy/catalog_restructure/seed_tags.php --apply
2026-11-01 local/deploy/catalog_seo/seed_faq.php
"
EVENTS=$( { for f in www/local/migrations/2026-*.php; do b=$(basename "$f"); p=0; [ "$b" = 2026-10-08-dishwasher-type-winterhalter.php ] && p=0.5; echo "${b:0:10} $p M $b"; done
            # winterhalter по алфавиту раньше hobart, но свойство TIP_POSUDOMOECHNOJ_MASHINY создаёт hobart
            echo "$SCRIPTS" | grep -v '^#' | awk 'NF{d=$1; $1=""; print d" 1 S"$0}'; } | sort -k1,1 -k2,2g -s)
deferred=()
photos_dir() {  # каталог фото скрипта: --photos=… или значение по умолчанию из самого скрипта
  for a in "$@"; do case "$a" in --photos=*) echo "${a#--photos=}"; return;; esac; done
  grep -oE "photos'\] \?\? '[^']+'" "www/$1" | head -1 | sed -E "s/.*'([^']+)'$/\1/"
}
run_script() {  # $@ = путь + аргументы. Ошибка = код возврата ≠ 0 или исключение в выводе.
  echo ">>> скрипт: $*"
  local dir; dir=$(photos_dir "$@")
  for try in $(seq 1 400); do
    X "/var/www/html/$1" "${@:2}" > /tmp/replay_last.txt 2>&1; local rc=$?
    if [ $rc -eq 0 ] && ! grep -qE '<pre>\[|Exception\]|Error\]|Fatal error' /tmp/replay_last.txt; then
      tail -4 /tmp/replay_last.txt; [ $try -gt 1 ] && echo "    (заглушек фото: $((try-1)))"; return 0
    fi
    # фото с погибшего сервера: создаём заглушку с ожидаемым именем и повторяем
    local miss; miss=$(sed -e 's/<[^>]*>//g' /tmp/replay_last.txt | grep -oE '(нет фото|Нет файла|нет файла) .+\.(png|jpe?g|webp|PNG|JPE?G)' | head -1 | sed -E 's/^(нет фото|Нет файла|нет файла) //')
    # «строгие» скрипты ждут значений списков, которые были на проде (по товарам вне архива) — добавляем
    local enum; enum=$(sed -e 's/<[^>]*>//g' /tmp/replay_last.txt | grep -oE '[A-Z_]+: нет значения «[^»]+» среди существующих' | head -1)
    if [ -n "$enum" ]; then
      X /var/www/html/local/bootstrap/add_enum.php "${enum%%:*}" "$(echo "$enum" | sed -E 's/.*«([^»]+)».*/\1/')"; continue
    fi
    if [ -n "$miss" ] && [ -n "$dir" ]; then
      X /var/www/html/local/bootstrap/placeholder.php "$dir" "$miss" > /dev/null; continue
    fi
    sed -e 's/<[^>]*>//g' /tmp/replay_last.txt | grep -vE '^\s*$' | tail -8; return 1
  done
  return 1
}
while read -r d prio kind rest; do
  if [ "$kind" = M ]; then
    echo ">>> миграция: $rest"
    X /var/www/html/local/bootstrap/run_one_migration.php "$rest" 2>&1 | tail -6
    [ "${PIPESTATUS[0]}" -ne 0 ] && { echo "СТОП на миграции $rest"; exit 1; }
    left=(); for s in "${deferred[@]}"; do run_script $s || left+=("$s"); done; deferred=("${left[@]}")
  else
    run_script $rest || { echo "    отложен: $rest"; deferred+=("$rest"); }
  fi
done <<< "$EVENTS"
echo "=== не выполнены: ${#deferred[@]}"; for s in "${deferred[@]}"; do echo "  $s"; done
