# Логотип «ПРОФЭКВИП / ПЭ» (2026-11-09)

Замена логотипа «PROF EQUIP / PE». `/upload` не в git, поэтому файлы лежат здесь.

- `source.jpg` — исходник от заказчика (1280×231, белый фон).
- `logo.png` → `/upload/logo.png` — цветной, прозрачный фон (шапка, Schema.org, og:image по умолчанию).
- `logo-white.png` → `/upload/logo-white.png` — белый, поверх баннера главной.
- `favicon.webp`, `favicon.ico` в корне сайта — значок «ПЭ» (в этом же коммите).

Установка: `install -o www-data -g www-data -m 644 logo.png logo-white.png <docroot>/upload/`,
затем очистить `bitrix/cache`, `managed_cache`, `stack_cache`.
В `header.php` ссылки с `?v=2` — при следующей замене поднять версию.
