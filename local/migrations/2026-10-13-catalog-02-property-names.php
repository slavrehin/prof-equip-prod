<?php
/**
 * Стандарт характеристик, шаг 2: единые названия свойств (CatalogStandard::PROPERTY_NAMES —
 * «Тип нагрева», «Мощность, кВт» вместо «Тип Нагрева», «Мощность, Квт») и скрытие дублей/служебных
 * свойств (CatalogStandard::DEACTIVATE — ACTIVE=N, значения в базе сохраняются).
 * Идемпотентна.
 */

call_user_func(static function () {

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/deploy/catalog_restructure/lib.php';
\CModule::IncludeModule('iblock');

$iblockId = CatalogMigrate::iblockId();
$renamed = 0;
foreach (CatalogStandard::PROPERTY_NAMES as $code => $name) {
    $p = CatalogMigrate::property($code);
    if (!$p || $p['NAME'] === $name) {
        continue;
    }
    // IBLOCK_ID обязателен в Update, иначе Bitrix пересоздаёт привязку к фильтру некорректно
    if (!(new \CIBlockProperty())->Update((int)$p['ID'], ['IBLOCK_ID' => $iblockId, 'NAME' => $name])) {
        throw new \RuntimeException("Не переименовано свойство $code");
    }
    $renamed++;
}
echo "Переименовано свойств: $renamed\n";

$hidden = 0;
foreach (CatalogStandard::DEACTIVATE as $code) {
    $p = CatalogMigrate::property($code);
    if ($p && $p['ACTIVE'] !== 'N') {
        (new \CIBlockProperty())->Update((int)$p['ID'], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'N']);
        $hidden++;
    }
}
echo "Скрыто свойств: $hidden\n";

// проверка запросом к базе
$DB = CatalogMigrate::db();
foreach (CatalogStandard::PROPERTY_NAMES as $code => $name) {
    $r = $DB->Query("SELECT NAME FROM b_iblock_property WHERE IBLOCK_ID = $iblockId AND CODE = '" . $DB->ForSql($code) . "'")->Fetch();
    if ($r && $r['NAME'] !== $name) {
        throw new \RuntimeException("$code: в базе «{$r['NAME']}», ожидалось «{$name}»");
    }
}

CatalogMigrate::clearCache();

});
return true;
