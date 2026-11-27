<?php
/**
 * Б24 #9505 «Мелкие правки по сайту ПЭ»:
 *  1) логотипы брендов (инфоблок 14, свойство LOGO) — заменить на присланные:
 *     hanse-textile, rkf, ata, brimato (вложения чек-листа, обрезаны и вписаны в 900×300 JPG);
 *     fermest-equipment — вместо квадратной иконки (в карточке видна «полоской») полный
 *     логотип «Фермест», тот же, что у бренда fermest.
 *  2) убрать абзац «<p><strong>В НАЛИЧИИ</strong></p>» из описаний товаров (инфоблок 11) —
 *     теперь его заменяет шильдик «В наличии» на фото карточки.
 *
 * Запуск (локально, на dev и на проде — из корня сайта):
 *   php local/deploy/brand_logos_b24_9505/import.php [--dry]
 * Идемпотентен.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

const BRANDS_IBLOCK_ID = 14;
const MARK = 'b24#9505';
$dry = in_array('--dry', $argv, true);
$stats = ['set' => [], 'replaced' => [], 'already' => [], 'no_brand' => [], 'text' => []];

foreach (glob(__DIR__ . '/img/*.{png,jpg}', GLOB_BRACE) as $path) {
    $code = pathinfo($path, PATHINFO_FILENAME);
    $b = CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK_ID, '=CODE' => $code], false, false,
        ['ID', 'NAME', 'PROPERTY_LOGO'])->Fetch();
    if (!$b) { $stats['no_brand'][] = $code; continue; }
    $old = (int) ($b['PROPERTY_LOGO_VALUE'] ?? 0);
    $oldFile = $old ? CFile::GetFileArray($old) : null;
    if ($oldFile && $oldFile['DESCRIPTION'] === MARK . ' ' . $code
        && is_file($_SERVER['DOCUMENT_ROOT'] . $oldFile['SRC'])) { $stats['already'][] = $code; continue; }
    $stats[$oldFile ? 'replaced' : 'set'][] = $code;
    if ($dry) { continue; }
    $file = CFile::MakeFileArray($path);
    $file['MODULE_ID'] = 'iblock';
    $file['description'] = MARK . ' ' . $code;
    // LOGO одиночное (MULTIPLE=N) — SetPropertyValuesEx заменяет значение
    CIBlockElement::SetPropertyValuesEx($b['ID'], BRANDS_IBLOCK_ID, ['LOGO' => $file]);
}

// абзац «В НАЛИЧИИ» в описаниях товаров
const STOCK_P = '#\\s*<p>\\s*<strong>\\s*В НАЛИЧИИ\\s*</strong>\\s*</p>#u';
$r = CIBlockElement::GetList([], ['IBLOCK_ID' => 11, ['LOGIC' => 'OR', '%PREVIEW_TEXT' => 'В НАЛИЧИИ', '%DETAIL_TEXT' => 'В НАЛИЧИИ']],
    false, false, ['ID', 'CODE', 'PREVIEW_TEXT', 'DETAIL_TEXT']);
while ($e = $r->Fetch()) {
    $f = [];
    foreach (['PREVIEW_TEXT', 'DETAIL_TEXT'] as $k) {
        $n = preg_replace(STOCK_P, '', (string) $e[$k]);
        if ($n !== (string) $e[$k]) { $f[$k] = $n; }
    }
    if (!$f) { continue; }
    $stats['text'][] = $e['CODE'];
    if (!$dry) { (new CIBlockElement())->Update($e['ID'], $f); }
}
if (!$dry) { CIBlock::clearIblockTagCache(11); }

if (!$dry) { CIBlock::clearIblockTagCache(BRANDS_IBLOCK_ID); BXClearCache(true, '/s1/bitrix/news.list/'); BXClearCache(true, '/s1/bitrix/news.detail/'); }
echo json_encode($stats, JSON_UNESCAPED_UNICODE), "\n";
