<?php
/**
 * Добавляет скрытые вопросы client_id/utm_* в веб-формы SIMPLE_FORM_1/2/3,
 * чтобы их значения (собранные JS в header.php/dev.js) доходили до
 * sendFormToBitrix24() в local/php_interface/init.php и попадали в лид
 * Bitrix24 (поля UF_CRM_YA_CID/UF_CRM_YA_COUNTER_ID/UTM_*).
 *
 * Формат — как у уже существующих скрытых вопросов order_url/calculate_url/
 * consultation_url: CFormField::Set() создаёт запись в b_form_field и
 * связанный b_form_answer (FIELD_TYPE='text').
 */

if (!CModule::IncludeModule('form')) {
    throw new \RuntimeException('Модуль form не подключается');
}

global $DB;

$formsConfig = [
    'SIMPLE_FORM_1' => 'order',
    'SIMPLE_FORM_2' => 'calculate',
    'SIMPLE_FORM_3' => 'consultation',
];

$suffixes = [
    'client_id'    => 'Yandex Client ID',
    'utm_source'   => 'UTM Source',
    'utm_medium'   => 'UTM Medium',
    'utm_campaign' => 'UTM Campaign',
    'utm_content'  => 'UTM Content',
    'utm_term'     => 'UTM Term',
];

foreach ($formsConfig as $formSid => $prefix) {
    $arForm = \CForm::GetBySID($formSid)->Fetch();
    if (!$arForm || empty($arForm['ID'])) {
        throw new \RuntimeException("Форма $formSid не найдена");
    }
    $formId = (int)$arForm['ID'];

    foreach ($suffixes as $suffix => $title) {
        $fieldSid = $prefix . '_' . $suffix;

        $exists = \CFormField::GetBySID($fieldSid, $formId)->Fetch();
        if ($exists) {
            echo "Поле $fieldSid уже существует в форме $formSid, пропускаю\n";
            continue;
        }

        $sort = \CFormField::GetNextSort($formId);

        $fieldId = \CFormField::Set(
            [
                'FORM_ID' => $formId,
                'ACTIVE' => 'Y',
                'TITLE' => $title,
                'TITLE_TYPE' => 'text',
                'SID' => $fieldSid,
                'C_SORT' => $sort,
                'ADDITIONAL' => 'N',
                'REQUIRED' => 'N',
                'IN_RESULTS_TABLE' => 'N',
                'IN_EXCEL_TABLE' => 'Y',
                'arANSWER' => [[
                    'MESSAGE' => ' ',
                    'VALUE' => '',
                    'C_SORT' => 100,
                    'ACTIVE' => 'Y',
                    'FIELD_TYPE' => 'text',
                    'FIELD_WIDTH' => 0,
                    'FIELD_HEIGHT' => 0,
                    'FIELD_PARAM' => '',
                ]],
            ],
            false,
            'N',
            'N'
        );

        if (!$fieldId) {
            throw new \RuntimeException("Не создано поле $fieldSid");
        }

        $check = $DB->Query('SELECT ID FROM b_form_field WHERE ID = ' . (int)$fieldId)->Fetch();
        if (!$check) {
            throw new \RuntimeException("Поле $fieldSid не найдено в базе после Set()");
        }

        echo "Создано поле $fieldSid (ID=$fieldId) в форме $formSid\n";
    }
}
