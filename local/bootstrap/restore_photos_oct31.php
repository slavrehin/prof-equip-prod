<?php
/**
 * Откат фото каталога к состоянию бэкапа 31.10.2026 + удаление точных дублей в галерее.
 * Вход: JSON [{id, code, expect_detail, expect_gallery[], action: restore|fix, files[] | delete_prop_ids[], append[]}]
 *  restore: PREVIEW/DETAIL = files[0], GALLERY = files[1..] (старая галерея, в т.ч. заглушки, снимается);
 *  fix:     удалить строки GALLERY delete_prop_ids (точные копии DETAIL/других кадров), дописать append в конец.
 * Элемент пропускается, если его текущие DETAIL_PICTURE и GALLERY не совпадают с expect_* (состояние изменилось после аудита).
 * Файлы из upload не удаляются (дубль часто ссылается на тот же файл, что и DETAIL).
 * Запуск: php local/bootstrap/restore_photos_oct31.php /path/plan.json [--apply]
 */
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('iblock');
global $DB;

$plan = json_decode(file_get_contents($argv[1]), true);
$apply = in_array('--apply', $argv, true);
$iblockId = (int) CIBlock::GetList([], ['CODE' => 'catalog', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$propId = (int) CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'GALLERY'])->Fetch()['ID'];
$save = function ($path, $desc) {
    $a = CFile::MakeFileArray($path);
    if (!$a || empty($a['size'])) return 0;
    $a['MODULE_ID'] = 'iblock';
    $a['description'] = $desc;
    return (int) CFile::SaveFile($a, 'iblock');
};
$addGallery = function ($id, $fid, $desc) use ($DB, $propId) {
    $DB->Query("INSERT INTO b_iblock_element_property (IBLOCK_PROPERTY_ID, IBLOCK_ELEMENT_ID, VALUE, VALUE_TYPE, VALUE_NUM, DESCRIPTION)
        VALUES ($propId, $id, '$fid', 'text', $fid, '" . $DB->ForSql($desc) . "')");
};
$stat = ['restore' => 0, 'fix' => 0, 'deleted_vals' => 0, 'appended' => 0, 'skip_changed' => 0, 'skip_nofile' => 0];
foreach ($plan as $it) {
    $id = (int) $it['id'];
    $el = $DB->Query("SELECT ID, CODE, NAME, DETAIL_PICTURE FROM b_iblock_element WHERE ID=$id AND IBLOCK_ID=$iblockId")->Fetch();
    $gal = [];
    $r = $DB->Query("SELECT ID, VALUE_NUM FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID=$propId ORDER BY ID");
    while ($g = $r->Fetch()) $gal[(int) $g['ID']] = (int) $g['VALUE_NUM'];
    $curG = array_values($gal); $expG = $it['expect_gallery']; sort($curG); sort($expG);
    if (!$el || $el['CODE'] !== $it['code'] || (int) $el['DETAIL_PICTURE'] !== (int) $it['expect_detail'] || $curG !== $expG) {
        $stat['skip_changed']++; echo "  изменён после аудита, пропуск: {$it['code']}\n"; continue;
    }
    $files = $it['action'] === 'restore' ? $it['files'] : ($it['append'] ?? []);
    if (array_filter($files, fn($f) => !is_file($f))) { $stat['skip_nofile']++; echo "  нет файла бэкапа: {$it['code']}\n"; continue; }
    $name = $el['NAME'];
    if ($it['action'] === 'restore') {
        $stat['restore']++;
        if (!$apply) continue;
        $prev = $save($files[0], $name); $det = $save($files[0], $name);
        if (!$prev || !$det) { echo "  ошибка сохранения: {$it['code']}\n"; continue; }
        $DB->Query("UPDATE b_iblock_element SET PREVIEW_PICTURE=$prev, DETAIL_PICTURE=$det, TIMESTAMP_X=NOW() WHERE ID=$id");
        $DB->Query("DELETE FROM b_iblock_element_property WHERE IBLOCK_ELEMENT_ID=$id AND IBLOCK_PROPERTY_ID=$propId");
        foreach (array_slice($files, 1) as $k => $f) if ($fid = $save($f, $name . ' — фото ' . ($k + 2))) $addGallery($id, $fid, $name . ' — фото ' . ($k + 2));
    } else {
        $del = array_values(array_intersect(array_map('intval', $it['delete_prop_ids']), array_keys($gal)));
        $stat['fix']++; $stat['deleted_vals'] += count($del); $stat['appended'] += count($files);
        if (!$apply) continue;
        if ($del) $DB->Query("DELETE FROM b_iblock_element_property WHERE IBLOCK_PROPERTY_ID=$propId AND IBLOCK_ELEMENT_ID=$id AND ID IN (" . implode(',', $del) . ")");
        $n = count($gal) - count($del) + 1;
        foreach ($files as $f) { $n++; if ($fid = $save($f, "$name — фото $n")) $addGallery($id, $fid, "$name — фото $n"); }
        $DB->Query("UPDATE b_iblock_element SET TIMESTAMP_X=NOW() WHERE ID=$id");
    }
    \Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex($iblockId, $id);
}
if ($apply) CIBlock::clearIblockTagCache($iblockId);
echo ($apply ? "ПРИМЕНЕНО\n" : "DRY-RUN (без изменений; для записи добавьте --apply)\n");
foreach ($stat as $k => $v) echo "$k: $v\n";
