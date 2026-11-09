<?php
/**
 * Описания брендов с остатками страницы WooCommerce (2026-11-09).
 *
 * При переносе с WordPress у 14 брендов в PREVIEW_TEXT/DETAIL_TEXT попала вся область страницы
 * бренда: <header class="woocommerce-products-header"> со старым логотипом, кнопка «ПОЛУЧИТЬ
 * КОНСУЛЬТАЦИЮ» (vc_row), чипсы фильтра, «Grid view / List view», «Отображение 1–16 из 66»,
 * фильтр и сетка товаров с картинками /wp-content/... На странице /brends/<код>/ всё это
 * выводилось сырым HTML внизу блока .brand-about__text.
 *
 * Скрипт оставляет только содержимое <div class="term-description"> (собственно текст бренда)
 * без кнопки vc_row. Если такого блока нет (у бренда в WP текста не было) — описание становится пустым.
 * Перед правкой исходные тексты сохраняются в JSON рядом с корнем сайта (вне docs).
 *
 * Запуск (локально в контейнере, на dev и на проде — из корня сайта, от www-data):
 *   php local/deploy/brand_desc_cleanup_2026_11_09/import.php [--dry]
 * Идемпотентен: после очистки маркера woocommerce-products-header нет, повторный запуск ничего не меняет.
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
const MARKER = 'woocommerce-products-header';

$ib = \CIBlock::GetList([], ['CODE' => 'brands', 'CHECK_PERMISSIONS' => 'N'])->Fetch();
if (!$ib) { exit("Нет инфоблока brands\n"); }
$iblockId = (int) $ib['ID'];

/** Текст бренда из обёртки WooCommerce: innerHTML первого div.term-description без кнопок vc_row. */
function cleanBrandText(string $html): string
{
    if (!str_contains($html, MARKER)) { return $html; }
    $doc = new \DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xp = new \DOMXPath($doc);
    // в шапке WP обычно только логотип, но на проде у renzacci сюда вручную перенесён первый абзац — его сохраняем
    $intro = '';
    $head = $xp->query('//div[contains(concat(" ", normalize-space(@class), " "), " brand-description ")]')->item(0);
    if ($head && ($t = trim(preg_replace('/\s+/u', ' ', $head->textContent))) !== '') {
        $intro = '<p>' . htmlspecialchars($t, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>';
    }
    $node = $xp->query('//div[normalize-space(@class)="term-description"]')->item(0);
    if (!$node) { return $intro; }
    foreach (iterator_to_array($xp->query('.//div[contains(concat(" ", normalize-space(@class), " "), " vc_row ")]', $node)) as $btn) {
        $btn->parentNode->removeChild($btn);
    }
    $out = '';
    foreach ($node->childNodes as $ch) { $out .= $doc->saveHTML($ch); }
    $out = trim(preg_replace('/\s+/u', ' ', $intro . $out));
    return str_replace('> <', ">\n<", $out);
}

$rows = [];
$r = \CIBlockElement::GetList(['ID' => 'ASC'], [
    'IBLOCK_ID' => $iblockId, 'CHECK_PERMISSIONS' => 'N',
    [
        'LOGIC' => 'OR',
        ['%PREVIEW_TEXT' => MARKER],
        ['%DETAIL_TEXT' => MARKER],
    ],
], false, false, ['ID', 'CODE', 'NAME', 'PREVIEW_TEXT', 'DETAIL_TEXT']);
while ($e = $r->Fetch()) {
    // фильтр %… по тексту — грубый, перепроверяем
    if (!str_contains((string) $e['PREVIEW_TEXT'], MARKER) && !str_contains((string) $e['DETAIL_TEXT'], MARKER)) { continue; }
    $rows[] = $e;
}

if (!$rows) { echo ($dry ? '[dry] ' : '') . "очищено брендов: 0\n"; exit(0); }

if (!$dry) {
    $dir = dirname($_SERVER['DOCUMENT_ROOT']);
    if (!is_writable($dir)) { $dir = sys_get_temp_dir(); }
    $bak = $dir . '/brand_desc_backup_' . date('Ymd_His') . '.json';
    file_put_contents($bak, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "бэкап: $bak\n";
}

$el = new \CIBlockElement();
foreach ($rows as $e) {
    $prev = cleanBrandText((string) $e['PREVIEW_TEXT']);
    $det = cleanBrandText((string) $e['DETAIL_TEXT']);
    printf("%s#%d %s: %d → %d байт%s\n", $dry ? '[dry] ' : '', $e['ID'], $e['CODE'],
        strlen((string) $e['PREVIEW_TEXT']), strlen($prev), $prev === '' ? ' (текста нет, описание пустое)' : '');
    if ($dry && $prev !== '') { echo '      начало: ' . mb_substr(strip_tags($prev), 0, 90) . "…\n"; }
    if (!$dry) {
        $ok = $el->Update((int) $e['ID'], [
            'PREVIEW_TEXT' => $prev, 'PREVIEW_TEXT_TYPE' => 'html',
            'DETAIL_TEXT' => $det, 'DETAIL_TEXT_TYPE' => 'html',
        ]);
        if (!$ok) { throw new RuntimeException("#{$e['ID']}: " . $el->LAST_ERROR); }
    }
}
if (!$dry) { \CIBlock::clearIblockTagCache($iblockId); }
echo ($dry ? '[dry] ' : '') . 'очищено брендов: ' . count($rows) . "\n";
