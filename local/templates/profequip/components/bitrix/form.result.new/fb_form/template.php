<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$response = new \Bitrix\Main\HttpResponse(\Bitrix\Main\Application::getInstance()->getContext());
?>
<?if (empty($request->get("formresult"))){?>    
<?if ($arResult["isFormErrors"] == "Y"):?><?=$arResult["FORM_ERRORS_TEXT"];?><?endif;?>
<?=$arResult["FORM_NOTE"]?>
    <?if ($arResult["isFormNote"] != "Y"){?>



    <?
    } 
} elseif ($request->get("formresult") == "addok"){
    $result['success'] = true;
    $result['message'] = 'Форма успешно отправлена'; // Добавляем сообщение для пользователя
    $result['show_success_modal'] = true;

    header('Content-Type: application/json');
    echo json_encode($result);
    return;
}
?>
