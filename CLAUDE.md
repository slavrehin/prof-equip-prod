# prof-equip.ru — контекст для Claude Code

Интернет-магазин на 1С-Битрикс. Прод и тестовая копия — на одном VPS
(`93.189.228.162`, root).

| | |
|---|---|
| Прод | `/var/www/wordpress/`, домен `prof-equip.ru` |
| test3 | `/var/www/prof-equip-test/src/` (Docker), домен `test3.prof-equip.ru`, закрыт Basic Auth + noindex |
| Версии | PHP 8.4.15, MySQL 8.0.45, Bitrix `SM_VERSION 26.100.0` |
| Основной инфоблок каталога | `IBLOCK_ID = 11` (CODE=`catalog`) |
| Активный шаблон | `local/templates/profequip` |
| Кастомный модуль импорта | `local/modules/profequip.import/` |
| Git | этот репозиторий (`prof-equip-prod`). Прод — `master`, только `git fetch` + `merge --ff-only`, **никогда push**. test3 — разработка, пушит ветки |

## Устройство test3

`/var/www/prof-equip-test/src` — тот же git, что и прод. `upload/` и
`wp-content/uploads/` монтируются в Docker-контейнер **read-only** прямо из
боевых папок (см. `docker-compose.yml` в `/var/www/prof-equip-test/`) —
не дублируются, но и не могут быть изменены с test3. Своя отдельная БД
(`sitedb` в контейнере), свой `bitrix/.settings.php` (не в git).

## Правила работы с продом

1. **Ни одной операции на проде до бэкапа.** `deploy.sh` делает это сам.
2. **Никогда** `git clean` / `git checkout .` / `git reset --hard` на
   проде без явного запроса пользователя и понимания, что теряется.
3. **Дамп test3 никогда не заливается на прод.** Структурные изменения БД —
   только через `local/migrations/`.
4. Секреты (`bitrix/.settings.php`, `dbconn.php`, API-ключи в
   `local/php_interface/include/constants_local.php`) не в git, разные на
   проде и test3.
5. Первый коммит сделан от состояния прода (2026-09-01), не от test3.
6. Изменения структуры БД — идемпотентными миграциями
   (`local/migrations/README.md`).
7. Результат проверяется запросом к базе / открытием страницы, не кодом
   возврата функции.

## Секреты — где искать

`local/php_interface/include/constants_local.php` (не в git, на диске у
каждого окружения свой): API-ключи Яндекс.SmartCaptcha,
`BITRIX24_WEBHOOK_URL` (вебхук CRM). `constants.php` в git подключает этот
файл через `require_once`.

## CLI mysqldump не работает на этом сервере

`mysql`/`mysqldump` CLI получают `Access denied` с рабочими учётными
данными из `.settings.php`, независимо от сокета/TCP/SSL-флагов; при этом
`mysqli_connect()` с теми же данными подключается нормально. Причина не
выяснена. Поэтому бэкап и восстановление БД (`local/deploy/db_dump.php`,
`rollback.sh`) сделаны напрямую через `mysqli`, не через `mysqldump`.

## PHP CLI не бутстрапил ядро Bitrix — исправлено (short_open_tag)

Сайт обслуживается Apache + `libapache2-mod-php8.4` (не php-fpm — `apache2 -k
start` в процессах, php-fpm не установлен). У Apache и CLI **разные php.ini**
(`/etc/php/8.4/apache2/` и `/etc/php/8.4/cli/`). У Apache `short_open_tag =
On`, у CLI по умолчанию был `Off`. Ядро Bitrix написано с короткими тегами
`<?` — при `Off` PHP CLI не парсил `bitrix/modules/main/include.php` и
подобные файлы как код, выводил их как текст, из-за чего падало
`Class "Bitrix\Main\HttpApplication" not found` при любом запуске CLI-скрипта,
подключающего `prolog_before.php` (обнаружено на Этапе 7 при первом реальном
запуске `local/migrations/run.php` на проде — до этого ни разу не
проверялось на живом сервере).

Исправлено системно (вне git, это конфиг сервера, не сайта):

```
/etc/php/8.4/cli/conf.d/99-bitrix-short-open-tag.ini
short_open_tag = On
```

Если на новом окружении (например, при переносе на другой сервер) CLI-скрипты
Bitrix (`local/migrations/run.php`, `local/modules/profequip.import/cli/*`)
падают с похожей ошибкой сразу на прологе — проверить эту настройку первой.

## Известные грабли Bitrix (см. `local/migrations/README.md` подробнее)

- PHP 8.4: `CIBlockProperty/Element/Section::Add/Update` только через `new`.
- Картинки элементов — только прямым SQL, не `CIBlockElement::Update()`
  (иначе может обнулиться `IBLOCK_SECTION_ID`).
- Привязка свойства к умному фильтру — таблица
  `b_iblock_section_property`, не колонка в `b_iblock_property`.
- `CIBlockElement::GetProperty()` — только по одному элементу за вызов.

## Ключи деплоя (GitHub deploy keys на этом репозитории)

- `prof-equip-prod-deploy` (на проде, `~/.ssh/deploy_prof-equip`) —
  **read-only**. Временно включался на запись только для самого первого
  push истории 2026-09-01, затем возвращён в read-only.
- `prof-equip-test3-dev` (на test3, `~/.ssh/deploy_prof-equip-test3`) —
  постоянный **write**, test3 пушит ветки в разработке.

## Известное, но не устранённое

- На главной странице два тега `<meta name="robots">` (не связано с
  git/деплоем, существовало и на test3 до переноса). Основная защита от
  индексации test3 — заголовок `X-Robots-Tag` от nginx и Basic Auth, оба
  работают независимо от этого дубля.
- У части товаров в каталоге не заполнены SEO title/description (Bitrix
  отдаёт буквальные заглушки `<title>Title</title>`) — `smoke.sh` это
  ловит на тех URL, что в `smoke-urls.txt`, но не гарантирует полноту по
  всему каталогу.
