<?php
/**
 * Заменяет хэш-подобные XML_ID у вариантов списка свойства "Тип" (CODE=TIP_ZAPCHASTI,
 * инфоблок "Каталог") на человекочитаемые слаги — это то, что попадает в ЧПУ умного
 * фильтра (bitrix/modules/iblock/install/components/bitrix/catalog.smart.filter/class.php
 * берёт mb_strtolower(XML_ID) как URL_ID), например:
 *   /product-category/zapasnye-chasti/f/tip_zapchasti-is-d6b98fa851143e27d7d1ab09786e772f/
 *   -> /product-category/zapasnye-chasti/f/tip_zapchasti-is-blok-podgotovki-vozduha/
 *
 * Слаг делается тем же способом, что и в local/import/parser.php::makeXmlIdFromText()
 * (CUtil::translit, replace_space/replace_other='-', нижний регистр, до 50 символов).
 *
 * Идемпотентен: обрабатывает только варианты, чей текущий XML_ID выглядит как
 * 32-символьный hex-хэш (значит, ещё не переведён в слаг) - уже переведённые
 * при повторном запуске пропускаются. Дедуп слагов внутри свойства - через
 * суффикс -2, -3 и т.д., если базовый слаг уже занят.
 *
 * Дедуп при повторном импорте CSV не ломается: ProductImporter::resolveEnumValue()
 * матчит вариант по VALUE (тексту), не по XML_ID - см.
 * local/modules/profequip.import/lib/ProductImporter.php.
 *
 * Запуск:
 *   docker compose exec web php /var/www/html/local/deploy/backfill_tip_zapchasti_slugs.php   # test3
 *   php local/deploy/backfill_tip_zapchasti_slugs.php                                          # прод
 */

$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

CModule::IncludeModule('iblock');

global $DB;

function tzs_make_slug(string $text): string
{
    if (class_exists('\CUtil')) {
        $xml = \CUtil::translit($text, 'ru', [
            'replace_space' => '-',
            'replace_other' => '-',
            'change_case' => 'L',
            'max_len' => 50,
        ]);
    } else {
        $xml = mb_strtolower($text);
    }
    $xml = preg_replace('/[^a-z0-9\-]/', '-', (string)$xml);
    $xml = preg_replace('/-+/', '-', (string)$xml);
    return trim((string)$xml, '-');
}

$prop = \CIBlockProperty::GetList([], ['IBLOCK_ID' => 11, 'CODE' => 'TIP_ZAPCHASTI'])->Fetch();
if (!$prop) {
    throw new \RuntimeException('Свойство TIP_ZAPCHASTI не найдено в инфоблоке 11');
}
$propertyId = (int)$prop['ID'];

// Собираем уже занятые (не хэш-подобные) XML_ID в нижнем регистре - под них
// нельзя генерировать дубли.
$taken = [];
$rows = [];
$rs = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propertyId]);
while ($row = $rs->Fetch()) {
    $rows[] = $row;
    if (!preg_match('/^[0-9a-f]{32}$/', $row['XML_ID'])) {
        $taken[mb_strtolower($row['XML_ID'])] = true;
    }
}

$updated = 0;
$skipped = 0;

foreach ($rows as $row) {
    $xmlId = (string)$row['XML_ID'];
    if (!preg_match('/^[0-9a-f]{32}$/', $xmlId)) {
        $skipped++;
        continue; // уже слаг - идемпотентность
    }

    $base = tzs_make_slug((string)$row['VALUE']);
    if ($base === '') {
        $base = 'value-' . $row['ID'];
    }

    $slug = $base;
    $n = 2;
    while (isset($taken[$slug])) {
        $slug = $base . '-' . $n;
        $n++;
    }
    $taken[$slug] = true;

    $enum = new \CIBlockPropertyEnum();
    $ok = $enum->Update((int)$row['ID'], ['XML_ID' => $slug]);
    if (!$ok) {
        throw new \RuntimeException('Не удалось обновить XML_ID для варианта ID=' . $row['ID'] . ' (' . $row['VALUE'] . ')');
    }

    // Проверка результата запросом к базе.
    $check = $DB->Query("SELECT XML_ID FROM b_iblock_property_enum WHERE ID = " . (int)$row['ID'])->Fetch();
    if (!$check || $check['XML_ID'] !== $slug) {
        throw new \RuntimeException('XML_ID не сохранился как ожидалось для варианта ID=' . $row['ID']);
    }

    $updated++;
}

echo "Обновлено слагов: $updated, уже было слагом (пропущено): $skipped, всего вариантов: " . count($rows) . "\n";
