<?php
// Аудит карточек товаров раздела каталога (с подразделами): нет фото / нет списка характеристик справа от фото.
// Только чтение. Справа от фото шаблон catalog.element выводит PREVIEW_TEXT (div.info__list).
// Запуск: sudo -u www-data DOCROOT=/var/www/prof-equip.ru/docs php audit_section_cards.php <section_code> > out.csv
// Столбцы (;): id;active;name;url;section_path;photo_state;photo_detail;preview_state;preview_len
//   photo_state: ok | none (нет фото вообще) | stub (только заглушки/битые файлы)
//   preview_state: list (>=2 строк «Параметр: значение» в p/br/li/tr) | prose (текст без характеристик) | empty
$_SERVER['DOCUMENT_ROOT'] = getenv('DOCROOT') ?: realpath(__DIR__ . '/../..');
define('NO_KEEP_STATISTIC', true); define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('iblock');
const IB = 11;
$code = $argv[1] ?? 'oborudovanie-professionalnoj-kuhni';
$STUB_MD5 = ['e9a7dade70f31b6b1a876eb7ba0998c7' => 'wp-stub', '00a39eeb6bccf3d7d4027c2bfa36b36f' => 'wp-stub',
    '85bf583dfaa296b390ae4bed7e285cdb' => 'wp-pictogram', 'b4ba041c74a560d96a274b03927d6d7c' => 'wp-pictogram'];

$root = CIBlockSection::GetList([], ['IBLOCK_ID' => IB, '=CODE' => $code, 'CHECK_PERMISSIONS' => 'N'], false, ['ID', 'LEFT_MARGIN', 'RIGHT_MARGIN'])->Fetch();
if (!$root) { fwrite(STDERR, "нет раздела $code\n"); exit(1); }
$secs = [];
$r = CIBlockSection::GetList(['LEFT_MARGIN' => 'ASC'], ['IBLOCK_ID' => IB, '>=LEFT_MARGIN' => $root['LEFT_MARGIN'], '<=RIGHT_MARGIN' => $root['RIGHT_MARGIN'], 'CHECK_PERMISSIONS' => 'N'], false, ['ID', 'NAME', 'IBLOCK_SECTION_ID', 'ACTIVE']);
while ($s = $r->Fetch()) $secs[$s['ID']] = $s;
$path = function ($id) use ($secs) { $p = []; while (isset($secs[$id])) { array_unshift($p, $secs[$id]['NAME']); $id = $secs[$id]['IBLOCK_SECTION_ID']; } return implode(' / ', $p); };

$state = function ($fid) use ($STUB_MD5) {
    static $cache = [];
    if (!$fid) return null;
    if (isset($cache[$fid])) return $cache[$fid];
    $f = CFile::GetFileArray($fid);
    if (!$f) return $cache[$fid] = 'norecord';
    $p = $_SERVER['DOCUMENT_ROOT'] . $f['SRC'];
    if (!is_file($p)) return $cache[$fid] = 'nofile';
    if ($w = $STUB_MD5[md5_file($p)] ?? null) return $cache[$fid] = $w;
    $info = @getimagesize($p);
    if (!$info) return $cache[$fid] = 'broken';
    if ($info[0] === 800 && $info[1] === 600) {
        $im = @imagecreatefromstring((string) file_get_contents($p));
        if ($im) { $bg = function ($x, $y) use ($im) { $q = imagecolorat($im, $x, $y);
            return abs((($q >> 16) & 255) - 236) <= 8 && abs((($q >> 8) & 255) - 239) <= 8 && abs(($q & 255) - 243) <= 8; };
            if ($bg(5, 5) && $bg(400, 100) && $bg(790, 590) && ((imagecolorat($im, 20, 300) >> 8) & 255) < 170) return $cache[$fid] = 'grey-placeholder'; }
    }
    return $cache[$fid] = 'ok';
};

$out = fopen('php://stdout', 'w');
fputcsv($out, ['id', 'active', 'name', 'url', 'section_path', 'photo_state', 'photo_detail', 'preview_state', 'preview_len', 'kv_lines', 'preview_lines'], ';');
$seen = [];
$r = CIBlockElement::GetList(['ID' => 'ASC'], ['IBLOCK_ID' => IB, 'SECTION_ID' => $root['ID'], 'INCLUDE_SUBSECTIONS' => 'Y', 'CHECK_PERMISSIONS' => 'N'], false, false,
    ['ID', 'IBLOCK_ID', 'ACTIVE', 'NAME', 'IBLOCK_SECTION_ID', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'PREVIEW_TEXT']);
while ($ob = $r->GetNextElement(true, false)) {
    $e = $ob->GetFields();
    if (isset($seen[$e['ID']])) continue; $seen[$e['ID']] = 1;
    $gal = $ob->GetProperty('GALLERY')['VALUE'] ?: [];
    if (!is_array($gal)) $gal = [$gal];
    $slots = ['preview' => $state($e['PREVIEW_PICTURE']), 'detail' => $state($e['DETAIL_PICTURE'])];
    foreach ($gal as $i => $g) $slots['gallery' . ($i + 1)] = $state($g);
    $slots = array_filter($slots);
    $real = array_filter($slots, fn($s) => $s === 'ok');
    $photo = $real ? 'ok' : ($slots ? 'stub' : 'none');
    $det = implode(',', array_map(fn($k, $v) => "$k=$v", array_keys($slots), $slots));
    // секция для пути: основная, если в дереве; иначе первая привязка внутри дерева
    $sid = $e['IBLOCK_SECTION_ID'];
    if (!isset($secs[$sid])) { $gr = CIBlockElement::GetElementGroups($e['ID'], true, ['ID']); while ($g = $gr->Fetch()) if (isset($secs[$g['ID']])) { $sid = $g['ID']; break; } }
    $pt = trim((string) $e['PREVIEW_TEXT']);
    $plain = trim(html_entity_decode(strip_tags($pt), ENT_QUOTES, 'UTF-8'), " \t\n\r\0\x0B\xC2\xA0");
    // строки анонса: <p>, <br>, <li>, <tr> -> отдельные строки; «Параметр: значение» или пара ячеек таблицы
    $lines = preg_split('~\n+~', html_entity_decode(strip_tags(preg_replace('~</(p|li|tr|h\d|div)>|<br\s*/?>~i', "\n", preg_replace('~</td>\s*<td~i', ': </td><td', $pt))), ENT_QUOTES, 'UTF-8'));
    $lines = array_values(array_filter(array_map(fn($l) => trim($l, " \t\r\0\x0B\xC2\xA0"), $lines), 'strlen'));
    $kv = count(array_filter($lines, fn($l) => preg_match('~^[^:]{2,60}:\s*\S~u', $l)));
    $pst = $plain === '' ? 'empty' : ($kv >= 2 ? 'list' : 'prose');
    fputcsv($out, [$e['ID'], $e['ACTIVE'], $e['NAME'], 'https://prof-equip.ru' . $e['DETAIL_PAGE_URL'], $path($sid), $photo, $det, $pst, mb_strlen($plain), $kv, mb_substr(implode(' | ', $lines), 0, 300)], ';');
}
