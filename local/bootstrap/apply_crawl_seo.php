<?php
/**
 * SEO из краула Screaming Frog 2026-07-02 (сайт уже на Битриксе) -> разделы и элементы.
 * Разделы каталога: title/description всегда из краула (снимков разделов в архиве мало, иначе наследуют родителя).
 * Элементы: title/description из краула всегда, когда они там есть. Создаёт недостающие разделы каталога, которые есть в краула (имя = H1), без родителя.
 *
 * Запуск: docker compose exec -T web php /var/www/html/local/bootstrap/apply_crawl_seo.php
 */
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('iblock');

$seo = json_decode(file_get_contents(__DIR__ . '/data/seo_crawl_2026-07-02.json'), true);
$ib = fn($c) => (int) CIBlock::GetList([], ['CODE' => $c, 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$prefix = ['/product/' => 'catalog', '/brends/' => 'brands', '/blog/' => 'blog', '/novosti/' => 'news',
    '/portfolio/' => 'projects', '/gotovye-resheniya/' => 'solution'];
$cat = $ib('catalog');
$bs = new CIBlockSection();
$el = new CIBlockElement();
$stat = ['sections' => 0, 'sections_new' => 0, 'elements' => 0, 'not_found' => 0];
$notFound = [];

foreach ($seo as $path => $m) {
    $tpl = array_filter(['TITLE' => $m['title'], 'DESCRIPTION' => $m['description']], fn($v) => $v !== '' && $v !== 'Title');
    if (preg_match('#^/product-category/([^/]+)/$#', $path, $mm)) {
        $s = CIBlockSection::GetList([], ['IBLOCK_ID' => $cat, '=CODE' => $mm[1], 'CHECK_PERMISSIONS' => 'N'], false, ['ID'])->Fetch();
        $f = ['IPROPERTY_TEMPLATES' => ['SECTION_META_TITLE' => $tpl['TITLE'] ?? '', 'SECTION_META_DESCRIPTION' => $tpl['DESCRIPTION'] ?? '']];
        if (!$s) {
            if (!$m['h1']) continue;
            $f += ['IBLOCK_ID' => $cat, 'CODE' => $mm[1], 'NAME' => $m['h1'], 'ACTIVE' => 'Y'];
            if ($bs->Add($f, false)) $stat['sections_new']++;
            continue;
        }
        $bs->Update($s['ID'], $f, false);
        $stat['sections']++;
        continue;
    }
    foreach ($prefix as $px => $code) {
        if (!preg_match('#^' . preg_quote($px, '#') . '([^/]+)/$#', $path, $mm)) continue;
        $e = CIBlockElement::GetList([], ['IBLOCK_ID' => $ib($code), '=CODE' => $mm[1], 'CHECK_PERMISSIONS' => 'N'], false, false, ['ID', 'XML_ID'])->Fetch();
        if (!$e) { $stat['not_found']++; $notFound[] = $path; break; }
        // краул (июль, Битрикс) новее почти всех снимков архива — берём его всегда, если там есть title
        if ($tpl) {
            $el->Update($e['ID'], ['IPROPERTY_TEMPLATES' => ['ELEMENT_META_TITLE' => $tpl['TITLE'] ?? '', 'ELEMENT_META_DESCRIPTION' => $tpl['DESCRIPTION'] ?? '']]);
            $stat['elements']++;
        }
        break;
    }
}
CIBlockSection::ReSort($cat);
file_put_contents(__DIR__ . '/data/crawl_not_in_db.txt', implode("\n", $notFound));
foreach ($stat as $k => $v) echo "$k: $v\n";
echo "адреса из краула, которых нет в базе: data/crawl_not_in_db.txt\n";
