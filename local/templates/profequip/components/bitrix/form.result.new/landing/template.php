<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Обработчик отправки форм лендингов направлений (SIMPLE_FORM_4).
 * Сами формы рисует компонент custom:direction.landing, они шлют POST на
 * /local/ajax/form/?template_form=landing (см. local/ajax/form/index.php) и ждут JSON.
 * Успех — редирект компонента на ?formresult=addok; инлайн-сообщение об успехе показывает
 * сама страница (общее модальное окно «Спасибо» не нужно — show_success_modal=false).
 *
 * @var array $arResult
 */

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();

header('Content-Type: application/json; charset=utf-8');

if ($request->get('formresult') === 'addok') {
    echo json_encode(['success' => true, 'message' => 'Форма успешно отправлена', 'show_success_modal' => false], JSON_UNESCAPED_UNICODE);
    return;
}

if (($arResult['isFormErrors'] ?? '') === 'Y') {
    $msg = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br />', '</li>'], "\n", (string)$arResult['FORM_ERRORS_TEXT']))));
    echo json_encode(['success' => false, 'message' => $msg !== '' ? $msg : 'Не удалось отправить форму. Проверьте поля и попробуйте ещё раз.'], JSON_UNESCAPED_UNICODE);
    return;
}

// GET без результата — ничего не рисуем
echo json_encode(['success' => false, 'message' => 'Форма не отправлена'], JSON_UNESCAPED_UNICODE);
