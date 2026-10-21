# Реструктуризация каталога и стандарт характеристик (2026-10-13)

Задача: под каждый кластер спроса — посадочная страница (раздел, подраздел, фильтр, тег); единые
характеристики во всём каталоге; подкатегории с картинками и «Популярные подборки» над листингом.

## Где что

| Что | Файл |
|---|---|
| Словарь стандарта: структура, названия свойств, дубли, значения, «Тип», «Тип предприятия», фильтры по разделам, старые адреса | `local/php_interface/include/catalog_standard.php` (`CatalogStandard`) |
| «Тип» (TIP_TOVARA): запчасти → группы, текстиль → виды изделий | `local/php_interface/include/product_type.php` |
| SEO-теги: поиск, подсчёт, URL, 301 с `/f/` | `local/php_interface/include/seo_tags.php` (`SeoTags`) |
| Маршрут `/product-category/<раздел>/<тег>/` | `local/routes/web.php` |
| Раздел/тег: H1 из `UF_H1`, мета тега, 301 со старых адресов фильтра | `local/templates/profequip/components/bitrix/catalog/catalog/section.php` |
| Плитки подкатегорий + «Популярные подборки» | `…/catalog/catalog/include/catalog_top.php`, `assets/css/catalog-nav.css` |
| Набор/порядок фильтров, SEO-ссылки значений | `…/catalog.smart.filter/.default/result_modifier.php`, `template.php` |
| 301 старых адресов каталога (вместо префиксных Redirect в .htaccess) | `CatalogStandard::legacyRedirect()`, вызывается из `catalog/index.php` и маршрута тегов |
| Теги в sitemap | `local/app/Sitemap/SitemapGenerator.php` |
| Числа в CSV-импорте (свойства типа «Число») | `local/modules/profequip.import/lib/ProductImporter.php` |

## Миграции (по порядку, все идемпотентны, всё по CODE)

1. `2026-10-13-catalog-01-structure.php` — снятие лишних привязок к родителям, перенос товаров из
   «свалок», новые разделы «Оборудование для химчистки» и «Сортировка белья», перенос разделов, порядок,
   переименования, поле `UF_H1` и H1, расформирование дублей (promyshlennye-…, stiralno-otzhimnye-…, gladilnye-mashiny).
2. `…-02-property-names.php` — единые названия 170 свойств, скрытие дублей «Типа».
3. `…-03-values.php` — слияние свойств-дублей (с пересчётом единиц), словарь значений, дедупликация,
   числовой формат, слаги вместо хэшей. История слагов → `b_profequip_slug_history` (для 301).
4. `…-04-numeric.php` — «Загрузка», «Рабочая ширина вала», «G-фактор» → тип «Число» (ползунок).
   Загрузка — из названия товара, где оно точнее (старые значения были округлены до 5/10/15/20/30).
5. `…-05-types.php` — пересчёт «Типа» всем товарам, «Тип предприятия» по правилам.
6. `…-06-features.php` — «Назначение: МОПы».
7. `…-07-seo-rules.php` — отключение SEO-правил фильтра, заменённых тегами (детали запчастей, загрузка).
8. `2026-10-13-seo-tags-iblock.php` — инфоблок `seotags`.
9. `2026-10-20-seo-tags-noindex.php` — свойство тега `NOINDEX` (страница есть, но noindex, без sitemap и ссылки
   в фильтре). Какие закрыты — `ENTERPRISE_INDEX_SECTIONS` и `NOINDEX_ENTERPRISE` в `seed_tags.php` (теги «Тип предприятия» индексируются только в корне прачечного, 5 типов).

Сухой прогон шагов 4 и 5: `CATALOG_DRY=1` (см. шапку файлов).

## После миграций — данные

```bash
php local/deploy/catalog_restructure/seed_tags.php            # отчёт: какие теги, сколько товаров
php local/deploy/catalog_restructure/seed_tags.php --apply    # создать/обновить теги
php local/deploy/generate_sitemap.php                          # sitemap с тегами
```

Повторный `seed_tags.php --apply` после правок текстов в админке — с `--keep-texts`.
Ручные тексты тегов (H1/Title/Description/SEO-текст) — в `texts.php`, применяются при любом запуске сида;
сейчас там кластер «прачечное оборудование для {тип предприятия}» (5 тегов `prachechnoe-oborudovanie/dlya-*`).

## Перенос на прод (не выполнен — только после согласования)

1. Бэкап (deploy.sh делает сам).
2. Ветка от `origin/master` с этими файлами → `deploy.sh` (миграции 01–07 и seo-tags-iblock).
3. `seed_tags.php` (сначала без `--apply`, сверить отчёт), затем `--apply`; `generate_sitemap.php`.
4. Проверка: `/product-category/stiralnye-mashiny/` (плитки, подборки), `/product-category/stiralnye-mashiny/25-kg/`
   (H1, index), старые адреса → 301: `/product-category/stiralnye-mashiny/f/zagruzka_kg-is-25/`,
   `/product-category/promyshlennye-stiralnye-mashiny/`, `/product-category/prachechnoe-oborudovanie/finishnoe-oborudovanie/paromanekeny/`.
5. На проде правила slug-истории пишутся заново (хэш-слаги там свои) — это ожидаемо.

## Грабли

- В шаблонах компонентов не заводить переменные `$exists`, `$frame`, `$page`, `$i` — шаблон
  подключается внутри метода ядра и перетирает его локальные переменные (`$exists` дал 500 на /f/).
- H1 раздела — только `UF_H1`: SEO-шаблон «Заголовок раздела» наследуется подразделами и
  попадает в хлебные крошки.
- Элемент 29 «Профессиональные прачечные» (инфоблок направлений) привязан к разделам каталога —
  миграции его не трогают.
- Ручные редиректы из инфоблока `redirect` срабатывают раньше `legacyRedirect`; адреса, ставшие
  страницами тегов, init.php пропускает.
