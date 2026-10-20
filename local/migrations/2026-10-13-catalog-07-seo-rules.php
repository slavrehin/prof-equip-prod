<?php
/**
 * SEO-правила фильтра (seofilterrules), которые заменены SEO-тегами, — деактивируются:
 *  - <раздел>__tip_zapchasti: «Наименование детали» (200+ значений, обычно по 1 товару — «тонкие» страницы)
 *    убрано из фильтра; старые /f/tip_zapchasti-is-<деталь>/ → 301 на тег группы (section.php);
 *  - <раздел>__zagruzka_kg: загрузка стала числом, подборки — теги-диапазоны («25 кг» = 23–26,9 кг);
 *    старые /f/zagruzka_kg-is-25/ → 301 на тег (section.php, b_profequip_slug_history).
 * Остальные правила (производитель, страна) работают как раньше для значений без тега. Идемпотентна.
 */

call_user_func(static function () {

\CModule::IncludeModule('iblock');
$iblockId = (int)GetIBlockIDByCode('seofilterrules');
if (!$iblockId) {
    echo "Нет инфоблока seofilterrules\n";
    return;
}
$n = 0;
$rs = \CIBlockElement::GetList([], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y'], false, false, ['ID', 'CODE']);
while ($r = $rs->Fetch()) {
    if (preg_match('/__(tip_zapchasti|zagruzka_kg)$/', (string)$r['CODE'])) {
        (new \CIBlockElement())->Update((int)$r['ID'], ['ACTIVE' => 'N']);
        echo "Правило {$r['CODE']} деактивировано\n";
        $n++;
    }
}
global $DB;
$left = $DB->Query("SELECT COUNT(*) N FROM b_iblock_element WHERE IBLOCK_ID = $iblockId AND ACTIVE = 'Y' AND (CODE LIKE '%\\_\\_tip_zapchasti' OR CODE LIKE '%\\_\\_zagruzka_kg')")->Fetch();
if ((int)$left['N'] > 0) {
    throw new \RuntimeException('Остались активные правила tip_zapchasti/zagruzka_kg: ' . $left['N']);
}
echo "Деактивировано правил: $n\n";

});
return true;
