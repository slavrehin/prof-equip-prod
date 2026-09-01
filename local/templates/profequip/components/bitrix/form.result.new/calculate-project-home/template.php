<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$response = new \Bitrix\Main\HttpResponse(\Bitrix\Main\Application::getInstance()->getContext());
?>
<?if (empty($request->get("formresult"))){?>    
<?if ($arResult["isFormErrors"] == "Y"):?><?=$arResult["FORM_ERRORS_TEXT"];?><?endif;?>
<?=$arResult["FORM_NOTE"]?>
    <?if ($arResult["isFormNote"] != "Y"){?>

    <form class="calculate-project-form form fb-form" name="<?=$arResult["WEB_FORM_NAME"];?>" action="/local/ajax/form/" novalidate="novalidate">
        <?foreach ($arResult["QUESTIONS"] as $FIELD_SID => $arQuestion)
            {
                if ($arQuestion['STRUCTURE'][0]['FIELD_TYPE'] == 'hidden')
                {
                    echo $arQuestion["HTML_CODE"];
                }
            }?>
        <?=bitrix_sessid_post();?>
        <input type="hidden" name="WEB_FORM_ID" value="<?=$arParams['WEB_FORM_ID'];?>" />
        <div class="inputs">
            <div class="input__row">
                <div class="input-wrapper"><input class="input input__valid-name" placeholder="Имя" value="" name="<?=$arResult["QUESTION_NAME"]["calculate_name"];?>" required><span class="req-star">*</span></div>
            </div>
            <div class="input__row">
                <div class="input-wrapper"><input class="input input__valid-phone" placeholder="Телефон" value="" name="<?=$arResult["QUESTION_NAME"]["calculate_phone"];?>" requiredid="phone"><span class="req-star">*</span></div>
            </div>
            <div class="input__row">
                <div class="input-wrapper"><input class="input" placeholder="Компания" value="" name="<?=$arResult["QUESTION_NAME"]["calculate_company"];?>"></div>
            </div>
            <div class="input__row">
                <div class="input-wrapper"><input class="input" placeholder="E-mail" value="" name="<?=$arResult["QUESTION_NAME"]["calculate_email"];?>"></div>
            </div>
        </div>
        <input type="hidden" name="web_form_submit" value="<?=$arResult["arForm"]["BUTTON"];?>">
                <input type="hidden" name="<?=$arResult["QUESTION_NAME"]["calculate_url"];?>" class="js-url" value="<?=$APPLICATION->GetCurDir();?>">
        <p class="hint"><span>* </span>- обязательные поля</p><button class="btn gradient" type="submit"><span class="btn__text">Рассчитать</span></button>
        <p class="alert">Нажимая на кнопку, вы соглашаетесь с <a href="/politika-konfidentsialnosti/" target="_blank">политикой обработки персональных данных</a></p>
    </form>

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
