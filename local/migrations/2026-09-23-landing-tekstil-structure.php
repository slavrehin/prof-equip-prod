<?php
/**
 * Структура лендинга направления «Текстиль» (/tekstil/) — по образцу «Прачечной»
 * (2026-09-18-landing-direction-structure.php). Компонент: local/components/custom/direction.landing.
 *
 * Направление уже существует как отдельный инфоблок (CODE=textile, ID=16, элемент CODE=textile) —
 * раньше на нём держалась старая, дотекстовая вёрстка страницы (свойства MAIN_*, ITEM_*, FAQ_*,
 * LIBRARY_*). Эта миграция ничего из старых свойств не трогает и не удаляет — только добавляет
 * новые свойства лендинга (вкладка «Лендинг», редактор local/php_interface/include/landing_editor.php)
 * на тот же инфоблок. Старые свойства остаются в базе (историческая копия), но /tekstil/index.php
 * после переноса на новую архитектуру их больше не читает.
 *
 * Свойства PROJECTS/BRANDS/CARD_META/CARD_TEXT/CARD_SCOPE у направления и у инфоблока projects уже
 * есть (были нужны старой вёрстке и созданы миграцией лендинга «Прачечная» для projects) — компонент
 * direction.landing использует их напрямую, без изменений здесь.
 *
 * Идемпотентна: profequip_LandingEnsureDirectionProps ищет свойства по CODE.
 */

call_user_func(static function () {

global $DB;

CModule::IncludeModule('iblock');

$ibDirection = (int)(\CIBlock::GetList([], ['CODE' => 'textile', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'] ?? 0);
if (!$ibDirection) {
    throw new \RuntimeException('Не найден инфоблок направления textile');
}

// Доп. поле формы SIMPLE_FORM_4 «Тип объекта» — нужно блоку «С какой задачей вы пришли»
// (тип объекта — вторая группа чипов рядом с задачей). Форма общая для всех лендингов
// направлений (создана 2026-09-18-landing-direction-structure.php) — просто добавляем поле,
// если его ещё нет.
CModule::IncludeModule('form');
$form = \CForm::GetBySID('SIMPLE_FORM_4')->Fetch();
if ($form) {
    $formId = (int)$form['ID'];
    if (\CFormField::GetBySID('land_objtype', $formId)->Fetch()) {
        echo "Поле формы land_objtype уже есть, пропускаю\n";
    } else {
        $fieldId = \CFormField::Set([
            'FORM_ID' => $formId,
            'ACTIVE' => 'Y',
            'TITLE' => 'Тип объекта',
            'TITLE_TYPE' => 'text',
            'SID' => 'land_objtype',
            'C_SORT' => \CFormField::GetNextSort($formId),
            'ADDITIONAL' => 'N',
            'REQUIRED' => 'N',
            'IN_RESULTS_TABLE' => 'Y',
            'IN_EXCEL_TABLE' => 'Y',
            'arANSWER' => [[
                'MESSAGE' => ' ', 'VALUE' => '', 'C_SORT' => 100, 'ACTIVE' => 'Y',
                'FIELD_TYPE' => 'text', 'FIELD_WIDTH' => 0, 'FIELD_HEIGHT' => 0, 'FIELD_PARAM' => '',
            ]],
        ], false, 'N', 'N');
        if (!$fieldId) {
            global $strError;
            throw new \RuntimeException('Не создано поле формы land_objtype: ' . ($strError ?? ''));
        }
        echo "Создано поле формы land_objtype (ID=$fieldId)\n";
    }
} else {
    echo "Форма SIMPLE_FORM_4 не найдена — поле land_objtype не добавлено\n";
}

echo "Свойства лендинга в инфоблоке направления «Текстиль» (ID=$ibDirection):\n";
foreach (profequip_LandingEnsureDirectionProps($ibDirection) as $code => $propId) {
    echo "  $code (ID=$propId)\n";
}

// SEO-шаблоны элемента направления (вкладка «SEO») — не трогаем, если уже что-то задано
// (на элементе уже есть старые title/description из легаси-страницы, заданные напрямую в /tekstil/index.php).
$dirEl = \CIBlockElement::GetList([], ['IBLOCK_ID' => $ibDirection, 'CODE' => 'textile'], false, ['nTopCount' => 1], ['ID'])->Fetch();
if ($dirEl) {
    $tpl = new \Bitrix\Iblock\InheritedProperty\ElementTemplates($ibDirection, (int)$dirEl['ID']);
    $current = $tpl->findTemplates();
    $toSet = [];
    if (empty($current['ELEMENT_META_TITLE']['TEMPLATE'])) {
        $toSet['ELEMENT_META_TITLE'] = 'Профессиональный текстиль для отелей, ресторанов и SPA — ПРОФЭКВИП';
    }
    if (empty($current['ELEMENT_META_DESCRIPTION']['TEMPLATE'])) {
        $toSet['ELEMENT_META_DESCRIPTION'] = 'Постельное бельё, махровые изделия, декоративный и ресторанный текстиль, форма для персонала. Бренд Fresco Maggiore. Склад в Москве, изготовление по бренд-букам сетевых отелей.';
    }
    if ($toSet) {
        $keep = [];
        foreach ($current as $code => $row) {
            if (!empty($row['TEMPLATE'])) {
                $keep[$code] = $row['TEMPLATE'];
            }
        }
        $tpl->set($toSet + $keep);
        echo "  заданы SEO-шаблоны: " . implode(', ', array_keys($toSet)) . "\n";
    } else {
        echo "  SEO-шаблоны уже заданы, пропускаю\n";
    }
} else {
    echo "  элемент направления textile не найден — SEO не задано\n";
}

// Итоговая проверка запросом к базе
$n = (int)$DB->Query("SELECT COUNT(*) C FROM b_iblock_property WHERE IBLOCK_ID = $ibDirection AND CODE LIKE 'LAND\\_%'")->Fetch()['C'];
if ($n < 12) {
    throw new \RuntimeException("В инфоблоке направления «Текстиль» $n свойств лендинга, ожидалось не меньше 12");
}
echo "Структура лендинга «Текстиль» готова.\n";

});
