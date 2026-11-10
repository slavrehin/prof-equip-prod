<?php
/**
 * Блог: вшитые в текст виджеты «Наш ассортимент» / «Наши выполненные проекты» → штатные свойства (2026-11-10).
 *
 * При восстановлении в DETAIL_TEXT 29 статей попала готовая разметка виджетов: карусели/сетки WP-темы The7
 * (portfolio/products/blog-carousel, без её CSS/JS — огромные картинки по одной + «Read more») и уже
 * отрисованные Битриксом .blog-about-catalog (с картинками из потерянного /upload). Здесь виджеты вырезаны
 * из текста (build.py), а товары/проекты записаны в свойства CATALOG_PRODUCT_1/2, TITLE_CATALOG_1/2, PROJECTS —
 * их выводит шаблон (blog-detail: товарный блок на месте маркера <!--BLOG_CATALOG_N-->, проекты — слайдер
 * после статьи). Фотогалереи статей не трогаются.
 *
 * data.json строится build.py из DETAIL_TEXT прода; src_md5 — хэш исходного текста. Если текст статьи
 * с тех пор правили в админке (хэш не совпал), статья пропускается (--force — применить всё равно).
 *
 * Картинки в тексте (build.py): WP-картинка есть в /wp-content — остаётся; нет — подставляется сохранившийся
 * размер того же файла или его квадратная копия -400x400; картинки из потерянного /upload/medialibrary
 * заменяются картинками WP-версии статьи по порядку (только если их число совпадает); не нашлось — тег <img>
 * удаляется (вместе с опустевшей обёрткой), чтобы не было битых иконок. Файлы, которых не было в /wp-content
 * на серверах, лежат в wp-content-add/ — их нужно доложить в DOCUMENT_ROOT/wp-content (см. DEPLOY ниже).
 *
 * Плюс: обложка «Промышленные стиральные машины от 20 до 25 кг» — настоящая (нашлась в кэше test2),
 * вместо временной замены из blog_dates_covers_2026_11_10.
 *
 * DEPLOY: rsync -a wp-content-add/ DOCUMENT_ROOT/wp-content/ (только добавление), затем этот скрипт.
 *
 * Запуск (локально, на dev и на проде, из корня сайта, от www-data):
 *   php local/deploy/blog_widgets_2026_11_10/import.php [--dry] [--force]
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

$dry = in_array('--dry', $argv, true);
$force = in_array('--force', $argv, true);
$ibId = static fn($code) => (int) \CIBlock::GetList([], ['CODE' => $code, 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$ib = $ibId('blog');
$ibCatalog = 11;
$ibProjects = $ibId('projects') ?: 8;

$idsByCode = static function (int $iblock, array $codes): array {
    if (!$codes) return [];
    $map = [];
    $r = \CIBlockElement::GetList([], ['IBLOCK_ID' => $iblock, '=CODE' => $codes, 'ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N'],
        false, false, ['ID', 'CODE']);
    while ($e = $r->Fetch()) $map[$e['CODE']] = (int) $e['ID'];
    $out = [];
    foreach ($codes as $c) if (isset($map[$c])) $out[] = $map[$c];
    return $out;
};

$data = json_decode(file_get_contents(__DIR__ . '/data.json'), true)['articles'];
$be = new \CIBlockElement();
$st = ['done' => 0, 'already' => 0, 'changed_skip' => 0, 'missing' => 0, 'err' => 0];
foreach ($data as $row) {
    $e = \CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, '=CODE' => $row['code'], 'CHECK_PERMISSIONS' => 'N'],
        false, ['nTopCount' => 1], ['ID', 'DETAIL_TEXT'])->Fetch();
    if (!$e) { $st['missing']++; echo "нет статьи: {$row['code']}\n"; continue; }
    $cur = (string) $e['DETAIL_TEXT'];
    if ($cur === $row['detail_text']) { $st['already']++; continue; }
    if (md5($cur) !== $row['src_md5'] && !$force) {
        $st['changed_skip']++; echo "ПРОПУСК (текст правили после выгрузки): #{$e['ID']} {$row['code']}\n"; continue;
    }
    $props = ['PROJECTS' => $idsByCode($ibProjects, $row['projects']) ?: false];
    foreach ([1, 2] as $n) {
        $c = $row['catalogs'][$n - 1] ?? null;
        $props['CATALOG_PRODUCT_' . $n] = $c ? ($idsByCode($ibCatalog, $c['codes']) ?: false) : false;
        $props['TITLE_CATALOG_' . $n] = $c ? $c['title'] : '';
    }
    $info = sprintf('товары %s, проекты %d', implode('+', array_map(fn($n) => is_array($props['CATALOG_PRODUCT_' . $n]) ? count($props['CATALOG_PRODUCT_' . $n]) : 0, [1, 2])),
        is_array($props['PROJECTS']) ? count($props['PROJECTS']) : 0);
    if ($dry) { echo "[dry] #{$e['ID']} {$row['code']}: $info\n"; $st['done']++; continue; }
    if (!$be->Update($e['ID'], ['DETAIL_TEXT' => $row['detail_text'], 'DETAIL_TEXT_TYPE' => 'html'])) {
        $st['err']++; echo "ОШИБКА #{$e['ID']} {$row['code']}: {$be->LAST_ERROR}\n"; continue;
    }
    \CIBlockElement::SetPropertyValuesEx($e['ID'], $ib, $props);
    echo "#{$e['ID']} {$row['code']}: $info\n";
    $st['done']++;
}
// настоящая обложка одной статьи (идемпотентно: по имени исходного файла)
$coverCode = 'promyshlennye-stiralnye-mashiny-ot-20-do-25-kg';
$coverFile = __DIR__ . '/cover-' . $coverCode . '.jpg';
$c = \CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, '=CODE' => $coverCode, 'CHECK_PERMISSIONS' => 'N'], false, ['nTopCount' => 1],
    ['ID', 'PREVIEW_PICTURE'])->Fetch();
if ($c && is_file($coverFile)) {
    $f = $c['PREVIEW_PICTURE'] ? \CFile::GetFileArray($c['PREVIEW_PICTURE']) : null;
    if (!$f || $f['ORIGINAL_NAME'] !== basename($coverFile)) {
        if ($dry) { echo "[dry] обложка $coverCode будет заменена\n"; }
        else {
            $be->Update($c['ID'], ['PREVIEW_PICTURE' => \CFile::MakeFileArray($coverFile), 'DETAIL_PICTURE' => \CFile::MakeFileArray($coverFile)]);
            echo "обложка $coverCode заменена\n";
        }
    }
}
echo json_encode($st, JSON_UNESCAPED_UNICODE) . ($dry ? ' (dry)' : '') . "\n";
if (!$dry) {
    \CIBlock::clearIblockTagCache($ib);
    BXClearCache(true);
}
