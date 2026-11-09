<?php
/**
 * Фото товаров каталога партиями (аудит 2026-11-09: local/bootstrap/audit_photos_v2.php, реестр site/checks).
 *
 * Партия — папка batches/<имя>/ с map.json и картинками в img/:
 *   [{"code": "<CODE товара>", "files": ["img/a.jpg", "img/b.jpg"], "source": "откуда взято"}]
 * Первый файл → PREVIEW_PICTURE и DETAIL_PICTURE, все файлы → свойство GALLERY (галерея заменяется целиком).
 * Картинки готовит site/photos/_scripts/normalize.py: белые поля до квадрата, JPEG.
 *
 * Фото ставится, только если у товара сейчас НЕТ нормального фото:
 *   - превью пусто или файла нет на диске;
 *   - серая заглушка «photo: restore from backup» (placeholder.php, 800×600);
 *   - WP-заглушка «Фото скоро появится» / пиктограмма (по md5 содержимого, см. $STUB_MD5).
 * С --force ставится всегда (для замены плохих фото на лучшие — только по осознанному списку).
 *
 * Запуск (локально в контейнере, на dev и на проде — из корня сайта, под www-data):
 *   php local/deploy/product_photos/import.php <партия> --log=/path/rollback.json [--dry] [--force]
 *   откат: php local/deploy/product_photos/import.php --rollback=/path/rollback.json
 * Идемпотентен: повторный запуск партии ничего не меняет (у товара уже нормальное фото).
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');
global $DB;

const CATALOG_IBLOCK_ID = 11;
const GALLERY_CODE = 'GALLERY';
// WP-заглушки, которые импорт поставил товарам вместо фото (md5 файлов, одинаковы локально и на проде)
$STUB_MD5 = [
    'e9a7dade70f31b6b1a876eb7ba0998c7' => 'WP «Фото скоро появится», инструменты',
    '00a39eeb6bccf3d7d4027c2bfa36b36f' => 'WP «Фото скоро появится», текстиль',
    '85bf583dfaa296b390ae4bed7e285cdb' => 'WP пиктограмма инструментов',
    'b4ba041c74a560d96a274b03927d6d7c' => 'WP пиктограмма прачечной машины',
];

$opts = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) { $opts[$m[1]] = $m[2] ?? true; } else { $opts['batch'] = $a; }
}
$dry = !empty($opts['dry']);
$force = !empty($opts['force']);
$gp = CIBlockProperty::GetList([], ['IBLOCK_ID' => CATALOG_IBLOCK_ID, 'CODE' => GALLERY_CODE])->Fetch();
$galleryPropId = (int) $gp['ID'];

// ---------- откат ----------
if (!empty($opts['rollback'])) {
    $log = json_decode(file_get_contents($opts['rollback']), true);
    foreach ($log['changed'] as $id => $old) {
        $id = (int) $id;
        $DB->Query(sprintf('UPDATE b_iblock_element SET PREVIEW_PICTURE=%s, DETAIL_PICTURE=%s, TIMESTAMP_X=NOW() WHERE ID=%d',
            $old['preview'] ?: 'NULL', $old['detail'] ?: 'NULL', $id));
        $DB->Query("DELETE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID=$galleryPropId");
        foreach ($old['gallery'] as $fid) {
            $DB->Query("INSERT INTO b_iblock_element_property (IBLOCK_ELEMENT_ID, IBLOCK_PROPERTY_ID, VALUE, VALUE_NUM) VALUES ($id, $galleryPropId, '" . (int) $fid . "', " . (int) $fid . ")");
        }
        \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(CATALOG_IBLOCK_ID, $id);
        echo "откат $id\n";
    }
    CIBlock::clearIblockTagCache(CATALOG_IBLOCK_ID);
    exit(0);
}

$batchDir = __DIR__ . '/batches/' . basename((string) ($opts['batch'] ?? ''));
if (empty($opts['batch']) || !is_file("$batchDir/map.json")) { fwrite(STDERR, "Нет партии: $batchDir/map.json\n"); exit(1); }
if (!$dry && empty($opts['log'])) { fwrite(STDERR, "Укажите --log=путь.json для отката\n"); exit(1); }
$map = json_decode(file_get_contents("$batchDir/map.json"), true);

// причина, по которой текущее превью считается «нет фото»; null — фото нормальное
$whyReplace = function ($fid) use ($STUB_MD5) {
    if (!$fid) return 'пусто';
    $f = CFile::GetFileArray($fid);
    if (!$f) return 'нет записи b_file';
    $path = $_SERVER['DOCUMENT_ROOT'] . $f['SRC'];
    if (!is_file($path)) return 'нет файла';
    if ($why = $STUB_MD5[md5_file($path)] ?? null) return $why;
    $im = @imagecreatefromstring((string) file_get_contents($path));
    if (!$im) return 'битый файл';
    if (imagesx($im) === 800 && imagesy($im) === 600) {
        $bg = function ($x, $y) use ($im) { $p = imagecolorat($im, $x, $y);
            return abs((($p >> 16) & 255) - 236) <= 8 && abs((($p >> 8) & 255) - 239) <= 8 && abs(($p & 255) - 243) <= 8; };
        if ($bg(5, 5) && $bg(400, 100) && $bg(790, 590) && ((imagecolorat($im, 20, 300) >> 8) & 255) < 170) return 'серая заглушка';
    }
    return null;
};

$log = ['date' => date('c'), 'env' => $_SERVER['SERVER_NAME'], 'batch' => basename($batchDir), 'changed' => []];
$stats = ['set' => [], 'has_photo' => [], 'not_found' => [], 'no_files' => []];
foreach ($map as $it) {
    $el = CIBlockElement::GetList([], ['IBLOCK_ID' => CATALOG_IBLOCK_ID, '=CODE' => $it['code'], 'CHECK_PERMISSIONS' => 'N'], false, false,
        ['ID', 'NAME', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'])->Fetch();
    if (!$el) { $stats['not_found'][] = $it['code']; continue; }
    $why = $whyReplace((int) $el['PREVIEW_PICTURE']);
    if ($why === null && !$force) { $stats['has_photo'][] = $it['code']; continue; }
    $files = [];
    foreach ($it['files'] as $rel) { $p = "$batchDir/$rel"; if (is_file($p)) $files[] = $p; }
    if (!$files) { $stats['no_files'][] = $it['code']; continue; }
    $id = (int) $el['ID'];
    $stats['set'][] = "$id {$it['code']} (было: " . ($why ?? 'фото, --force') . ')';
    if ($dry) continue;

    $oldGallery = [];
    $q = $DB->Query("SELECT VALUE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID=$galleryPropId ORDER BY ID");
    while ($v = $q->Fetch()) $oldGallery[] = (int) $v['VALUE'];
    $log['changed'][$id] = ['code' => $it['code'], 'preview' => (int) $el['PREVIEW_PICTURE'], 'detail' => (int) $el['DETAIL_PICTURE'], 'gallery' => $oldGallery];

    $save = function ($path, $n) use ($el) {
        $a = CFile::MakeFileArray($path);
        $a['MODULE_ID'] = 'iblock';
        $a['description'] = $n ? $el['NAME'] . ' — фото ' . ($n + 1) : $el['NAME'];
        return (int) CFile::SaveFile($a, 'iblock');
    };
    $main = $save($files[0], 0);
    $detail = $save($files[0], 0);
    if (!$main || !$detail) { fwrite(STDERR, "Файл не сохранён: {$files[0]}\n"); exit(1); }
    // картинки элементу прямым SQL: CIBlockElement::Update с картинками может обнулить раздел (CLAUDE.md, «грабли»)
    $DB->Query("UPDATE b_iblock_element SET PREVIEW_PICTURE=$main, DETAIL_PICTURE=$detail, TIMESTAMP_X=NOW() WHERE ID=$id");
    // галерея: детальная уже первая в слайдере карточки, поэтому в GALLERY — только дополнительные ракурсы
    $DB->Query("DELETE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID=$galleryPropId");
    foreach (array_slice($files, 1) as $k => $p) {
        $fid = $save($p, $k + 1);
        $DB->Query("INSERT INTO b_iblock_element_property (IBLOCK_ELEMENT_ID, IBLOCK_PROPERTY_ID, VALUE, VALUE_NUM, DESCRIPTION) VALUES ($id, $galleryPropId, '$fid', $fid, '" . $DB->ForSql($el['NAME']) . "')");
    }
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(CATALOG_IBLOCK_ID, $id);
    file_put_contents($opts['log'], json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
if (!$dry) { CIBlock::clearIblockTagCache(CATALOG_IBLOCK_ID); }
echo json_encode(array_map(fn ($v) => count($v), $stats), JSON_UNESCAPED_UNICODE), "\n";
foreach ($stats as $k => $v) if ($v && $k !== 'has_photo') echo "$k:\n  " . implode("\n  ", $v) . "\n";
