<?php
/**
 * Подставляет фото элементам по списку (JSON): [{"iblock":"catalog","code":"...","files":["/mnt/photos/..."],"mode":"product|logo|picture","source":"..."}]
 * Ставит, только если у элемента картинки нет или стоит заглушка (800×600 с фоном placeholder.php), с --force — всегда.
 *  product: PREVIEW_PICTURE + DETAIL_PICTURE = 1-й файл, GALLERY = все файлы;
 *  logo:    свойство LOGO + PREVIEW_PICTURE (бренды);
 *  picture: PREVIEW_PICTURE + DETAIL_PICTURE.
 * Картинки элементу — прямым SQL (см. «Известные грабли» в CLAUDE.md: Update() с картинками может обнулить раздел).
 * Запуск: docker compose exec -T web php /var/www/html/local/bootstrap/apply_photos.php data/photos_b24.json [--force]
 */
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('iblock');
global $DB;

$list = json_decode(file_get_contents(__DIR__ . '/' . $argv[1]), true);
$force = in_array('--force', $argv, true);
$ib = function ($c) { static $m = []; return $m[$c] ??= (int) CIBlock::GetList([], ['CODE' => $c, 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID']; };
$isPlaceholder = function ($fid) {
    if (!$fid) return true;
    $f = CFile::GetFileArray($fid);
    if (!$f) return true;
    if ((int) $f['WIDTH'] !== 800 || (int) $f['HEIGHT'] !== 600) return false;
    // заглушка placeholder.php: серо-голубой фон (236,239,243) и тёмная рамка; размер JPEG бывает и >15 КБ
    $im = @imagecreatefromstring((string) @file_get_contents($_SERVER['DOCUMENT_ROOT'] . $f['SRC']));
    if (!$im) return true;
    $bg = function ($x, $y) use ($im) { $p = imagecolorat($im, $x, $y);
        return abs((($p >> 16) & 255) - 236) <= 8 && abs((($p >> 8) & 255) - 239) <= 8 && abs(($p & 255) - 243) <= 8; };
    return $bg(5, 5) && $bg(400, 100) && $bg(790, 590) && ((imagecolorat($im, 20, 300) >> 8) & 255) < 170;
};
$save = function ($path, $desc) {
    $a = CFile::MakeFileArray($path);
    if (!$a || empty($a['size'])) return 0;
    $a['MODULE_ID'] = 'iblock';
    $a['description'] = $desc;
    return (int) CFile::SaveFile($a, 'iblock');
};
$stat = ['set' => 0, 'skip_has_photo' => 0, 'not_found' => 0, 'bad_file' => 0];
foreach ($list as $it) {
    $iblockId = $ib($it['iblock']);
    $el = CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, '=CODE' => $it['code'], 'CHECK_PERMISSIONS' => 'N'], false, false, ['ID', 'NAME', 'PREVIEW_PICTURE'])->Fetch();
    if (!$el) { $stat['not_found']++; echo "  нет элемента {$it['iblock']}/{$it['code']}\n"; continue; }
    if (!$force && !$isPlaceholder($el['PREVIEW_PICTURE'])) { $stat['skip_has_photo']++; continue; }
    $files = array_values(array_filter($it['files'], 'is_file'));
    if (!$files) { $stat['bad_file']++; echo "  нет файлов для {$it['code']}\n"; continue; }
    $mode = $it['mode'] ?? 'product';
    $id = (int) $el['ID'];
    $main = $save($files[0], $el['NAME']);
    if (!$main) { $stat['bad_file']++; continue; }
    $detail = $mode === 'logo' ? $main : $save($files[0], $el['NAME']);
    $DB->Query("UPDATE b_iblock_element SET PREVIEW_PICTURE=$main, DETAIL_PICTURE=$detail, TIMESTAMP_X=NOW() WHERE ID=$id");
    if ($mode === 'logo') {
        CIBlockElement::SetPropertyValuesEx($id, $iblockId, ['LOGO' => CFile::MakeFileArray($files[0])]);
    } elseif ($mode === 'product') {
        $g = [];
        foreach ($files as $k => $f) $g['n' . $k] = ['VALUE' => CFile::MakeFileArray($f), 'DESCRIPTION' => $el['NAME']];
        CIBlockElement::SetPropertyValuesEx($id, $iblockId, ['GALLERY' => $g]);
    }
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex($iblockId, $id);
    $stat['set']++;
}
CIBlock::clearIblockTagCache($ib('catalog'));
foreach ($stat as $k => $v) echo "$k: $v\n";
