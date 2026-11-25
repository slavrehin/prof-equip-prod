<?php
/**
 * Структура лендинга направления «Кухня» (/kuhnya/) на компоненте custom:direction.landing —
 * по образцу «Текстиля» (2026-09-23-landing-tekstil-structure.php).
 *
 * Инфоблок направления уже есть (CODE=kuhnya, элемент CODE=kuhnya). Его старые свойства
 * (BANNERS, STEPS, SOLUTION, REVIEWS, CATEGORIES_CATALOG …) не трогаем — после перевода
 * /kuhnya/index.php на новый компонент они больше не читаются, но остаются в базе.
 * Добавляются только свойства лендинга LAND_* и вкладка «Лендинг» в форме элемента.
 * PROJECTS и BRANDS у направления уже есть — компонент использует их напрямую.
 *
 * Содержимое вкладки «Лендинг» заливается отдельно: local/deploy/landing_kuhnya/import.php.
 * Идемпотентна: profequip_LandingEnsureDirectionProps ищет свойства по CODE.
 */

call_user_func(static function () {

global $DB;

CModule::IncludeModule('iblock');

$ibDirection = (int)(\CIBlock::GetList([], ['CODE' => 'kuhnya', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'] ?? 0);
if (!$ibDirection) {
    throw new \RuntimeException('Не найден инфоблок направления kuhnya');
}

echo "Свойства лендинга в инфоблоке направления «Кухня» (ID=$ibDirection):\n";
foreach (profequip_LandingEnsureDirectionProps($ibDirection) as $code => $propId) {
    echo "  $code (ID=$propId)\n";
}

$n = (int)$DB->Query("SELECT COUNT(*) C FROM b_iblock_property WHERE IBLOCK_ID = $ibDirection AND CODE LIKE 'LAND\\_%'")->Fetch()['C'];
if ($n < 12) {
    throw new \RuntimeException("В инфоблоке направления «Кухня» $n свойств лендинга, ожидалось не меньше 12");
}
echo "Структура лендинга «Кухня» готова ($n свойств LAND_*).\n";

});
