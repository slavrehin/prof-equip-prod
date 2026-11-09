<?php
/**
 * Локальная пересборка: добирает для лендинга /tekstil/ то, чего нет в git.
 * Источник содержимого — local/deploy/landing_tekstil/data.json (git 65b9fd1, экспорт с test3 16.10.2026,
 * после последних правок по задаче Б24 #9311). Эталон итогового вида — сохранённая страница прода
 * «лендинг текстиль.html» (проекты, бренды, товары «В наличии», названия).
 * Запускать ПОСЛЕ импорта Б24 (content_b24.json) и apply_photos.
 *
 * Что делает (повторный запуск безопасен, картинки ставятся только если их нет):
 *  1. Проект «Конгресс отель», Екатеринбург — создаёт по WP-странице /project/kongress-otel-ekaterinburg-2023/
 *     (в Б24 и Bitrix-снимках его нет).
 *  2. Фото проектов RUMA, Riviera, Vladivostok, «Конгресс» — из веб-архива (wp-content); без фото
 *     компонент лендинга проект не показывает.
 *  3. Логотипы 16 текстильных брендов (WP, 300×100) — без логотипа бренд не выводится.
 *  4. MANTERA: название как на проде («Поставка прачечного оборудования MANTERA Supreme Seaside 5*»).
 *  5. «В наличии»: «Наличие = СКЛАД МОСКВА» у 5 товаров, которые прод показывал в блоке
 *     (простыня 305×310 уже со складом); фото — EUROVAL (WP), подушка 892 — фото дубля 2452 (#9155).
 *  6. Матрасы Veneto — основной раздел «Матрасы» (#8563; на проде подпись карточки «Матрасы»).
 *  7. Повторный импорт лендинга (в replay он идёт раньше, чем появляются проекты) и картинки JSON-блоков,
 *     которые в data.json ссылались на upload/iblock прода: «Зоны → Ванные комнаты», 3 фото «Библиотеки».
 *
 *   php local/bootstrap/landing_tekstil_fix.php
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = 'localhost';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

$dir = __DIR__ . '/data/landing_tekstil_fix';
$ibId = static fn(string $code): int => (int)(\CIBlock::GetList([], ['CODE' => $code, 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'] ?? 0);
$el = static fn(int $ib, string $code) => \CIBlockElement::GetList([], ['IBLOCK_ID' => $ib, 'CODE' => $code], false, false,
    ['ID', 'NAME', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'IBLOCK_SECTION_ID'])->Fetch();
$file = static function (string $prefix) use ($dir): string {
    $f = glob("$dir/$prefix.*");
    return $f ? $f[0] : '';
};
$be = new \CIBlockElement();
$ibProjects = $ibId('projects');
$ibBrands = $ibId('brands');
$ibCatalog = $ibId('catalog');

// 1. «Конгресс отель»
$kCode = 'kongress-otel-ekaterinburg-2023';
if (!$el($ibProjects, $kCode)) {
    $id = $be->Add([
        'IBLOCK_ID' => $ibProjects,
        'NAME' => '«Конгресс отель», Екатеринбург',
        'CODE' => $kCode,
        'ACTIVE' => 'Y',
        'ACTIVE_FROM' => '05.09.2023 12:00:00',
        'DETAIL_TEXT_TYPE' => 'html',
        'DETAIL_TEXT' => '<p><strong>Город:</strong> Екатеринбург<br><strong>Год выполнения проекта:</strong> 2023<br>'
            . '<strong>Услуги:</strong> консалтинг, поставка профессионального текстиля</p>'
            . '<p><strong>Выполнена поставка:</strong></p><ul><li>Прямое бельё: простыни, пододеяльники, наволочки</li>'
            . '<li>Махровые изделия: полотенца, халаты</li></ul>'
            . '<p>Оснащение нового отеля текстилем — одна из финальных стадий перед открытием объекта. И здесь важно выбрать '
            . 'надёжного и компетентного партнёра. Владельцы «Конгресс отель» обратились к нам с запросом на оснащение отеля '
            . 'прямым бельём и махровыми изделиями. Специалисты ПРОФЭКВИП помогли рассчитать оптимальный объём текстиля и '
            . 'осуществили поставку износостойкого текстиля на выгодных условиях. Открытие отеля запланировано на сентябрь '
            . '2023 года, и всех гостей уже ждут оснащённые номера с высококлассным постельным бельём и махровыми изделиями.</p>',
        'IPROPERTY_TEMPLATES' => [
            'ELEMENT_META_TITLE' => 'Поставка текстиля и консалтинг для «Конгресс отель», Екатеринбург, 2023',
            'ELEMENT_META_DESCRIPTION' => 'К нам обратился отель «Конгресс отель», Екатеринбург с запросом на поставку текстиля. '
                . 'Была осуществлена поставка износостойкого текстиля на выгодных условиях.',
        ],
    ]);
    echo "projects/$kCode: " . ($id ? "создан ID=$id" : 'ошибка ' . $be->LAST_ERROR) . "\n";
}

// 2–3. Фото проектов и логотипы брендов (только если пусто)
$pics = [];
foreach (['otel-ruma', 'riviera-wellness-resort', 'vladivostok-grand-hotel-spa', $kCode] as $code) {
    $pics[] = [$ibProjects, $code, $file("project-$code"), true];
}
foreach (glob("$dir/brand-*") as $path) {
    $pics[] = [$ibBrands, substr(pathinfo($path, PATHINFO_FILENAME), 6), $path, false];
}
// 5. фото товаров «В наличии»
$pics[] = [$ibCatalog, 'tkan-euroval-plus-dlya-postelnogo-belya-70-hlopok-120-g-m-kv-ts120-ref-173-standard-textile',
    $file('product-tkan-euroval-plus'), true];
$twin = $el($ibCatalog, 'podushka-pukhovaya-trekhkamernaya-hanse-natural-hybrid-pillow-jumbo-51-71-sm-belaya');
if ($twin && $twin['PREVIEW_PICTURE']) {
    $pics[] = [$ibCatalog, 'podushka-natural-hybrid-pillow-jumbo-51-71-sm',
        $_SERVER['DOCUMENT_ROOT'] . \CFile::GetPath($twin['PREVIEW_PICTURE']), true];
}
foreach ($pics as [$ib, $code, $path, $withDetail]) {
    $e = $el($ib, $code);
    if (!$e || !is_file($path)) {
        echo "$code: " . (!$e ? 'нет элемента' : 'нет файла') . "\n";
        continue;
    }
    $fields = [];
    if (!$e['PREVIEW_PICTURE']) {
        $fields['PREVIEW_PICTURE'] = \CFile::MakeFileArray($path);
    }
    if ($withDetail && !$e['DETAIL_PICTURE']) {
        $fields['DETAIL_PICTURE'] = \CFile::MakeFileArray($path);
    }
    echo "$code: " . (!$fields ? 'уже заполнено' : ($be->Update($e['ID'], $fields) ? 'фото OK' : 'ошибка ' . $be->LAST_ERROR)) . "\n";
}

// 4. MANTERA — название как на проде
$m = $el($ibProjects, 'mantera-supreme-seaside-5-2');
$mName = 'Поставка прачечного оборудования MANTERA Supreme Seaside 5*';
if ($m && $m['NAME'] !== $mName) {
    echo 'MANTERA NAME: ' . ($be->Update($m['ID'], ['NAME' => $mName]) ? 'OK' : $be->LAST_ERROR) . "\n";
}

// 4а. Теги карточек проектов — бренды проекта (свойство BRANDS), как на проде; бренд Sovrana — имя как на проде
$brandIds = static function (array $codes) use ($el, $ibBrands): array {
    return array_values(array_filter(array_map(static fn($c) => (int)($el($ibBrands, $c)['ID'] ?? 0), $codes)));
};
foreach ([
    'otel-ruma' => ['profekvip'],
    'riviera-wellness-resort' => ['rivolta-carmignani'],
    'vladivostok-grand-hotel-spa' => ['profekvip'],
    'mantera-supreme-seaside-5-2' => ['tolon', 'imesa', 'sovrana', 'pony'],
    'marriott-imperial-plaza-hotel-moskva-2022' => ['profekvip'],
] as $code => $codes) {
    $e = $el($ibProjects, $code);
    $cur = $e ? array_map('intval', array_column(iterator_to_array((static function () use ($ibProjects, $e) {
        $rs = \CIBlockElement::GetProperty($ibProjects, $e['ID'], [], ['CODE' => 'BRANDS']);
        while ($r = $rs->Fetch()) {
            if ($r['VALUE']) {
                yield $r;
            }
        }
    })(), false), 'VALUE')) : [];
    if ($e && !$cur) {
        \CIBlockElement::SetPropertyValuesEx($e['ID'], $ibProjects, ['BRANDS' => $brandIds($codes)]);
        echo "BRANDS $code: OK\n";
    }
}
$sov = $el($ibBrands, 'sovrana');
if ($sov && $sov['NAME'] !== 'Sovrana') {
    $be->Update($sov['ID'], ['NAME' => 'Sovrana']);
    echo "brands/sovrana NAME: OK\n";
}
// Название направления (хлебные крошки, микроразметка) — как на проде
$dirE = $el($ibId('textile'), 'textile');
if ($dirE && $dirE['NAME'] !== 'Текстиль для отелей') {
    $be->Update($dirE['ID'], ['NAME' => 'Текстиль для отелей']);
    echo "textile NAME: OK\n";
}

// 5. «Наличие = СКЛАД МОСКВА»
$moskva = (int)(\CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $ibCatalog, 'CODE' => 'NALICHIE', 'XML_ID' => 'sklad-moskva'])->Fetch()['ID'] ?? 0);
// свойство скрыто миграцией 2026-10-13-catalog-02 («заменён STOCK_BADGE»), значения при этом остаются и лендинг
// фильтрует по ним; SetPropertyValuesEx неактивные свойства пропускает — включаем на время записи
$nalProp = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $ibCatalog, 'CODE' => 'NALICHIE'])->Fetch();
if ($nalProp && $nalProp['ACTIVE'] === 'N') {
    (new \CIBlockProperty())->Update((int)$nalProp['ID'], ['IBLOCK_ID' => $ibCatalog, 'ACTIVE' => 'Y']);
}
foreach ([
    'navolochka-na-podushku-45-70-sm-satin-tc600-100-dlinnovoloknistyy-khlopok-piano-finish-fresco-maggio',
    'pododeyalnik-na-odeyalo-160-240-sm-satin-tc600-100-dlinnovoloknistyy-khlopok-piano-finish-fresco-mag',
    'podushka-natural-hybrid-pillow-jumbo-51-71-sm',
    'tkan-euroval-plus-dlya-postelnogo-belya-70-hlopok-120-g-m-kv-ts120-ref-173-standard-textile',
    'odeyalo-stegannoe-vsesezonnoe-plotnost-napolnitelya-300-gr-m-kv-220-240-sm',
] as $code) {
    $e = $el($ibCatalog, $code);
    if ($e && $moskva) {
        \CIBlockElement::SetPropertyValuesEx($e['ID'], $ibCatalog, ['NALICHIE' => $moskva]);
        echo "NALICHIE $code: OK\n";
    } else {
        echo "NALICHIE $code: нет " . ($e ? 'значения списка' : 'элемента') . "\n";
    }
}
if ($nalProp && $nalProp['ACTIVE'] === 'N') {
    (new \CIBlockProperty())->Update((int)$nalProp['ID'], ['IBLOCK_ID' => $ibCatalog, 'ACTIVE' => 'N']);
}

// 6. Матрасы Veneto → раздел «Матрасы» (основной), прежний раздел остаётся дополнительным
$secM = \CIBlockSection::GetList([], ['IBLOCK_ID' => $ibCatalog, 'CODE' => 'matrasy'], false, ['ID'])->Fetch();
foreach (['matras-fresco-maggiore-veneto-aurora', 'matras-fresco-maggiore-veneto-celeste'] as $code) {
    $e = $el($ibCatalog, $code);
    if ($e && $secM && (int)$e['IBLOCK_SECTION_ID'] !== (int)$secM['ID']) {
        $secs = [(int)$secM['ID']];
        $rs = \CIBlockElement::GetElementGroups($e['ID'], true, ['ID']);
        while ($s = $rs->Fetch()) {
            $secs[] = (int)$s['ID'];
        }
        $ok = $be->Update($e['ID'], ['IBLOCK_SECTION' => array_values(array_unique($secs)), 'IBLOCK_SECTION_ID' => (int)$secM['ID']]);
        echo "раздел $code: " . ($ok ? 'OK' : $be->LAST_ERROR) . "\n";
    }
}

// 7. Повторный импорт лендинга + картинки JSON-блоков
passthru('php ' . escapeshellarg($_SERVER['DOCUMENT_ROOT'] . '/local/deploy/landing_tekstil/import.php'));

$saveFile = static function (string $path): int {
    global $DB;
    $ext = 'tekstil-fix-' . basename($path);
    $row = $DB->Query("SELECT ID FROM b_file WHERE EXTERNAL_ID = '" . $DB->ForSql($ext) . "' ORDER BY ID LIMIT 1")->Fetch();
    if ($row) {
        return (int)$row['ID'];
    }
    $arr = \CFile::MakeFileArray($path);
    $arr['MODULE_ID'] = 'iblock';
    $arr['external_id'] = $ext;
    return (int)\CFile::SaveFile($arr, 'landing');
};
$ibDir = $ibId('textile');
$dirEl = $el($ibDir, 'textile');
$patch = [
    'LAND_SOLUTIONS' => [1 => 'zone-vannye-komnaty'],
    'LAND_LIBRARY' => [0 => 'library-1', 1 => 'library-2', 2 => 'library-3'],
];
foreach ($patch as $code => $map) {
    $prop = \CIBlockElement::GetProperty($ibDir, $dirEl['ID'], [], ['CODE' => $code])->Fetch();
    $rows = json_decode((string)$prop['VALUE'], true);
    $changed = false;
    foreach ($map as $i => $prefix) {
        if (isset($rows[$i]) && empty($rows[$i]['pic']) && ($path = $file($prefix))) {
            $rows[$i]['pic'] = $saveFile($path);
            $changed = true;
        }
    }
    if ($changed) {
        \CIBlockElement::SetPropertyValuesEx($dirEl['ID'], $ibDir,
            [$code => json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        echo "$code: картинки OK\n";
    }
}
BXClearCache(true, '/profequip/direction_landing/');
foreach ([$ibDir, $ibProjects, $ibBrands, $ibCatalog] as $ib) {
    \CIBlock::clearIblockTagCache($ib);
}
