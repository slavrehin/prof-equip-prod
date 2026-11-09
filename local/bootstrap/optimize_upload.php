<?php
// Ужимает крупные картинки в /upload: длинная сторона ≤ 1600 px, JPEG q85, PNG — сжатие 9 (прозрачность сохраняется).
// Имя и расширение файла не меняются; b_file (WIDTH/HEIGHT/FILE_SIZE) обновляется. Только файлы > 400 КБ.
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
global $DB;
ini_set('memory_limit', '2048M');
$max = 1600; $done = 0; $saved = 0;
$r = $DB->Query("SELECT ID, SUBDIR, FILE_NAME, CONTENT_TYPE FROM b_file WHERE MODULE_ID='iblock' AND FILE_SIZE > 400000 AND CONTENT_TYPE IN ('image/png','image/jpeg','image/webp')");
while ($f = $r->Fetch()) {
    $path = $_SERVER['DOCUMENT_ROOT'] . '/upload/' . $f['SUBDIR'] . '/' . $f['FILE_NAME'];
    if (!is_file($path)) continue;
    $before = filesize($path);
    [$w, $h] = @getimagesize($path) ?: [0, 0];
    if (!$w) continue;
    $im = match ($f['CONTENT_TYPE']) { 'image/png' => @imagecreatefrompng($path), 'image/jpeg' => @imagecreatefromjpeg($path), default => @imagecreatefromwebp($path) };
    if (!$im) continue;
    $k = min(1, $max / max($w, $h));
    $nw = (int) round($w * $k); $nh = (int) round($h * $k);
    if ($k < 1) {
        $dst = imagecreatetruecolor($nw, $nh);
        if ($f['CONTENT_TYPE'] !== 'image/jpeg') { imagealphablending($dst, false); imagesavealpha($dst, true); }
        imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($im); $im = $dst;
    } elseif ($f['CONTENT_TYPE'] !== 'image/jpeg') { imagesavealpha($im, true); }
    $tmp = $path . '.tmp';
    $ok = match ($f['CONTENT_TYPE']) { 'image/png' => imagepng($im, $tmp, 9), 'image/jpeg' => imagejpeg($im, $tmp, 85), default => imagewebp($im, $tmp, 85) };
    imagedestroy($im);
    if ($ok && filesize($tmp) < $before) {
        rename($tmp, $path); clearstatcache();
        $DB->Query("UPDATE b_file SET WIDTH=$nw, HEIGHT=$nh, FILE_SIZE=" . filesize($path) . " WHERE ID={$f['ID']}");
        $done++; $saved += $before - filesize($path);
    } else { @unlink($tmp); }
}
echo "ужато файлов: $done, сэкономлено: " . round($saved / 1048576) . " МБ\n";
