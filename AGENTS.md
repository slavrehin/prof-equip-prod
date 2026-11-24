# Инструкции для Kimi Code

## Импорт фото товаров из архива пользователя

Контекст: сайт 1С-Битрикс, каталог в `IBLOCK_ID=11`. Фото добавляются через `site/www/local/deploy/product_photos/import.php` партиями.

### Шаги

1. **Распаковать архив** пользователя (например, `фотки товаров/<brand>.zip`) во временную папку.
2. **Найти товары в базе** по коду/модели:
   ```bash
   docker compose -f site/docker-compose.yml exec -T db \
     mysql -ubitrix -pbitrix sitedb -N -e \
     "SELECT ID, CODE, NAME FROM b_iblock_element WHERE IBLOCK_ID=11 AND CODE LIKE '%<brand>%';"
   ```
3. **Создать партию** в `site/www/local/deploy/product_photos/batches/<brand>_YYYYMMDD/`:
   - `img/` — нормализованные фото (квадрат 1200×1200, белый фон, JPEG).
   - `map.json` — массив объектов: `{id, name, code, files: ["img/<file>.jpg"], source}`.
   - Для нормализации используй `site/photos/_scripts/normalize.py`.
4. **Проверить сухим прогоном** локально:
   ```bash
   docker compose -f site/docker-compose.yml exec -T web \
     php /var/www/html/local/deploy/product_photos/import.php <brand>_YYYYMMDD --dry
   ```
5. **Применить локально** с логом отката:
   ```bash
   docker compose -f site/docker-compose.yml exec -T web \
     php /var/www/html/local/deploy/product_photos/import.php <brand>_YYYYMMDD \
     --log=/var/www/html/local/deploy/product_photos/batches/<brand>_YYYYMMDD/rollback_local.json
   ```
   - Файл `rollback_local.json` не коммитить.
6. **Проверить** 1–2 карточки товара локально (`http://127.0.0.1:8090/product/<code>/`).
7. **Коммит и пуш**:
   ```bash
   cd site/www
   git add local/deploy/product_photos/batches/<brand>_YYYYMMDD/map.json local/deploy/product_photos/batches/<brand>_YYYYMMDD/img
   git commit -m "Фото <BRAND>: партия <brand>_YYYYMMDD (N товаров, источник)"
   git -C ../repo pull --ff-only ../site/www master
   git -C ../repo push origin master
   git fetch origin
   ```
8. **Деплой на dev**:
   ```bash
   bash site/deploy_cloud/deploy_dev.sh
   ```
   Скрипт заменяет базу dev локальной копией.
9. **Проверить на dev** несколько карточек:
   ```bash
   U=$(sed -n 1p site/.dev-basic-auth); P=$(sed -n 2p site/.dev-basic-auth)
   curl -s -u "$U:$P" "https://dev.prof-equip.ru/product/<code>/" | grep -oE '/upload/iblock/[a-z0-9]+/[a-z0-9]+\.(jpg|webp)'
   ```
10. **Деплой файлов на прод** (только после проверки на dev):
    ```bash
    bash site/deploy_cloud/deploy_prod.sh
    ```
    Скрипт переносит файлы (включая партию в `local/deploy/product_photos/batches/`), но **не базу**.
    Без следующего шага фото на проде не появятся: записи о них есть только в базах local/dev.
11. **Импорт на проде** — снимок базы, сухой прогон, применение с логом отката:
    ```bash
    B=<brand>_YYYYMMDD
    ssh root@89.111.178.85 "mysqldump --defaults-extra-file=/root/.my-prof.cnf --single-transaction --no-tablespaces profdb \
      | gzip > /backup/prod_db_before_photos_${B}_\$(date +%Y%m%d_%H%M%S).sql.gz"
    ssh root@89.111.178.85 "cd /var/www/prof-equip.ru/docs && sudo -u www-data php local/deploy/product_photos/import.php $B --dry"
    ssh root@89.111.178.85 "cd /var/www/prof-equip.ru/docs && sudo -u www-data php local/deploy/product_photos/import.php $B \
      --log=/var/www/prof-equip.ru/rollback_${B}.json"
    ```
    - Сухой прогон на проде может отличаться от локального: там могли появиться фото, добавленные через админку, — их импорт не трогает.
    - Лог отката хранится на сервере вне корня сайта; откат: `... import.php --rollback=/var/www/prof-equip.ru/rollback_${B}.json`.
12. **Проверить на проде** те же карточки:
    ```bash
    curl -s "https://prof-equip.ru/product/<code>/" | grep -oE '/upload/iblock/[a-z0-9]+/[a-z0-9]+\.(jpg|webp)'
    ```

### Важные ограничения

- Импорт не заменяет существующие нормальные фото без `--force`. Заглушки и пустые превью заменяются автоматически.
- Если в архиве дублируются файлы между моделями, это дубли источника; сообщить пользователю.
- `rollback_local.json` оставлять локально, не включать в Git.
