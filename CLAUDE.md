# prof-equip.ru — технический контекст

Интернет-магазин на 1С-Битрикс. Актуальная схема: local → dev → prod.

| Окружение | Расположение |
|---|---|
| local | `/Users/slavrehin/Documents/prof-equip-local/site/www`, Docker, `http://127.0.0.1:8090/` |
| dev | `root@89.111.178.85`, `/var/www/dev.prof-equip.ru/docs`, Basic Auth + запрет индексации |
| prod | `root@89.111.178.85`, `/var/www/prof-equip.ru/docs`, `prof-equip.ru` |
| Git | `slavrehin/prof-equip-prod`; основной worktree `site/www`, чистое зеркало `repo` |

| Компонент | Значение |
|---|---|
| Версии | PHP 8.4, MySQL 8.0, Bitrix `SM_VERSION 26.100.0` |
| Основной инфоблок каталога | `IBLOCK_ID = 11`, `CODE=catalog` |
| Активный шаблон | `local/templates/profequip` |
| Frontend | `local/layout`: Pug, SCSS, JS, Webpack, Node 20 |
| Кастомный модуль импорта | `local/modules/profequip.import/` |

Старый VPS `93.189.228.162`, test3 и прежняя Git-only схема описаны в `ХРОНОЛОГИЯ.md` как история. Текущая инструкция — `DEPLOY.md` и `site/deploy_cloud/`.

## Правила работы

1. **Ни одной операции на проде до резервной копии.** Полный бэкап: `site/server/backup_full.sh`; файловый деплой также снимает дамп БД.
2. Не использовать `git clean`, `git checkout .`, `git reset --hard` на сервере без явного запроса и понимания потерь.
3. Обычный `site/deploy_cloud/deploy_prod.sh` копирует только файлы. База local/dev на прод не переносится; `--with-db` требует отдельного явного согласования.
4. Структурные изменения БД — идемпотентными миграциями `local/migrations/`; контентные изменения — скриптами `local/deploy/<задача>/` с dry-run, резервным снимком и read-back проверкой.
5. Секреты `bitrix/.settings.php`, `dbconn.php`, `local/php_interface/include/constants_local.php` в Git не включаются и различаются по окружениям.
6. Результат проверяется запросом к БД, содержимым файлов и HTTP-страницами, а не только кодом возврата функции.
7. **Каждое изменение коммитится и пушится в GitHub без отдельного запроса.** После коммита в `site/www`:
   `git -C repo pull --ff-only ../site/www master && git -C repo push origin master`.
8. Перед коммитом проверить `git status` и `git diff`: секреты, дампы и `/upload` в индекс не попадают. Изменения `/upload` воспроизводятся идемпотентным импортом с исходниками в Git.
9. Сначала коммит и push, затем выкладка на dev/прод.

## Секреты

`local/php_interface/include/constants_local.php` содержит ключи Яндекс.SmartCaptcha и `BITRIX24_WEBHOOK_URL`; файл не входит в Git. `constants.php` подключает его через `require_once`. Выгрузки Bitrix24 используют `site/.b24-webhook`, значение не печатать.

Владелец подтвердил, что вебхук с потерянного сервера отозван. Полные резервные наборы содержат секреты и должны храниться приватно. `local/layout/.env` исключён из Git и сохраняется только локально; frontend не использует переменные `APP_*` в исходниках.

## Резервные копии и mysqldump

На текущем сервере `site/server/backup_full.sh` и `site/deploy_cloud/` используют `mysqldump --single-transaction`. Прежняя проблема CLI-аутентификации на потерянном VPS не относится к текущему серверу. Старые `local/deploy/deploy.sh`, `rollback.sh` и `db_dump.php` сохранены в истории кода, но не описывают актуальный процесс.

## PHP CLI и short_open_tag

Для ядра Bitrix необходим `short_open_tag=On` и для Apache, и для CLI. В локальном Docker настройка находится в `site/docker/php/php.ini`; на сервере — в `/etc/php/8.4/*/conf.d/99-bitrix.ini`.

Если CLI-скрипт падает сразу на прологе с `Class "Bitrix\Main\HttpApplication" not found`, сначала проверить эту настройку. Подробности восстановления серверной конфигурации — в `site/server/provision.sh`.

## Известные особенности Bitrix

Подробнее — `local/migrations/README.md`.

- PHP 8.4: `CIBlockProperty/Element/Section::Add/Update` вызывать через `new`.
- Картинки элементов обновлять только прямым SQL, не `CIBlockElement::Update()`: иначе может обнулиться `IBLOCK_SECTION_ID`.
- Привязка свойства к умному фильтру хранится в `b_iblock_section_property`, не в колонке `b_iblock_property`.
- `CIBlockElement::GetProperty()` вызывать для одного элемента за раз.
- Миграции безопасно изолировать в `call_user_func(static function () { ... });`, чтобы переменные не перезаписали состояние раннера.

## Проверки

- `local/deploy/smoke.sh` — HTTP и базовые SEO-заглушки, список в `smoke-urls.txt`.
- `local/migrations/run.php --dry-run` — неприменённые миграции.
- GitHub Actions — PHP lint, shell syntax и frontend build; smoke запускается вручную.
- Импорты и повторные миграции проверяются на реальной локальной/dev БД: GitHub-hosted CI не содержит ядро Bitrix и дамп данных.

Полнота smoke по всему каталогу не гарантируется. Для контентных задач дополнительно использовать отчёты и проверки из `site/checks/`.
