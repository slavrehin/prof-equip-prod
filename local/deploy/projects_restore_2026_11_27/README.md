# Портфолио: возврат к состоянию на конец октября 2026

Источник — база старого сервера (93.189.228.162) из файловой копии `backup/`:
сырые файлы MySQL 8.0.45 `backup/var/lib/mysql` (снимок 31.10.2026), поднятые во
временном контейнере и выгруженные в `site/backups/oct31_old_server_bitrix_db.sql.gz`,
и `backup/var/www/wordpress/upload/`. По проектам снимок 31.10 полностью совпадает с
дампом перед деплоем 21.10 (`backup/backup/deploy-2026-10-21-0619/db.sql.gz`).
В старой базе 149 проектов (135 активных + 14 скрытых), в текущей до импорта — 82.

`projects.json` строится `tools/export.py` и содержит для каждого проекта поля элемента,
обложки, галерею (по SHA-256), LIST (распакован из HTML-свойства старого сайта),
TYPE (значение «Отель» → «Гостиница»), строки CITY/INFO_TITLE/TITLE_*/CARD_*, коды связанных
товаров и брендов (`prof-equip` → `profekvip`), SEO-шаблоны элемента.
Изображения не в Git: `site/photos/projects_restore_2026_11_27/` (в Docker — `/mnt/photos/...`).

```sh
python3 -I site/www/local/deploy/projects_restore_2026_11_27/tools/export.py \
  site/backups/oct31_old_server_bitrix_db.sql.gz backup/var/www/wordpress/upload \
  site/photos/projects_restore_2026_11_27 "old server bitrix_db, raw datadir 31.10.2026"
```

## Что делает импорт

Каждый проект, кроме `--keep`, приводится **точно** к `projects.json`: NAME, SORT, ACTIVE_FROM,
тексты, обложки, все свойства инфоблока (свойства и значения, которых не было в октябре, —
BLOCKS задачи 8627, теги «Крым», «Санаторий» и т. п., ноябрьские LIST — очищаются), SEO.
Отсутствующие проекты создаются. Активность — как в октябре; `--activate-hidden`
публикует 14 проектов, скрытых на старом сайте.

По решению от 27.11.2026 не трогаются `mantera-supreme-seaside-5-2` и `mantera-supreme-seaside-5`.

Перед заменой обложки или галереи файл копируется в `--backup-dir/files/upload/...`,
состояние элемента — в `--backup-dir/snapshots/`. Откат: дамп БД, снятый перед запуском,
плюс возврат файлов из `files/` в корень сайта. Повторный dry-run после `--apply` → `updated: 0`.

## Запуск

```sh
# локально
docker compose -f site/docker-compose.yml exec -T web php -d memory_limit=1G \
  /var/www/html/local/deploy/projects_restore_2026_11_27/import.php \
  --src=/mnt/photos/projects_restore_2026_11_27 \
  --keep=mantera-supreme-seaside-5-2,mantera-supreme-seaside-5 --activate-hidden
# затем то же с --apply --backup-dir=/root/projects_exact_local
```

Применено локально 27.11.2026 (дампы до: `site/backups/projects_restore_20261127_before.sql.gz`,
`site/backups/projects_exact_20261127_before.sql.gz`; файлы и снимки —
`site/backups/projects_exact_20261127_local_rollback/`), затем база выложена на dev.

## Прод

Только после согласования на dev: свежий дамп `/backup/prod_db_*.sql.gz`, каталог
изображений — на сервер вне корня сайта, dry-run, затем `--apply --allow-production`
с теми же `--keep`. `--activate-hidden` — только если согласована публикация 14 проектов.
