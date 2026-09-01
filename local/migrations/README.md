# Миграции БД

Любое изменение структуры инфоблоков, свойств, настроек модулей и т.п. —
только через файл здесь, не руками через админку на проде.

## Как запустить

```bash
php local/migrations/run.php            # применить всё непримененное
php local/migrations/run.php --dry-run  # только показать список, ничего не менять
```

На test3 — то же самое, из контейнера:

```bash
docker compose exec web php /var/www/html/local/migrations/run.php
```

`deploy.sh` на проде вызывает `run.php` сам, отдельно руками там его
запускать не нужно.

## Как написать новую миграцию

Имя файла: `YYYY-MM-DD-краткое-описание.php`, например
`2026-09-05-seo-filter-props.php`.

**Идемпотентность обязательна** — перед созданием чего угодно проверка,
что оно уже существует:

```php
<?php
$exists = \CIBlockProperty::GetList([], ['IBLOCK_ID' => 11, 'CODE' => 'MATERIAL'])->Fetch();
if (!$exists) {
    $prop = new \CIBlockProperty();   // PHP 8.4: только через new, не статически
    $id = $prop->Add([
        'IBLOCK_ID' => 11,
        'CODE' => 'MATERIAL',
        'NAME' => 'Материал',
        'PROPERTY_TYPE' => 'S',
    ]);
    if (!$id) {
        throw new \RuntimeException('Не создано свойство MATERIAL: ' . $prop->LAST_ERROR);
    }

    // Проверка результата запросом к базе, а не доверие коду возврата.
    global $DB;
    $check = $DB->Query("SELECT ID FROM b_iblock_property WHERE ID = " . (int)$id)->Fetch();
    if (!$check) {
        throw new \RuntimeException('Свойство не найдено в базе после Add()');
    }
    echo "Создано свойство MATERIAL (ID=$id)\n";
} else {
    echo "Свойство MATERIAL уже существует, пропускаю\n";
}
```

Каждая миграция должна:
- проверять существование того, что создаёт (по `CODE`, не по `ID` —
  ID на test3 и на проде для одной и той же сущности почти всегда разные);
- бросать исключение при ошибке (раннер остановится и не отметит миграцию
  как применённую);
- печатать, что именно сделала;
- проверять результат запросом к базе.

## Известные грабли (см. также корневой CLAUDE.md)

- `CIBlockProperty::Update()` без `IBLOCK_ID` в массиве не создаёт привязку
  к умному фильтру — передавайте `IBLOCK_ID`, проверяйте через
  `b_iblock_section_property`.
- Показ в умном фильтре — это строка в `b_iblock_section_property`
  (`SECTION_ID=0` — глобально), не колонка `b_iblock_property`.
- `CIBlockElement::Update()` с картинками падает валидацией и может обнулить
  `IBLOCK_SECTION_ID` — картинки только прямым SQL `UPDATE`.
- В PHP 8.4 `CIBlockProperty/Element/Section::Add/Update` только через
  `new ClassName()`, не статически.
- `CIBlockElement::GetProperty()` не принимает массив ID — вызывать в цикле.
- SEO-значения при прямой записи в `b_iblock_iproperty` не подхватываются
  без сброса кэша страницы после миграции.

## Обкатка

Каждая миграция сначала прогоняется на test3 — **дважды подряд**. Второй
запуск должен сказать «уже применено» и ничего не поменять. Проверять
результат — запросом к базе (`docker compose exec db mysql ...`), не
доверять тому, что скрипт «отработал без ошибок».
