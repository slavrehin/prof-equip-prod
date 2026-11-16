# Деплой prof-equip.ru

Актуальная схема: **локальная Docker-копия → dev.prof-equip.ru → prof-equip.ru**. Все команды ниже запускаются на Mac из `/Users/slavrehin/Documents/prof-equip-local`, если не указано иное.

> Старые инструкции для `test3.prof-equip.ru` и сервера `93.189.228.162` исторические. Скрипты `local/deploy/deploy.sh` и `local/deploy/rollback.sh` относятся к прежней Git-only схеме и не являются текущим способом выкладки или отката.

## 1. Разработка и проверка локально

Изменения вносятся в `site/www`. Перед выкладкой:

```bash
docker compose -f site/docker-compose.yml up -d
docker compose -f site/docker-compose.yml exec -T web \
  php /var/www/html/local/migrations/run.php --dry-run
SMOKE_BASE_URL=http://127.0.0.1:8090 bash site/www/local/deploy/smoke.sh
```

После изменения frontend-исходников:

```bash
cd site/www/local/layout
npm ci
npm run build
```

Миграции сначала применяются локально или на dev дважды: второй запуск не должен ничего менять. Контентный импорт сначала выполняется без `--apply`, затем применяется с резервным снимком и повторно проверяется по README конкретной задачи.

## 2. Коммит и GitHub

Проверить, что в индекс не попали секреты, дампы, `upload/` или ядро Bitrix:

```bash
git -C site/www status --short
git -C site/www diff --check
```

После коммита в `site/www`:

```bash
git -C repo pull --ff-only ../site/www master
git -C repo push origin master
git -C site/www fetch origin
```

Сначала код фиксируется в GitHub, затем выкатывается.

## 3. Выкладка на dev

```bash
bash site/deploy_cloud/deploy_dev.sh
```

Скрипт:

- снимает дамп локальной `sitedb` в `site/backups/`;
- копирует файлы на `dev.prof-equip.ru`;
- **полностью заменяет базу dev** локальной базой;
- сохраняет серверные доступы и окружение dev;
- включает Basic Auth и запрет индексации;
- проверяет несколько страниц.

Дамп локальной базы нельзя использовать как обычный способ обновления прода.

После выкладки нужно проверить изменённые страницы, формы и импорты именно на dev. Smoke с Basic Auth:

```bash
read -r DEV_USER DEV_PASS < site/.dev-basic-auth
SMOKE_AUTH="$DEV_USER:$DEV_PASS" SMOKE_BASE_URL="https://dev.prof-equip.ru" \
  bash site/www/local/deploy/smoke.sh
unset DEV_USER DEV_PASS
```

## 4. Выкладка на прод

Обычный режим:

```bash
bash site/deploy_cloud/deploy_prod.sh
```

Он создаёт резервный дамп базы прода в `/backup/`, копирует файлы с уже проверенного dev, не удаляет `upload/`, сохраняет продовые секреты и настройки БД, очищает кэш и проверяет основные страницы. База dev на прод в этом режиме **не копируется**.

Если есть новые структурные миграции, после файлового деплоя и резервного дампа они применяются отдельно:

```bash
ssh root@89.111.178.85 \
  'cd /var/www/prof-equip.ru/docs && php local/migrations/run.php'
```

Контентные импорты запускаются отдельно по своим README, с новым резервным снимком и явным production-флагом, если он предусмотрен. После них проверяется фактическое содержимое БД и страницы, а не только код возврата скрипта.

Финальная проверка:

```bash
bash site/www/local/deploy/smoke.sh
```

Режим `deploy_prod.sh --with-db` полностью заменяет базу прода базой dev. Он не входит в стандартный процесс и допускается только после отдельного явного согласования и свежей полной резервной копии.

## 5. Откат и аварийное восстановление

У текущего `deploy_prod.sh` нет универсальной автоматической команды отката. Перед каждым содержательным изменением нужно знать:

- коммит, на котором находился предыдущий код;
- путь к созданному дампу `/backup/prod_db_*.sql.gz`;
- резервные снимки конкретного импорта;
- путь к последнему полному набору в `full_backups/`.

Не использовать старый `local/deploy/rollback.sh` на новом сервере. Для восстановления всей площадки на чистом VPS использовать `site/server/restore.sh` и README свежего полного резервного набора.

## Запрещено

- Править отслеживаемые файлы прода напрямую: следующий `rsync --delete` их удалит или перезапишет.
- Заливать базу local/dev на прод без отдельного явного согласования.
- Запускать импорт с `--apply` без предварительного dry-run и резервного снимка.
- Выполнять `git reset --hard`, `git clean` или удалять серверные файлы ради устранения конфликта без проверки содержимого.
- Публиковать резервные наборы: они содержат доступы к БД, SmartCaptcha, Bitrix24 и SMTP.
