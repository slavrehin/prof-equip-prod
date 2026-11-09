<?php
// Картинки-заглушки для скриптов local/deploy, чьи фото лежали в /tmp погибшего сервера.
// php placeholder.php <dir> <имя файла> [...]  — создаёт <dir>/<имя> (PNG/JPEG по расширению), если файла нет.
$dir = rtrim($argv[1], '/');
foreach (array_slice($argv, 2) as $name) {
    $path = str_starts_with($name, '/') ? $name : "$dir/$name";
    if (is_file($path)) continue;
    @mkdir(dirname($path), 0777, true);
    $im = imagecreatetruecolor(800, 600);
    imagefill($im, 0, 0, imagecolorallocate($im, 236, 239, 243));
    $c = imagecolorallocate($im, 120, 130, 145);
    imagerectangle($im, 20, 20, 779, 579, $c);
    imagestring($im, 5, 230, 280, 'photo: restore from backup', $c);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $ok = match ($ext) { 'jpg', 'jpeg' => imagejpeg($im, $path, 85), 'webp' => imagewebp($im, $path), default => imagepng($im, $path) };
    echo ($ok ? 'заглушка ' : 'НЕ создана ') . $path . "\n";
}
