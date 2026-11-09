<?php
// Аудит фото товаров каталога (iblock catalog): нет картинки / заглушка / файл отсутствует на диске.
// Выводит CSV: id;code;active;preview_state;detail_state;gallery_count;gallery_ok;name
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true); define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('iblock');
global $DB;
$ib = (int) CIBlock::GetList([], ['CODE' => 'catalog', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$state = function ($fid) {
    if (!$fid) return 'none';
    $f = CFile::GetFileArray($fid);
    if (!$f) return 'norecord';
    if (!is_file($_SERVER['DOCUMENT_ROOT'] . $f['SRC'])) return 'nofile';
    if ((int)$f['WIDTH'] === 800 && (int)$f['HEIGHT'] === 600 && isPh($_SERVER['DOCUMENT_ROOT'] . $f['SRC'])) return 'placeholder';
    return 'ok';
};
function isPh($path) { // заглушка placeholder.php: фон (236,239,243) + рамка в 20px
    static $c = []; if (isset($c[$path])) return $c[$path];
    $im = @imagecreatefromstring(file_get_contents($path)); if (!$im) return $c[$path] = false;
    $near = function ($x, $y, $rgb) use ($im) { $p = imagecolorat($im, $x, $y); $a = [($p>>16)&255, ($p>>8)&255, $p&255];
        foreach ($a as $i => $v) if (abs($v - $rgb[$i]) > 8) return false; return true; };
    return $c[$path] = $near(5, 5, [236,239,243]) && $near(400, 100, [236,239,243]) && $near(790, 590, [236,239,243]) && ((imagecolorat($im, 20, 300) >> 8) & 255) < 170;
}
$gp = CIBlockProperty::GetList([], ['IBLOCK_ID' => $ib, 'CODE' => 'GALLERY'])->Fetch();
$out = fopen('php://stdout', 'w');
fputcsv($out, ['id','code','active','preview','detail','gallery','gallery_ok','name'], ';');
$r = CIBlockElement::GetList(['ID' => 'ASC'], ['IBLOCK_ID' => $ib, 'CHECK_PERMISSIONS' => 'N'], false, false, ['ID','CODE','NAME','ACTIVE','PREVIEW_PICTURE','DETAIL_PICTURE']);
while ($e = $r->Fetch()) {
    $g = 0; $gok = 0;
    if ($gp) {
        $q = $DB->Query("SELECT VALUE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID={$e['ID']} AND IBLOCK_PROPERTY_ID={$gp['ID']}");
        while ($v = $q->Fetch()) { $g++; if ($state((int)$v['VALUE']) === 'ok') $gok++; }
    }
    fputcsv($out, [$e['ID'],$e['CODE'],$e['ACTIVE'],$state($e['PREVIEW_PICTURE']),$state($e['DETAIL_PICTURE']),$g,$gok,$e['NAME']], ';');
}
