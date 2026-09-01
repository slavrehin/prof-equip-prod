<?php

use Bitrix\Main\EventManager;

$eventManager = EventManager::getInstance();
ini_set('error_log', $_SERVER['DOCUMENT_ROOT'] . '/error.log');
/*
Project includes
*/
require_once(__DIR__ . '/include/constants.php');
require_once(__DIR__ . '/include/function.php');
// require_once(__DIR__ . '/events/events.php');
//require_once __DIR__ . '/include/custom_fields.php';
require_once(__DIR__ . '/include/smartcaptcha.php');

/**** autoload ****/
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php');

function sendFormToBitrix24($RESULT_ID, $arFields) {
    // Определяем ID результата (он может быть в arFields)
    $realResultId = 0;
    
    if (is_numeric($arFields) && $arFields > 0) {
        $realResultId = $arFields;
    } elseif (is_numeric($RESULT_ID) && $RESULT_ID > 0) {
        $realResultId = $RESULT_ID;
    } elseif (is_array($arFields) && isset($arFields['RESULT_ID'])) {
        $realResultId = $arFields['RESULT_ID'];
    }
    
    if ($realResultId <= 0) {
        return;
    }
    
    // Маппинг форм и их полей
    $formMapping = [
        'SIMPLE_FORM_1' => [
            'name_field' => 'order_name',
            'phone_field' => 'order_phone',
            'email_field' => 'order_email',
            'message_field' => 'order_message',
            'product_field' => 'order_product',
            'url_field' => 'order_url',
            'title' => 'Заявка с формы Запросить стоимость'
        ],
        'SIMPLE_FORM_2' => [
            'name_field' => 'calculate_name',
            'phone_field' => 'calculate_phone',
            'email_field' => 'calculate_email',
            'message_field' => 'calculate_company',
            'product_field' => 'calculate_comment',
            'url_field' => 'calculate_url',
            'title' => 'Заявка с формы Рассчитать проект'
        ],
        'SIMPLE_FORM_3' => [
            'name_field' => 'consultation_name',
            'phone_field' => 'consultation_phone',
            'email_field' => 'consultation_email',
            'message_field' => 'consultation_message',
            'product_field' => '',
            'url_field' => 'consultation_url',
            'title' => 'Заявка с формы Консультация'
        ]
    ];
    
    // Подключаем модуль веб-форм
    if (!CModule::IncludeModule('form')) {
        return;
    }
    
    // Получаем информацию о результате формы
    $rsResult = CFormResult::GetByID($realResultId);
    if (!$rsResult) {
        return;
    }
    
    $arResult = $rsResult->Fetch();
    if (!$arResult) {
        return;
    }
    
    $formId = $arResult['FORM_ID'];
    
    // Получаем SID формы
    $rsForm = CForm::GetByID($formId);
    if (!$rsForm) {
        return;
    }
    
    $arForm = $rsForm->Fetch();
    if (!$arForm) {
        return;
    }
    
    $formSid = $arForm['SID'];
    
    // Проверяем, что форма из нашего списка
    if (!isset($formMapping[$formSid])) {
        return;
    }
    
    $mapping = $formMapping[$formSid];
    
    // Получаем данные формы
    $arResultRaw = [];
    $arResultAnswers = [];
    $arResultFilter = [];
    CFormResult::GetDataByID($realResultId, $arResultRaw, $arResultAnswers, $arResultFilter);
    
    // Собираем данные из полей формы
    $formData = [];
    foreach ($arResultFilter as $fieldSid => $fieldData) {
        if (is_array($fieldData) && !empty($fieldData)) {
            $firstField = reset($fieldData);
            if (isset($firstField['USER_TEXT']) && !empty($firstField['USER_TEXT'])) {
                $formData[$fieldSid] = $firstField['USER_TEXT'];
            }
        }
    }
    
    // Извлекаем значения полей
    $name = !empty($formData[$mapping['name_field']]) ? $formData[$mapping['name_field']] : 'Клиент';
    $phone = $formData[$mapping['phone_field']] ?? '';
    $email = $formData[$mapping['email_field']] ?? '';
    $message = $formData[$mapping['message_field']] ?? '';
    $product = isset($mapping['product_field']) && $mapping['product_field'] ? ($formData[$mapping['product_field']] ?? '') : '';
    $url = $formData[$mapping['url_field']] ?? '';
    
    // Если телефон не указан, не отправляем лид
    if (empty($phone)) {
        return;
    }
    
    // Формируем комментарий
    $comments = "Форма: $formSid\n";
    $comments .= "Дата: " . date("d.m.Y H:i:s") . "\n";
    
    if (!empty($message)) {
        $comments .= "Сообщение: $message\n";
    }
    
    if (!empty($product)) {
        $comments .= "Продукт/Комментарий: $product\n";
    }
    
    if (!empty($url)) {
        $comments .= "URL: $url\n";
    }
    
    $webhookUrl = BITRIX24_WEBHOOK_URL;
    
    // Данные для лида
    $leadData = [
        'fields' => [
            'TITLE' => $mapping['title'],
            'NAME' => $name,
            'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']],
            'COMMENTS' => $comments,
        ]
    ];
    
    // Добавляем email, если указан
    if (!empty($email)) {
        $leadData['fields']['EMAIL'] = [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']];
    }
    
    // Отправляем запрос в Битрикс24
    $queryUrl = $webhookUrl . 'crm.lead.add.json';
    $queryData = http_build_query($leadData);
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $queryUrl,
        CURLOPT_POSTFIELDS => $queryData,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $result = curl_exec($curl);
    curl_close($curl);
    
    return json_decode($result, true);
}

$eventManager->addEventHandler('form', 'onAfterResultAdd', 'sendFormToBitrix24');



/**
 * Редирект на основе соответствия текущего URL свойству FROM
 */
function redirect_by_infoblock_properties() {

    if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
        return;
    }
    if (defined('BX_AJAX') && BX_AJAX === true) {
        return;
    }
    
    global $APPLICATION;
    
    $currentUrl = $APPLICATION->GetCurDir();
    
    if (empty($currentUrl) || $currentUrl == '/') {
        return;
    }
    
    if (!\Bitrix\Main\Loader::includeModule('iblock')) {
        return;
    }
    
    $arSelect = [
        'ID',
        'IBLOCK_ID',
        'PROPERTY_FROM',
        'PROPERTY_WHERE'
    ];
    
    $arFilter = [
        'IBLOCK_ID' => GetIBlockIDByCode("redirect"),
        'ACTIVE' => 'Y',
        '!PROPERTY_FROM' => false, // Исключаем пустые значения FROM
        '!PROPERTY_WHERE' => false  // Исключаем пустые значения WHERE
    ];
    
    $res = CIBlockElement::GetList(
        [], // Сортировка
        $arFilter,
        false,
        false,
        $arSelect
    );
    
    $redirectUrl = null;
    
    while ($ob = $res->GetNextElement()) {
        $arFields = $ob->GetFields();
        $arProps = $ob->GetProperties();
        
        $fromValue = $arProps['FROM']['VALUE'] ?? '';
        $whereValue = $arProps['WHERE']['VALUE'] ?? '';
        
        // Пропускаем, если значения пустые
        if (empty($fromValue) || empty($whereValue)) {
            continue;
        }
        
        // Нормализуем URL для сравнения
        $normalizedFrom = normalizeUrlForRedirect($fromValue);
        $normalizedCurrent = normalizeUrlForRedirect($currentUrl);
        
        // Проверяем точное соответствие
        if ($normalizedFrom === $normalizedCurrent) {
            $redirectUrl = $whereValue;
            break;
        }
        
        // Дополнительно проверяем, содержится ли FROM в текущем URL (для вложенных страниц)
        // Раскомментируйте, если нужно
        /*
        if (strpos($normalizedCurrent, $normalizedFrom) !== false) {
            $redirectUrl = $whereValue;
            break;
        }
        */
    }
    
    // Выполняем редирект, если найдено соответствие
    if ($redirectUrl) {
        // Проверяем, не пытаемся ли мы редиректнуть на тот же URL
        $normalizedRedirect = normalizeUrlForRedirect($redirectUrl);
        $normalizedCurrent = normalizeUrlForRedirect($currentUrl);
        
        if ($normalizedRedirect !== $normalizedCurrent) {
            // Используем 301 редирект для постоянного перенаправления
            LocalRedirect($redirectUrl, true, '301 Moved Permanently');
        }
    }
}

/**
 * Вспомогательная функция для нормализации URL
 */
function normalizeUrlForRedirect($url) {
    // Удаляем GET-параметры, если они есть
    if (strpos($url, '?') !== false) {
        $url = substr($url, 0, strpos($url, '?'));
    }
    
    // Удаляем слеш в конце, если он есть и это не просто слеш
    if ($url !== '/') {
        $url = rtrim($url, '/');
    }
    
    return $url;
}

redirect_by_infoblock_properties();