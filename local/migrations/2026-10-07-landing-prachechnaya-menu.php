<?php
/**
 * Лендинг «Прачечная»: пункты якорного меню — одним списком в блоке «Якорное меню» (поле `menu`),
 * чтобы подписи и порядок правились в одном месте. Список собирается из текущих полей
 * «Пункт якорного меню» у блоков (в порядке страницы), плюс новый пункт «Оборудование» (#cats)
 * сразу после «Проектов».
 *
 * Идемпотентна: список заполняется, только если он пуст.
 */

call_user_func(static function () {

CModule::IncludeModule('iblock');

$ibDirection = (int)(\CIBlock::GetList([], ['CODE' => 'prachechnaya', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'] ?? 0);
$dirId = (int)(\CIBlockElement::GetList([], ['IBLOCK_ID' => $ibDirection, 'CODE' => 'prachechnaya'], false, ['nTopCount' => 1], ['ID'])->Fetch()['ID'] ?? 0);
if (!$ibDirection || !$dirId) {
    throw new \RuntimeException('Не найдено направление prachechnaya');
}

$filled = static function (string $code) use ($ibDirection, $dirId): string {
    $r = \CIBlockElement::GetProperty($ibDirection, $dirId, [], ['CODE' => $code])->Fetch();
    return (string)($r['VALUE'] ?? ''); // у GetProperty()->Fetch() ключа ~VALUE нет — только VALUE
};

$blocks = profequip_LandingDecode($filled('LAND_BLOCKS'), 'LAND_BLOCKS');
if (!$blocks || !isset($blocks['cats'])) {
    throw new \RuntimeException('LAND_BLOCKS без блока cats — сначала 2026-10-07-landing-prachechnaya-cats.php');
}

if (!empty($blocks['anchors']['menu'])) {
    echo "Пункты якорного меню уже заданы списком, пропускаю\n";
    return;
}

$blocks['cats']['anchor'] = $blocks['cats']['anchor'] ?: 'Оборудование';
$menu = [];
foreach ($blocks as $key => $row) {   // порядок групп = порядок блоков на странице «Прачечной»
    if ($key === 'anchors' || empty($row['active']) || ($row['anchor'] ?? '') === '') {
        continue;
    }
    $menu[] = ['v' => $row['anchor'], 'd' => '#' . $key];
}
// «Бренды» на странице стоят перед каталогом «В наличии» — так же и в меню
$pos = array_flip(array_column($menu, 'd'));
if (isset($pos['#stock'], $pos['#brands']) && $pos['#stock'] < $pos['#brands']) {
    [$menu[$pos['#stock']], $menu[$pos['#brands']]] = [$menu[$pos['#brands']], $menu[$pos['#stock']]];
}
$blocks['anchors'] = ($blocks['anchors'] ?? []) + ['active' => 1];
$blocks['anchors']['menu'] = $menu;
\CIBlockElement::SetPropertyValuesEx($dirId, $ibDirection, ['LAND_BLOCKS' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);

// проверка запросом к базе
$check = profequip_LandingDecode($filled('LAND_BLOCKS'), 'LAND_BLOCKS');
if (count($check) !== count($blocks) || !in_array('#cats', array_column($check['anchors']['menu'] ?? [], 'd'), true)) {
    throw new \RuntimeException('Пункты якорного меню не записались');
}
echo 'Якорное меню: ' . implode(' · ', array_column($check['anchors']['menu'], 'v')) . "\n";

});
