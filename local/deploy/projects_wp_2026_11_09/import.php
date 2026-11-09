<?php
/**
 * Проекты «Гостиница «Империал», Осетия» и «Гостевой дом, Великий Устюг», 2026-11-09.
 *
 * На проде (Bitrix) были в портфолио и на странице «Комплексное оснащение отелей»
 * (/portfolio/gostinitsa-imperial-osetiya-2023-god/, /portfolio/gostevoy-dom-velikiy-ustyug/),
 * но в восстановленную базу не попали. Текст и фото — с WP-страниц из веб-архива:
 * /project/gostinitsa-imperial-osetiya-2023-god/ (06.09.2023), /project/gostevoj-dom-velikij-ustyug-2021/ (23.09.2021).
 * Теги TYPE — «Гостиница», «Текстиль» (по wayback-снимку /portfolio/).
 *
 * Попутно: фото проекту «MANTERA Supreme Seaside 5*» (mantera-supreme-seaside-5, было пусто) — WP-обложка из веб-архива.
 *
 * После него перезапустить local/deploy/directions_2026_11_09/import.php — он привяжет проекты к «Оснащению».
 *
 * Запуск (локально, на dev и на проде, из корня сайта, от www-data):
 *   php local/deploy/projects_wp_2026_11_09/import.php [--dry]
 * Идемпотентен: существующий проект (по CODE) не пересоздаётся, фото ставится только если пусто.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
\CModule::IncludeModule('iblock');

$dry = in_array('--dry', $argv, true);
$ib = (int) \CIBlock::GetList([], ['CODE' => 'projects', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
$ibBrands = (int) \CIBlock::GetList([], ['CODE' => 'brands', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];

$find = static function (int $iblock, string $code): ?array {
    $r = \CIBlockElement::GetList([], ['IBLOCK_ID' => $iblock, '=CODE' => $code, 'CHECK_PERMISSIONS' => 'N'], false, ['nTopCount' => 1],
        ['ID', 'PREVIEW_PICTURE', 'DETAIL_PICTURE'])->Fetch();
    return $r ?: null;
};
$enum = [];
$r = \CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => $ib, 'CODE' => 'TYPE']);
while ($e = $r->Fetch()) { $enum[$e['XML_ID']] = (int) $e['ID']; }

$projects = [
    'gostinitsa-imperial-osetiya-2023-god' => [
        'NAME' => 'Гостиница «Империал», Осетия',
        'ACTIVE_FROM' => '06.09.2023 12:00:00',
        'CITY' => 'Владикавказ',
        'LIST' => [['Владикавказ', 'Город'], ['2023', 'Год выполнения проекта'], ['консалтинг, поставка профессионального текстиля', 'Услуги']],
        'DETAIL_TEXT' => '<p><strong>Выполнена поставка:</strong></p><ul><li>Гостиничный текстиль</li></ul>'
            . '<p>В 2023 году команда ПРОФЭКВИП выполнила запрос от гостиницы «Империал» – осуществила поставку гостиничного '
            . 'текстиля в Осетию. Поставляемые нами текстильные изделия отличаются высоким качеством пошива, '
            . 'износостойкостью и разумной стоимостью.</p>',
        'META_TITLE' => 'Поставка текстиля и консалтинг для гостиницы «Империал» Осетия 2023',
        'META_DESCRIPTION' => 'В 2023 году команда ПРОФЭКВИП выполнила запрос гостиницы Империал Владикавказ. Мы осуществили '
            . 'поставку профессионального текстиля. Консультация по телефону +7 (499) 302-37-62.',
        'BRANDS' => ['profekvip'],
        'IMG' => 'imperial-1.jpg',
    ],
    'gostevoy-dom-velikiy-ustyug' => [
        'NAME' => 'Гостевой дом, Великий Устюг',
        'ACTIVE_FROM' => '23.09.2021 12:00:00',
        'CITY' => 'Великий Устюг',
        'LIST' => [['Великий Устюг', 'Город'], ['2021', 'Год выполнения проекта'],
            ['халаты, полотенца, постельное белье и постельные принадлежности', 'Текстильные изделия']],
        'DETAIL_TEXT' => '<p>Великий Устюг – популярное место отдыха для тысяч россиян в зимний период. Именно здесь находится '
            . 'резиденция Деда Мороза. Гостиничный сектор здесь развивается, и растет число различных вариантов размещения гостей.</p>'
            . '<p>В 2021 году специалисты ПРОФЭКВИП выполнили заказ владельцев гостевого дома в Великом Устюге – уютного места '
            . 'размещения с номерами различных категорий. Для номерного фонда был изготовлен профессиональный гостиничный текстиль, '
            . 'выполнена поставка готовых изделий.</p>'
            . '<p>Собственное производство ПРОФЭКВИП изготовило удобные халаты из вафельного полотна, постельное белье, подушки и '
            . 'одеяла. Для номеров повышенной категории комфорта клиент выбрал готовые изделия французского бренда Dumas Paris. '
            . 'Также все номера укомплектовали махровыми полотенцами PARIS бренда Standard Textile.</p>'
            . '<p><strong>Выполнена поставка профессионального текстиля для базы:</strong></p><ul>'
            . '<li>Халаты из вафельного полотна</li><li>Махровые полотенца PARIS Standard Textile</li>'
            . '<li>Постельное белье гладь сатин</li><li>Подушки производства ПРОФЭКВИП</li><li>Одеяла производства ПРОФЭКВИП</li>'
            . '<li>Подушки DUMAS</li><li>Одеяла DUMAS</li></ul>'
            . '<p><strong>Виды выполненных работ:</strong></p><ul><li>Консалтинг</li>'
            . '<li>Поставка готового профессионального текстиля</li><li>Пошив и поставка текстильных изделий</li></ul>',
        'META_TITLE' => 'Поставка текстильных изделий для Гостевого дома, Великий Устюг',
        'META_DESCRIPTION' => 'В 2021 году ПРОФЭКВИП изготовил и поставил профессиональный гостиничный текстиль для гостевого '
            . 'дома в Великом Устюге: халаты, постельное белье, подушки и одеяла, полотенца Standard Textile, изделия Dumas Paris.',
        'BRANDS' => ['profekvip', 'dumas', 'standard-textile'],
        'IMG' => 'ustyug-1.jpg',
    ],
];

$be = new \CIBlockElement();
foreach ($projects as $code => $p) {
    $img = __DIR__ . '/img/' . $p['IMG'];
    $e = $find($ib, $code);
    if ($dry) { echo "$code: " . ($e ? "есть #{$e['ID']}" : 'будет создан') . "\n"; continue; }
    if (!$e) {
        $id = $be->Add([
            'IBLOCK_ID' => $ib, 'NAME' => $p['NAME'], 'CODE' => $code, 'ACTIVE' => 'Y', 'SORT' => 500,
            'ACTIVE_FROM' => $p['ACTIVE_FROM'], 'DETAIL_TEXT_TYPE' => 'html', 'DETAIL_TEXT' => $p['DETAIL_TEXT'],
            'PREVIEW_PICTURE' => \CFile::MakeFileArray($img), 'DETAIL_PICTURE' => \CFile::MakeFileArray($img),
            'IPROPERTY_TEMPLATES' => ['ELEMENT_META_TITLE' => $p['META_TITLE'], 'ELEMENT_META_DESCRIPTION' => $p['META_DESCRIPTION']],
        ]);
        if (!$id) { echo "$code: ошибка {$be->LAST_ERROR}\n"; continue; }
        echo "$code: создан #$id\n";
        $brands = [];
        foreach ($p['BRANDS'] as $b) { if ($x = $find($ibBrands, $b)) { $brands[] = (int) $x['ID']; } }
        \CIBlockElement::SetPropertyValuesEx($id, $ib, [
            'TYPE'   => [$enum['gostinitsa'], $enum['tekstil']],
            'CITY'   => $p['CITY'],
            'LIST'   => array_map(fn($l) => ['VALUE' => $l[0], 'DESCRIPTION' => $l[1]], $p['LIST']),
            'BRANDS' => $brands,
        ]);
    } else {
        $f = [];
        if (!$e['PREVIEW_PICTURE']) { $f['PREVIEW_PICTURE'] = \CFile::MakeFileArray($img); }
        if (!$e['DETAIL_PICTURE']) { $f['DETAIL_PICTURE'] = \CFile::MakeFileArray($img); }
        echo "$code: есть #{$e['ID']}" . ($f ? ($be->Update($e['ID'], $f) ? ', фото OK' : ", ошибка {$be->LAST_ERROR}") : '') . "\n";
    }
}
// MANTERA Supreme Seaside 5* — фото только если пусто
$m = $find($ib, 'mantera-supreme-seaside-5');
$mImg = __DIR__ . '/img/mantera-1.webp';
if ($m && !$m['PREVIEW_PICTURE'] && !$dry) {
    echo 'mantera-supreme-seaside-5: ' . ($be->Update($m['ID'], ['PREVIEW_PICTURE' => \CFile::MakeFileArray($mImg),
        'DETAIL_PICTURE' => \CFile::MakeFileArray($mImg)]) ? 'фото OK' : $be->LAST_ERROR) . "\n";
}
if (!$dry) { \CIBlock::clearIblockTagCache($ib); BXClearCache(true); }
