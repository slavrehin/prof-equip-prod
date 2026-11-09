<?php
// Бренды, которых нет в архиве: из краула 2026-07-02 (H1 + SEO), плюс нужные скриптам local/deploy (oasis).
// Описание пустое — заполнить из бэкапа/вручную. Идемпотентно по CODE.
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('iblock');
$ib = (int) CIBlock::GetList([], ['CODE' => 'brands', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$seo = json_decode(file_get_contents(__DIR__ . '/data/seo_crawl_2026-07-02.json'), true);
$want = ['oasis' => ['h1' => 'OASIS', 'title' => '', 'description' => '']];
foreach ($seo as $p => $m) if (preg_match('#^/brends/([^/]+)/$#', $p, $mm)) $want[$mm[1]] ??= $m;
$el = new CIBlockElement();
$n = 0;
foreach ($want as $code => $m) {
    if (CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, '=CODE' => $code], false, false, ['ID'])->Fetch()) continue;
    $id = $el->Add(['IBLOCK_ID' => $ib, 'CODE' => $code, 'NAME' => $m['h1'] ?: $code, 'ACTIVE' => 'Y', 'XML_ID' => 'stub:crawl-2026-07-02',
        'IPROPERTY_TEMPLATES' => array_filter(['ELEMENT_META_TITLE' => $m['title'], 'ELEMENT_META_DESCRIPTION' => $m['description']])]);
    echo $id ? "  бренд-заглушка $code -> $id\n" : "  ! $code: {$el->LAST_ERROR}\n";
    $n += (bool) $id;
}
echo "создано заглушек брендов: $n\n";
