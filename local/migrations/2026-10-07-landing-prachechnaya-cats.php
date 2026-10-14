<?php
/**
 * Лендинг «Прачечная»: блок «Категории каталога» после формы под проектами — карточки подразделов
 * раздела «Прачечное оборудование» (новое свойство LAND_CATS + группа `cats` в LAND_BLOCKS).
 * Строки ссылаются на разделы по символьному коду: название, ссылка и картинка берутся из раздела,
 * пока в админке не загружена своя картинка карточки.
 *
 * Идемпотентна: LAND_CATS заполняется, только если пустое; группа `cats` — только если её нет.
 */

call_user_func(static function () {

CModule::IncludeModule('iblock');

$ibDirection = (int)(\CIBlock::GetList([], ['CODE' => 'prachechnaya', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'] ?? 0);
$dirId = (int)(\CIBlockElement::GetList([], ['IBLOCK_ID' => $ibDirection, 'CODE' => 'prachechnaya'], false, ['nTopCount' => 1], ['ID'])->Fetch()['ID'] ?? 0);
if (!$ibDirection || !$dirId) {
    throw new \RuntimeException('Не найдено направление prachechnaya');
}

// свойство LAND_CATS + вкладка «Лендинг» (создаёт недостающие свойства схемы)
profequip_LandingEnsureDirectionProps($ibDirection);

$filled = static function (string $code) use ($ibDirection, $dirId): string {
    $r = \CIBlockElement::GetProperty($ibDirection, $dirId, [], ['CODE' => $code])->Fetch();
    return (string)($r['VALUE'] ?? ''); // у GetProperty()->Fetch() ключа ~VALUE нет — только VALUE
};

if ($filled('LAND_CATS') === '') {
    global $DB;
    // порядок — по скриншоту клиента 2026-10-07
    $codes = [
        'prachechnoe-oborudovanie-v-nalichii', 'stiralnye-mashiny', 'sushilnoe-oborudovanie', 'gladilnoe-oborudovanie',
        'finishnoe-oborudovanie', 'upakovochnoe-oborudovanie', 'komplekty-prachechnogo-oborudovaniya', 'vspomogatelnoe-oborudovanie',
    ];
    $catIb = (int)(\CIBlock::GetList([], ['CODE' => 'catalog', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'] ?? 0);
    $rows = [];
    foreach ($codes as $c) {
        $s = \CIBlockSection::GetList([], ['IBLOCK_ID' => $catIb, '=CODE' => $c], false, ['ID', 'IBLOCK_ID', 'CODE', 'NAME', 'PICTURE', 'SECTION_PAGE_URL'])->GetNext();
        if (!$s) {
            echo "Раздел $c не найден — пропускаю\n";
            continue;
        }
        $pic = 0;
        if ($s['PICTURE']) { // своя копия строки b_file: замена картинки в редакторе не тронет картинку раздела
            $DB->Query("INSERT INTO b_file (TIMESTAMP_X, MODULE_ID, HEIGHT, WIDTH, FILE_SIZE, CONTENT_TYPE, SUBDIR, FILE_NAME, ORIGINAL_NAME, DESCRIPTION, HANDLER_ID, EXTERNAL_ID) "
                . "SELECT NOW(), MODULE_ID, HEIGHT, WIDTH, FILE_SIZE, CONTENT_TYPE, SUBDIR, FILE_NAME, ORIGINAL_NAME, '', NULL, '" . md5(uniqid('', true)) . "' FROM b_file WHERE ID = " . (int)$s['PICTURE']);
            $pic = (int)$DB->LastID();
        }
        $rows[] = ['section' => $c, 'title' => (string)$s['~NAME'], 'url' => (string)$s['SECTION_PAGE_URL'], 'pic' => $pic];
    }
    \CIBlockElement::SetPropertyValuesEx($dirId, $ibDirection, ['LAND_CATS' => json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    echo "LAND_CATS: записано\n";
} else {
    echo "LAND_CATS: уже заполнено, пропускаю\n";
}

$blocks = profequip_LandingDecode($filled('LAND_BLOCKS'), 'LAND_BLOCKS');
if (!$blocks) {
    throw new \RuntimeException('LAND_BLOCKS направления prachechnaya пусто — сначала сид лендинга');
}
if (!isset($blocks['cats'])) {
    // заголовок над карточками — по просьбе клиента 2026-10-07
    $blocks['cats'] = ['title' => 'Наше оборудование', 'lead' => '', 'anchor' => 'Оборудование', 'link_text' => '', 'link_url' => '', 'active' => 1];
    \CIBlockElement::SetPropertyValuesEx($dirId, $ibDirection, ['LAND_BLOCKS' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    echo "LAND_BLOCKS: блок cats добавлен\n";
}

// проверка запросом к базе
$check = profequip_LandingDecode($filled('LAND_BLOCKS'), 'LAND_BLOCKS');
if (empty($check['cats']['active']) || count(profequip_LandingDecode($filled('LAND_CATS'), 'LAND_CATS')) < 1) {
    throw new \RuntimeException('Блок категорий «Прачечной» не записался');
}
echo "Категории «Прачечной» готовы.\n";

});
