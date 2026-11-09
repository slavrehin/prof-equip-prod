<?php
/**
 * Локальная пересборка: раскладывает текст проектов (инфоблок 8) по свойствам, как это было на проде.
 *
 * Тексты новых проектов (docx из задач Б24 #8143, #8847) импортированы целиком в DETAIL_TEXT.
 * Шаблон project-detail показывает DETAIL_TEXT одним блоком «Описание проекта», а CSS
 * `.project-content__text strong{display:block}` разрывает каждую строку с жирным словом —
 * верстка «едет». На проде контент-менеджер вносил текст «блоками под подзаголовки, чтобы они
 * сворачивались в моб. версии» (Б24 #8847, 28.07.2026), т.е. в свойство BLOCKS.
 *
 * Что делает (только у проектов с пустым BLOCKS; DETAIL_TEXT не меняет, повторный запуск безопасен):
 *  - «Кратко о проекте» (или первый абзац из пар «<strong>Метка:</strong> значение<br>») →
 *    INFO_TITLE + LIST (значение, описание = метка) — голубой инфоблок шаблона;
 *  - каждая секция <h2>Заголовок</h2>…  → значение BLOCKS (HTML, описание = заголовок);
 *    вступительный h2 без текста (подзаголовок проекта) → первой строкой первого блока;
 *  - «Бренды» / «Бренд» → BRANDS (по названию в инфоблоке 14); если найдены не все — секция остаётся блоком;
 *  - внутри блоков <strong> посреди строки → <b> (иначе display:block разрывает предложение),
 *    <strong> на весь абзац остаётся подзаголовком.
 * Старые проекты из WP (без h2-секций «Кратко о проекте» и без пар «Метка: значение») не трогает.
 *
 *   php local/bootstrap/projects_blocks_fix.php [--dry]
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = 'localhost';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

const PROJECTS_IBLOCK = 8;
const BRANDS_IBLOCK = 14;
$dry = in_array('--dry', $argv, true);

function norm(string $s): string {
    $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace("\xC2\xA0", ' ', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

/** «<strong>Метка:</strong> значение<br>…» → [[метка, значение], …] или null, если абзац не такой. */
function parseLabelPairs(string $html): ?array {
    $parts = preg_split('~<br\s*/?>~i', $html);
    $pairs = [];
    foreach ($parts as $part) {
        if (norm($part) === '') { continue; }
        if (preg_match('~^\s*<(strong|b)>(.*?)</\1>(.*)$~isu', trim($part), $m)) {
            $label = rtrim(norm($m[2]), ': ');
            $value = norm($m[3]);
        } elseif (preg_match('~^([^:]{2,40}):(.+)$~su', norm($part), $m)) {   // «Объект: …» без жирного
            $label = trim($m[1]);
            $value = trim($m[2]);
        } else {
            return null;
        }
        $value = ltrim($value, ': ');
        if ($label === '' || $value === '') { return null; }
        $pairs[] = [$label, $value];
    }
    return count($pairs) >= 2 ? $pairs : null;
}

/** <strong> посреди строки → <b>; <strong> на весь <p>/<li> оставляем. */
function fixInlineStrong(string $html): string {
    return preg_replace_callback('~<(p|li)(\s[^>]*)?>(.*?)</\1>~isu', function ($m) {
        $inner = $m[3];
        if (preg_match('~^\s*<strong>(?:(?!</strong>).)*</strong>\s*$~isu', $inner)) {
            return $m[0];
        }
        $inner = preg_replace('~<strong>(.*?)</strong>~isu', '<b>$1</b>', $inner);
        return '<' . $m[1] . $m[2] . '>' . $inner . '</' . $m[1] . '>';
    }, $html);
}

function isEmptyHtml(string $html): bool {
    return norm(preg_replace('~<(ul|ol)>\s*</\1>~i', '', $html)) === '';
}

function brandKey(string $s): string {
    $s = mb_strtolower(norm($s));
    return strtr($s, ['ü' => 'u', 'ö' => 'o', 'ä' => 'a', 'é' => 'e', '«' => '', '»' => '', '"' => '']);
}

// бренды: нормализованное имя → ID
$brandByName = [];
$res = CIBlockElement::GetList([], ['IBLOCK_ID' => BRANDS_IBLOCK], false, false, ['ID', 'NAME', 'ACTIVE']);
while ($b = $res->Fetch()) {
    $key = brandKey($b['NAME']);
    if (!isset($brandByName[$key]) || $b['ACTIVE'] === 'Y') { $brandByName[$key] = (int)$b['ID']; }
}
$brandAliases = ['ооо профэквип' => 'профэквип', 'alpha by jensen' => 'jensen', 'muhldorfer' => 'muhldorfer'];

$stats = ['seen' => 0, 'done' => 0, 'skipped_old' => 0, 'skipped_has_blocks' => 0];
$res = CIBlockElement::GetList(['ID' => 'ASC'], ['IBLOCK_ID' => PROJECTS_IBLOCK], false, false,
    ['ID', 'CODE', 'NAME', 'DETAIL_TEXT']);
while ($el = $res->Fetch()) {
    $stats['seen']++;
    $id = (int)$el['ID'];
    $text = (string)$el['DETAIL_TEXT'];

    $cur = CIBlockElement::GetProperty(PROJECTS_IBLOCK, $id, [], ['CODE' => 'BLOCKS'])->Fetch();
    if ($cur && !empty($cur['VALUE'])) { $stats['skipped_has_blocks']++; continue; }

    // разбиение на преамбулу и секции по <h2>
    $chunks = preg_split('~<h2[^>]*>(.*?)</h2>~isu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $preamble = array_shift($chunks);
    $sections = [];
    for ($i = 0; $i < count($chunks); $i += 2) {
        $sections[] = ['title' => norm($chunks[$i]), 'body' => trim($chunks[$i + 1] ?? '')];
    }

    $hasBrief = false;
    foreach ($sections as $s) { if (mb_stripos($s['title'], 'Кратко о проекте') === 0) { $hasBrief = true; } }

    // пары «Метка: значение» в первом абзаце преамбулы (формат «Конгресс отеля»)
    $preamblePairs = null;
    if (!$hasBrief && preg_match('~^\s*<p[^>]*>(.*?)</p>~isu', $preamble, $m)) {
        $preamblePairs = parseLabelPairs($m[1]);
        if ($preamblePairs) { $preamble = substr($preamble, strlen($m[0])); }
    }
    if (!$hasBrief && !$preamblePairs) { $stats['skipped_old']++; continue; }

    $infoTitle = 'Кратко о проекте';
    $list = $preamblePairs ?: [];
    $blocks = [];
    $brandIds = [];
    $lead = [];
    if (!isEmptyHtml($preamble)) {
        $blocks[] = ['title' => 'Описание проекта', 'body' => trim($preamble)];
    }

    for ($i = 0; $i < count($sections); $i++) {
        $s = $sections[$i];
        $title = $s['title'];
        $body = $s['body'];

        if (mb_stripos($title, 'Кратко о проекте') === 0) {
            if (preg_match('~<p[^>]*>(.*?)</p>~isu', $body, $m) && ($pairs = parseLabelPairs($m[1]))) {
                $list = $pairs;
                $rest = trim(str_replace($m[0], '', $body));
                if (!isEmptyHtml($rest)) { $blocks[] = ['title' => $title, 'body' => $rest]; }
            } else {
                $blocks[] = ['title' => $title, 'body' => $body];
            }
            continue;
        }

        if (preg_match('~^Бренды?$~u', $title)) {
            // «<h2>Бренд</h2><h2>Imesa</h2>» — имя бренда во втором h2
            if (isEmptyHtml($body) && isset($sections[$i + 1])) {
                $body = $sections[$i + 1]['title'];
                $i++;
            }
            $flat = preg_replace('~</(p|li)>|<br\s*/?>~i', ',', $body);
            $flat = preg_replace('~\([^)]*\)~u', '', norm($flat));
            $names = array_values(array_filter(array_map(fn($n) => trim($n, " .\t"), preg_split('~[,;•]~u', $flat))));
            $found = [];
            foreach ($names as $n) {
                $k = brandKey($n);
                $k = $brandAliases[$k] ?? $k;
                if (isset($brandByName[$k])) { $found[] = $brandByName[$k]; }
            }
            if ($names && count($found) === count($names)) {
                $brandIds = array_values(array_unique(array_merge($brandIds, $found)));
            } else {
                // нашлись не все — оставляем текстом, чтобы не показывать половину логотипов
                $blocks[] = ['title' => $title, 'body' => fixInlineStrong($body)];
                fwrite(STDERR, "  #$id бренды не все найдены: " . norm($body) . "\n");
            }
            continue;
        }

        if (isEmptyHtml($body)) {           // подзаголовок проекта без текста
            if ($title !== '') { $lead[] = $title; }
            continue;
        }
        $blocks[] = ['title' => rtrim($title, ':'), 'body' => $body];
    }

    if ($lead && $blocks) {
        $leadHtml = '';
        foreach ($lead as $l) { $leadHtml .= '<p><strong>' . htmlspecialchars($l) . '</strong></p>'; }
        $blocks[0]['body'] = $leadHtml . "\n" . $blocks[0]['body'];
    }
    foreach ($blocks as &$b) { $b['body'] = fixInlineStrong($b['body']); }
    unset($b);

    $propBlocks = [];
    foreach ($blocks as $n => $b) {
        $propBlocks['n' . $n] = ['VALUE' => ['TYPE' => 'html', 'TEXT' => $b['body']], 'DESCRIPTION' => $b['title']];
    }
    $propList = [];
    foreach ($list as $n => [$label, $value]) {
        $propList['n' . $n] = ['VALUE' => $value, 'DESCRIPTION' => $label];
    }

    printf("#%d %s: LIST %d, BLOCKS %d [%s], BRANDS %s%s\n", $id, $el['CODE'], count($propList), count($propBlocks),
        implode(' | ', array_column($blocks, 'title')), $brandIds ? implode(',', $brandIds) : '—',
        $lead ? ', лид: ' . implode(' / ', $lead) : '');

    if ($dry) { continue; }
    $values = ['BLOCKS' => $propBlocks];
    if ($propList) {
        $values['LIST'] = $propList;
        $values['INFO_TITLE'] = $infoTitle;
    }
    $curBrands = CIBlockElement::GetProperty(PROJECTS_IBLOCK, $id, [], ['CODE' => 'BRANDS'])->Fetch();
    if ($brandIds && empty($curBrands['VALUE'])) { $values['BRANDS'] = $brandIds; }
    CIBlockElement::SetPropertyValuesEx($id, PROJECTS_IBLOCK, $values);
    $stats['done']++;
}

if (!$dry) {
    \CIBlock::clearIblockTagCache(PROJECTS_IBLOCK);
    BXClearCache(true, '/');
}
echo json_encode($stats, JSON_UNESCAPED_UNICODE), "\n";
