<?php
// Картинки блоков главной из вёрстки агентства (local/layout/dist/assets/img) — пока нет боевых /upload.
// Первый экран, «О компании», направления, преимущества, клиенты, бренды. Перезаписывает.
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('iblock');
global $DB;
$img = $_SERVER['DOCUMENT_ROOT'] . '/local/layout/dist/assets/img';
$ib = fn($c) => (int) CIBlock::GetList([], ['CODE' => $c, 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$fa = fn($p) => is_file($p) ? CFile::MakeFileArray($p) : null;
$multi = function (array $paths) use ($fa) { $v = []; foreach ($paths as $i => $p) if ($a = $fa($p)) $v['n' . $i] = ['VALUE' => $a]; return $v; };
$el = fn($ibc, $code) => CIBlockElement::GetList([], ['IBLOCK_ID' => $ib($ibc), '=CODE' => $code], false, false, ['ID'])->Fetch()['ID'] ?? 0;
$setPic = function ($id, $path) use ($DB) {
    $a = CFile::MakeFileArray($path); $a['MODULE_ID'] = 'iblock';
    $fid = (int) CFile::SaveFile($a, 'iblock');
    $DB->Query("UPDATE b_iblock_element SET PREVIEW_PICTURE=$fid, DETAIL_PICTURE=$fid WHERE ID=" . (int) $id);
};

if ($id = $el('herohome', 'herohome')) {
    CIBlockElement::SetPropertyValuesEx($id, $ib('herohome'), [
        'HERO_SLIDER_IMAGES' => $multi(["$img/home/hero@2x.jpg", "$img/home/hero2.jpg", "$img/home/hero3.jpg", "$img/home/hero4.jpg", "$img/home/hero5.jpg"]),
        'LOGO' => $fa("$img/home/about@2x.png"),
    ]);
    echo "первый экран: слайдер и картинка блока «О компании»\n";
}
if ($id = $el('brandhome', 'brandhome')) {
    $cl = []; for ($i = 1; $i <= 12; $i++) $cl[] = "$img/clients/$i@2x.png";
    $br = []; for ($i = 1; $i <= 11; $i++) $br[] = "$img/brands/$i@2x.png";
    CIBlockElement::SetPropertyValuesEx($id, $ib('brandhome'), ['CLIENTS' => $multi($cl), 'BRANDS' => $multi($br)]);
    echo "клиенты и бренды: логотипы\n";
}
foreach (['directionshome' => ['areas-activity', 'jpg'], 'advantages' => ['advantages', 'png']] as $code => [$dir, $ext]) {
    $r = CIBlockElement::GetList(['SORT' => 'ASC', 'ID' => 'ASC'], ['IBLOCK_ID' => $ib($code)], false, false, ['ID']);
    $n = 0;
    while ($x = $r->Fetch()) { $n++; if (is_file("$img/$dir/$n@2x.$ext")) $setPic($x['ID'], "$img/$dir/$n@2x.$ext"); }
    echo "$code: картинки для $n\n";
}
