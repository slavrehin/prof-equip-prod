<?php
/**
 * Winterhalter: карточки серий UC, PT и «Котломоечные машины Winterhalter» (созданы 2026-11-07)
 * остаются обзорными страницами серий (решение владельца 2026-11-27):
 *  1. в DETAIL_TEXT перед завершающим абзацем-призывом добавляется блок «Модели серии» со ссылками на
 *     активные карточки моделей из local/deploy/winterhalter_dishwashers (между маркерами <!-- wh-models -->,
 *     повторный запуск блок перестраивает — например, когда UF-XL станет активной);
 *  2. исправляются данные по официальным листовкам Winterhalter V02: высоты UC (715→725, 810→820 мм),
 *     вес UF «190-1305» → «190–305 кг»; то же значение UC-S в свойстве-списке GABARITY_MODELEY_SH_G_V.
 * Замена применяется, только если исходная строка встречается ровно один раз; если уже стоит новая — пропуск.
 * Иначе остановка без записи.
 *
 *   php local/deploy/winterhalter_series_links/apply.php --dry
 *   php local/deploy/winterhalter_series_links/apply.php --log=/path/rollback.json
 *   откат: php local/deploy/winterhalter_series_links/apply.php --rollback=/path/rollback.json
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

$opts = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) { $opts[$m[1]] = $m[2] ?? true; }
}
$dry = isset($opts['dry']);
const IBLOCK_ID = 11;
const M_OPEN = '<!-- wh-models -->';
const M_CLOSE = '<!-- /wh-models -->';

$cards = [
    'vstraivaemye-podstolnye-posudomoechnye-mashiny-winterhalter-uc' => [
        'cta' => '<p>Специалисты ПРОФЭКВИП',
        'title' => 'Модели серии UC',
        'models' => [
            'winterhalter-uc-s' => 'Winterhalter UC-S — самая компактная: ширина 460 мм, корзина 400×400 мм',
            'winterhalter-uc-m' => 'Winterhalter UC-M — корзина 500×500 мм, высота загрузки 309 мм',
            'winterhalter-uc-l' => 'Winterhalter UC-L — увеличенная высота загрузки 404 мм',
            'winterhalter-uc-xl' => 'Winterhalter UC-XL — корзина 500×540 мм, высота загрузки 404 мм',
        ],
        'replace' => [
            'UC-S 460×603×715 мм' => 'UC-S 460×603×725 мм',
            'UC-M 600×603×715 мм' => 'UC-M 600×603×725 мм',
            'UC-L 600×603×810 мм' => 'UC-L 600×603×820 мм',
            'UC-XL 600×637×810 мм' => 'UC-XL 600×637×820 мм',
        ],
    ],
    'kupolnye-posudomoechnye-mashiny-winterhalter-pt' => [
        'cta' => '<p>Специалисты ПРОФЭКВИП',
        'title' => 'Модели серии PT',
        'models' => [
            'winterhalter-pt-m' => 'Winterhalter PT-M — корзина 500×500 мм, высота загрузки 440 мм',
            'winterhalter-pt-l' => 'Winterhalter PT-L — корзины 500×500 и 500×600 мм',
            'winterhalter-pt-xl' => 'Winterhalter PT-XL — высота загрузки 560 мм',
            'winterhalter-pt-l-utensil' => 'Winterhalter PT-L Utensil — для посуды и кухонной утвари',
            'winterhalter-pt-xl-utensil' => 'Winterhalter PT-XL Utensil — для посуды и утвари, высота загрузки 560 мм',
        ],
        'replace' => [],
    ],
    'kotlomoechnye-mashiny-winterhalter' => [
        'cta' => '<p>Нужна котломоечная машина',
        'title' => 'Модели котломоечных машин Winterhalter',
        'models' => [
            'winterhalter-gs-630' => 'Winterhalter GS 630 — подстольная, высота 820 мм',
            'winterhalter-pt-l-utensil' => 'Winterhalter PT-L Utensil — купольная для посуды и утвари',
            'winterhalter-pt-xl-utensil' => 'Winterhalter PT-XL Utensil — купольная, высота загрузки 560 мм',
            'winterhalter-uf-m' => 'Winterhalter UF-M — корзина 612×672 мм, высота загрузки 640 мм',
            'winterhalter-uf-l' => 'Winterhalter UF-L — высота загрузки 800 мм',
            'winterhalter-uf-xl' => 'Winterhalter UF-XL — двойная ширина, корзина 1305×672 мм',
        ],
        'replace' => [
            '190-1305 кг' => '190–305 кг',
        ],
    ],
];
$enumFix = ['prop' => 'GABARITY_MODELEY_SH_G_V', 'old' => 'UC-S 460×603×715 мм', 'new' => 'UC-S 460×603×725 мм'];

function clearCache(): void
{
    foreach (['cache', 'managed_cache', 'stack_cache'] as $d) {
        foreach (glob($_SERVER['DOCUMENT_ROOT'] . "/bitrix/$d/*") ?: [] as $f) { \Bitrix\Main\IO\Directory::deleteDirectory($f); }
    }
}

function setDetail(int $id, string $html): void
{
    global $DB;
    // прямым SQL: CIBlockElement::Update тут не нужен (меняется только текст), и он не трогает раздел/картинки
    $DB->Query("UPDATE b_iblock_element SET DETAIL_TEXT='" . $DB->ForSql($html) . "', TIMESTAMP_X=NOW() WHERE ID=$id");
    \CIBlock::clearIblockTagCache(IBLOCK_ID);
}

$propId = (int) ($DB->Query("SELECT ID FROM b_iblock_property WHERE IBLOCK_ID=" . IBLOCK_ID . " AND CODE='" . $enumFix['prop'] . "'")->Fetch()['ID'] ?? 0);

// ---------- откат ----------
if (!empty($opts['rollback'])) {
    $log = json_decode(file_get_contents($opts['rollback']), true, 512, JSON_THROW_ON_ERROR);
    foreach ($log['detail'] as $code => $row) {
        if (!$dry) { setDetail((int) $row['id'], $row['before']); }
        echo ($dry ? '[dry] ' : '') . "возвращён текст $code\n";
    }
    if (!empty($log['enum'])) {
        if (!$dry) { $DB->Query("UPDATE b_iblock_property_enum SET VALUE='" . $DB->ForSql($log['enum']['before']) . "' WHERE ID=" . (int) $log['enum']['id']); }
        echo ($dry ? '[dry] ' : '') . "возвращено значение списка {$log['enum']['id']}\n";
    }
    if (!$dry) { clearCache(); }
    exit(0);
}
if (!$dry && empty($opts['log'])) { throw new RuntimeException('Укажите --log=/path/rollback.json (или --dry)'); }
if (!$dry && is_file($opts['log'])) { throw new RuntimeException("Лог {$opts['log']} уже существует"); }

$log = ['site' => $_SERVER['SERVER_NAME'], 'at' => date('c'), 'detail' => [], 'enum' => null];
$plan = [];
foreach ($cards as $code => $c) {
    $e = $DB->Query("SELECT ID, DETAIL_TEXT FROM b_iblock_element WHERE IBLOCK_ID=" . IBLOCK_ID . " AND CODE='" . $DB->ForSql($code) . "'")->Fetch();
    if (!$e) { throw new RuntimeException("нет карточки $code"); }
    $before = $e['DETAIL_TEXT'];
    $html = $before;
    foreach ($c['replace'] as $old => $new) {
        $n = substr_count($html, $old);
        if ($n === 1) { $html = str_replace($old, $new, $html); echo "  $code: «{$old}» → «{$new}»\n"; continue; }
        if ($n === 0 && substr_count($html, $new) === 1) { echo "  $code: «{$new}» уже стоит\n"; continue; }
        throw new RuntimeException("$code: «{$old}» найдено $n раз — остановка");
    }
    // блок ссылок: только активные модели
    $li = [];
    foreach ($c['models'] as $mcode => $label) {
        $m = $DB->Query("SELECT ID FROM b_iblock_element WHERE IBLOCK_ID=" . IBLOCK_ID . " AND ACTIVE='Y' AND CODE='" . $DB->ForSql($mcode) . "'")->Fetch();
        if (!$m) { echo "  $code: модель $mcode не активна — без ссылки\n"; continue; }
        [$name, $rest] = explode(' — ', $label, 2);
        $li[] = '<li><a href="/product/' . $mcode . '/">' . htmlspecialcharsbx($name) . '</a> — ' . htmlspecialcharsbx($rest) . '</li>';
    }
    if (!$li) { throw new RuntimeException("$code: нет ни одной активной модели"); }
    $block = M_OPEN . "\n<h2>" . htmlspecialcharsbx($c['title']) . "</h2>\n<ul>\n" . implode("\n", $li) . "\n</ul>\n" . M_CLOSE . "\n";
    if (str_contains($html, M_OPEN)) {
        $html = preg_replace('~' . preg_quote(M_OPEN, '~') . '.*?' . preg_quote(M_CLOSE, '~') . '\n?~s', $block, $html, 1);
    } else {
        if (substr_count($html, $c['cta']) !== 1) { throw new RuntimeException("$code: абзац «{$c['cta']}» найден " . substr_count($html, $c['cta']) . ' раз'); }
        $html = str_replace($c['cta'], $block . $c['cta'], $html);
    }
    echo "  $code: ссылок " . count($li) . ($html === $before ? ' (без изменений)' : '') . "\n";
    $plan[$code] = ['id' => (int) $e['ID'], 'before' => $before, 'after' => $html];
}

$enum = $DB->Query("SELECT ID, VALUE FROM b_iblock_property_enum WHERE PROPERTY_ID=$propId AND VALUE IN ('" . $DB->ForSql($enumFix['old']) . "','" . $DB->ForSql($enumFix['new']) . "')")->Fetch();
if (!$enum) { throw new RuntimeException('нет значения списка ' . $enumFix['old']); }
echo $enum['VALUE'] === $enumFix['new'] ? "  список: «{$enumFix['new']}» уже стоит\n" : "  список {$enum['ID']}: «{$enumFix['old']}» → «{$enumFix['new']}»\n";

if ($dry) { echo "[dry] записей нет\n"; exit(0); }

foreach ($plan as $code => $p) {
    if ($p['after'] === $p['before']) { continue; }
    $log['detail'][$code] = ['id' => $p['id'], 'before' => $p['before']];
    file_put_contents($opts['log'], json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    setDetail($p['id'], $p['after']);
    $chk = $DB->Query("SELECT DETAIL_TEXT FROM b_iblock_element WHERE ID={$p['id']}")->Fetch()['DETAIL_TEXT'];
    if ($chk !== $p['after']) { throw new RuntimeException("$code: текст не записался"); }
    echo "записано $code\n";
}
if ($enum['VALUE'] !== $enumFix['new']) {
    $log['enum'] = ['id' => (int) $enum['ID'], 'before' => $enum['VALUE']];
    file_put_contents($opts['log'], json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $DB->Query("UPDATE b_iblock_property_enum SET VALUE='" . $DB->ForSql($enumFix['new']) . "' WHERE ID=" . (int) $enum['ID']);
    echo "записано значение списка {$enum['ID']}\n";
}
file_put_contents($opts['log'], json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
clearCache();
echo "Кэш очищен. Лог отката: {$opts['log']}\n";
