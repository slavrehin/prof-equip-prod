<?php
// Аудит фото товаров каталога (iblock catalog), версия 2 — не только «есть/нет», но и КАК фото выглядит
// в вёрстке. Листинг (.catalog-card .image-wrapper) и галерея карточки (.catalog-product-hero__swiper)
// показывают картинку в КВАДРАТЕ с object-fit: cover, поэтому неквадратное фото обрезается по длинной
// стороне. Скрипт считает для каждого файла: размер, пропорцию, цвет фона, рамку (поля) вокруг содержимого
// и долю содержимого, которая срезается квадратным кадром.
//
// Только чтение. Запуск:  sudo -u www-data php local/bootstrap/audit_photos_v2.php > /tmp/photo_audit_v2.csv
// Столбцы (CSV, разделитель ;):
//   product_id;code;active;name;sections;preview_fid;detail_fid;gallery_fids;
//   slot (preview|detail|gallery1..);fid;src;state (none|norecord|nofile|placeholder|broken|ok);
//   w;h;bytes;ratio;bg (white|light|dark|transparent|other);bg_rgb;
//   margin_l;margin_t;margin_r;margin_b (доли ширины/высоты, поля фона вокруг содержимого);
//   cut (доля площади содержимого, срезаемая квадратным кадром object-fit:cover)
// DOCROOT=/var/www/<site>/docs позволяет запускать скрипт из любой папки (на сервере local/bootstrap не выкладывается).
$_SERVER['DOCUMENT_ROOT'] = getenv('DOCROOT') ?: realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true); define('NOT_CHECK_PERMISSIONS', true);
ini_set('memory_limit', '1024M');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('iblock');
global $DB;
$ib = (int) CIBlock::GetList([], ['CODE' => 'catalog', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$gp = CIBlockProperty::GetList([], ['IBLOCK_ID' => $ib, 'CODE' => 'GALLERY'])->Fetch();

function isPh($im, $w, $h) { // заглушка placeholder.php: фон (236,239,243) + рамка в 20px, 800x600
    if ($w !== 800 || $h !== 600) return false;
    $near = function ($x, $y, $rgb) use ($im) { $p = imagecolorat($im, $x, $y); $a = [($p>>16)&255, ($p>>8)&255, $p&255];
        foreach ($a as $i => $v) if (abs($v - $rgb[$i]) > 8) return false; return true; };
    return $near(5, 5, [236,239,243]) && $near(400, 100, [236,239,243]) && $near(790, 590, [236,239,243]) && ((imagecolorat($im, 20, 300) >> 8) & 255) < 170;
}

// Анализ пикселей на уменьшенной копии (<=256px по длинной стороне).
function analyze($im, $w, $h) {
    $S = 256; $sw = $w >= $h ? $S : max(1, (int)round($w * $S / $h)); $sh = $h >= $w ? $S : max(1, (int)round($h * $S / $w));
    $sm = imagecreatetruecolor($sw, $sh); imagealphablending($sm, false); imagesavealpha($sm, true);
    $tr = imagecolorallocatealpha($sm, 255, 255, 255, 127); imagefill($sm, 0, 0, $tr);
    imagecopyresampled($sm, $im, 0, 0, 0, 0, $sw, $sh, $w, $h);
    $px = function ($x, $y) use ($sm) { $p = imagecolorat($sm, $x, $y); return [($p>>16)&255, ($p>>8)&255, $p&255, ($p>>24)&127]; };
    // фон: медиана по 4 углам (3x3) и серединам сторон
    $samples = []; $pts = [[1,1],[$sw-2,1],[1,$sh-2],[$sw-2,$sh-2],[(int)($sw/2),1],[(int)($sw/2),$sh-2],[1,(int)($sh/2)],[$sw-2,(int)($sh/2)]];
    $transparent = 0;
    foreach ($pts as [$x,$y]) { $c = $px(max(0,$x), max(0,$y)); if ($c[3] > 64) { $transparent++; continue; } $samples[] = $c; }
    if ($transparent >= 5) { $bg = 'transparent'; $bgc = [255,255,255]; }
    else {
        $ch = [[],[],[]]; foreach ($samples as $c) for ($i=0;$i<3;$i++) $ch[$i][] = $c[$i];
        $bgc = []; for ($i=0;$i<3;$i++) { sort($ch[$i]); $bgc[$i] = $ch[$i][(int)(count($ch[$i])/2)]; }
        $lum = 0.299*$bgc[0] + 0.587*$bgc[1] + 0.114*$bgc[2];
        $chroma = max($bgc) - min($bgc);
        if (min($bgc) >= 246 && $chroma <= 6) $bg = 'white';
        elseif ($lum >= 215 && $chroma <= 24) $bg = 'light';
        elseif ($lum < 110) $bg = 'dark';
        else $bg = 'other';
    }
    // ограничивающий прямоугольник содержимого: пиксели, заметно отличные от фона
    $thr = $bg === 'transparent' ? 0 : 28;
    $minx = $sw; $miny = $sh; $maxx = -1; $maxy = -1;
    for ($y = 0; $y < $sh; $y++) for ($x = 0; $x < $sw; $x++) {
        $c = $px($x, $y);
        if ($bg === 'transparent') { $isC = $c[3] <= 64; }
        else { $isC = $c[3] <= 64 && (abs($c[0]-$bgc[0]) > $thr || abs($c[1]-$bgc[1]) > $thr || abs($c[2]-$bgc[2]) > $thr); }
        if ($isC) { if ($x < $minx) $minx = $x; if ($x > $maxx) $maxx = $x; if ($y < $miny) $miny = $y; if ($y > $maxy) $maxy = $y; }
    }
    imagedestroy($sm);
    if ($maxx < 0) return ['bg' => $bg, 'bgc' => $bgc, 'ml' => 0, 'mt' => 0, 'mr' => 0, 'mb' => 0, 'cut' => 0, 'empty' => 1];
    $ml = $minx / $sw; $mr = ($sw - 1 - $maxx) / $sw; $mt = $miny / $sh; $mb = ($sh - 1 - $maxy) / $sh;
    // квадратный кадр object-fit:cover по центру: что из содержимого выпадает
    $side = min($sw, $sh); $cx0 = ($sw - $side) / 2; $cy0 = ($sh - $side) / 2; $cx1 = $cx0 + $side; $cy1 = $cy0 + $side;
    $bw = $maxx - $minx + 1; $bh = $maxy - $miny + 1;
    $vx0 = max($minx, $cx0); $vx1 = min($maxx + 1, $cx1); $vy0 = max($miny, $cy0); $vy1 = min($maxy + 1, $cy1);
    $vis = max(0, $vx1 - $vx0) * max(0, $vy1 - $vy0);
    $cut = 1 - $vis / ($bw * $bh);
    return ['bg' => $bg, 'bgc' => $bgc, 'ml' => $ml, 'mt' => $mt, 'mr' => $mr, 'mb' => $mb, 'cut' => max(0, $cut), 'empty' => 0];
}

$cache = [];
function fileRow($fid) {
    global $cache;
    if (!$fid) return ['state' => 'none'];
    if (isset($cache[$fid])) return $cache[$fid];
    $f = CFile::GetFileArray($fid);
    if (!$f) return $cache[$fid] = ['state' => 'norecord', 'fid' => $fid];
    $path = $_SERVER['DOCUMENT_ROOT'] . $f['SRC'];
    $row = ['fid' => $fid, 'src' => $f['SRC']];
    if (!is_file($path)) return $cache[$fid] = $row + ['state' => 'nofile'];
    $row['bytes'] = filesize($path);
    $im = @imagecreatefromstring(file_get_contents($path));
    if (!$im) return $cache[$fid] = $row + ['state' => 'broken'];
    $w = imagesx($im); $h = imagesy($im); $row['w'] = $w; $row['h'] = $h; $row['ratio'] = round($w / $h, 3);
    if (isPh($im, $w, $h)) { imagedestroy($im); return $cache[$fid] = $row + ['state' => 'placeholder']; }
    $a = analyze($im, $w, $h); imagedestroy($im);
    return $cache[$fid] = $row + ['state' => 'ok'] + $a;
}

// разделы товаров
$secNames = []; $q = $DB->Query("SELECT ID, NAME, CODE, DEPTH_LEVEL FROM b_iblock_section WHERE IBLOCK_ID={$ib}");
while ($s = $q->Fetch()) $secNames[(int)$s['ID']] = $s;
$elSec = []; $q = $DB->Query("SELECT se.IBLOCK_ELEMENT_ID, se.IBLOCK_SECTION_ID FROM b_iblock_section_element se JOIN b_iblock_element e ON e.ID=se.IBLOCK_ELEMENT_ID WHERE e.IBLOCK_ID={$ib}");
while ($s = $q->Fetch()) $elSec[(int)$s['IBLOCK_ELEMENT_ID']][] = (int)$s['IBLOCK_SECTION_ID'];

$out = fopen('php://stdout', 'w');
fputcsv($out, ['product_id','code','active','name','sections','preview_fid','detail_fid','gallery_fids','slot','fid','src','state','w','h','bytes','ratio','bg','bg_rgb','margin_l','margin_t','margin_r','margin_b','cut','empty'], ';');
$r = CIBlockElement::GetList(['ID' => 'ASC'], ['IBLOCK_ID' => $ib, 'CHECK_PERMISSIONS' => 'N'], false, false, ['ID','CODE','NAME','ACTIVE','PREVIEW_PICTURE','DETAIL_PICTURE']);
$n = 0;
while ($e = $r->Fetch()) {
    $gal = [];
    if ($gp) { $q = $DB->Query("SELECT VALUE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID={$e['ID']} AND IBLOCK_PROPERTY_ID={$gp['ID']} ORDER BY ID"); while ($v = $q->Fetch()) $gal[] = (int)$v['VALUE']; }
    $secs = []; foreach ($elSec[(int)$e['ID']] ?? [] as $sid) if (isset($secNames[$sid])) $secs[] = $secNames[$sid]['CODE'];
    $base = [$e['ID'], $e['CODE'], $e['ACTIVE'], $e['NAME'], implode('|', $secs), (int)$e['PREVIEW_PICTURE'], (int)$e['DETAIL_PICTURE'], implode('|', $gal)];
    $slots = [['preview', (int)$e['PREVIEW_PICTURE']], ['detail', (int)$e['DETAIL_PICTURE']]];
    foreach ($gal as $i => $g) $slots[] = ['gallery' . ($i + 1), $g];
    foreach ($slots as [$slot, $fid]) {
        $f = fileRow($fid);
        fputcsv($out, array_merge($base, [$slot, $f['fid'] ?? '', $f['src'] ?? '', $f['state'], $f['w'] ?? '', $f['h'] ?? '', $f['bytes'] ?? '', $f['ratio'] ?? '',
            $f['bg'] ?? '', isset($f['bgc']) ? implode(',', $f['bgc']) : '',
            isset($f['ml']) ? round($f['ml'], 3) : '', isset($f['mt']) ? round($f['mt'], 3) : '', isset($f['mr']) ? round($f['mr'], 3) : '', isset($f['mb']) ? round($f['mb'], 3) : '',
            isset($f['cut']) ? round($f['cut'], 3) : '', $f['empty'] ?? '']), ';');
    }
    if (++$n % 100 === 0) fwrite(STDERR, "$n\n");
}
fwrite(STDERR, "done $n products\n");
