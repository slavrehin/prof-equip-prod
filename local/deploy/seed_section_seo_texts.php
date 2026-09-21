<?php
/**
 * Заливает SEO-тексты разделов каталога (инфоблок 11, поле раздела DESCRIPTION,
 * выводится в .catalog__text внизу страницы категории) из local/deploy/seo_texts/<CODE>.html.
 *
 * Раздел ищется по CODE (не по ID — ID на test3 и проде расходятся).
 *
 * По умолчанию НИЧЕГО не перезаписывает: раздел с уже заполненным описанием
 * пропускается (отчёт "пропущено: текст уже есть"). Чтобы заменить существующий
 * текст — явно передать --force (старый текст сохраняется в
 * local/deploy/seo_texts/_backup_<время>.json). Так можно безопасно перезапускать
 * (идемпотентно) и не затереть тексты, которые кто-то поправил в админке.
 *
 * Флаги:
 *   --dry            только показать, что будет сделано
 *   --force          перезаписывать непустые описания
 *   --only=code1,code2   ограничить список кодов
 *
 * Запуск:
 *   docker compose exec web php /var/www/html/local/deploy/seed_section_seo_texts.php --dry   # test3
 *   php local/deploy/seed_section_seo_texts.php                                              # прод
 */

$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

CModule::IncludeModule('iblock');

const SEO_IBLOCK_ID = 11;

$dry = in_array('--dry', $argv, true);
$force = in_array('--force', $argv, true);
$only = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = array_filter(explode(',', substr($arg, 7)));
    }
}

$dir = __DIR__ . '/seo_texts';
$files = glob($dir . '/*.html');
sort($files);

$stat = ['updated' => 0, 'skipped_has_text' => 0, 'same' => 0, 'no_section' => 0];
$backup = [];

foreach ($files as $file) {
    $code = basename($file, '.html');
    if (str_starts_with($code, '_')) {
        continue;
    }
    if ($only && !in_array($code, $only, true)) {
        continue;
    }
    $html = trim((string)file_get_contents($file));
    if ($html === '') {
        continue;
    }

    $section = CIBlockSection::GetList(
        [],
        ['IBLOCK_ID' => SEO_IBLOCK_ID, 'CODE' => $code],
        false,
        ['ID', 'CODE', 'NAME', 'DESCRIPTION', 'DESCRIPTION_TYPE']
    )->Fetch();

    if (!$section) {
        echo "НЕТ РАЗДЕЛА   $code\n";
        $stat['no_section']++;
        continue;
    }

    $current = trim((string)$section['DESCRIPTION']);
    if ($current === trim($html)) {
        echo "уже актуально $code\n";
        $stat['same']++;
        continue;
    }
    $hasText = trim(strip_tags(html_entity_decode($current))) !== '';
    if ($hasText && !$force) {
        echo "пропущено (текст уже есть, нужен --force) $code\n";
        $stat['skipped_has_text']++;
        continue;
    }

    if ($dry) {
        echo "будет записано $code (id {$section['ID']}, было " . mb_strlen($current) . " симв.)\n";
        continue;
    }

    if ($hasText) {
        $backup[$code] = ['ID' => $section['ID'], 'DESCRIPTION' => $section['DESCRIPTION'], 'DESCRIPTION_TYPE' => $section['DESCRIPTION_TYPE']];
    }

    $bs = new CIBlockSection();
    $ok = $bs->Update((int)$section['ID'], ['DESCRIPTION' => $html, 'DESCRIPTION_TYPE' => 'html']);
    if (!$ok) {
        echo "ОШИБКА        $code: " . $bs->LAST_ERROR . "\n";
        continue;
    }

    // Проверка запросом к базе, а не по коду возврата.
    $check = CIBlockSection::GetList([], ['IBLOCK_ID' => SEO_IBLOCK_ID, 'ID' => $section['ID']], false, ['ID', 'DESCRIPTION', 'DESCRIPTION_TYPE'])->Fetch();
    if (trim((string)$check['DESCRIPTION']) === $html && $check['DESCRIPTION_TYPE'] === 'html') {
        echo "записано      $code (" . mb_strlen($html) . " симв.)\n";
        $stat['updated']++;
    } else {
        echo "НЕ СОВПАЛО ПОСЛЕ ЗАПИСИ $code\n";
    }
}

if ($backup && !$dry) {
    $path = $dir . '/_backup_' . date('Ymd_His') . '.json';
    file_put_contents($path, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "Бэкап заменённых текстов: $path\n";
}

echo "\nИтого: записано {$stat['updated']}, уже актуально {$stat['same']}, "
    . "пропущено (текст есть) {$stat['skipped_has_text']}, нет раздела {$stat['no_section']}\n";
