<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$response = new \Bitrix\Main\HttpResponse(\Bitrix\Main\Application::getInstance()->getContext());
?>
<?if (empty($request->get("formresult"))){?>    
<?if ($arResult["isFormErrors"] == "Y"):?><?=$arResult["FORM_ERRORS_TEXT"];?><?endif;?>
<?=$arResult["FORM_NOTE"]?>
    <?if ($arResult["isFormNote"] != "Y"){?>


    <div class="modal modal--consultation-modal" data-modal="consultation-modal">
        <div class="modal-content"> <button class="btn close-modal">×</button>
            <p class="modal__title">Получить консультацию</p>
            <form class="form consultation__form fb-form" name="<?=$arResult["WEB_FORM_NAME"];?>" action="/local/ajax/form/">
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
                        <div class="input-wrapper"><input class="input input__valid-name" type="text" placeholder="Имя" value="" name="<?=$arResult["QUESTION_NAME"]["consultation_name"];?>"></div>
                    </div>
                    <div class="input__row">
                        <div class="input-wrapper"><input class="input input__valid-phone" type="text" placeholder="Телефон" value="" name="<?=$arResult["QUESTION_NAME"]["consultation_phone"];?>"></div>
                    </div>
                    <div class="input__row">
                        <div class="input-wrapper"><input class="input" type="text" placeholder="E-mail" value="" name="<?=$arResult["QUESTION_NAME"]["consultation_email"];?>"></div>
                    </div><textarea placeholder="Комментарий" name="<?=$arResult["QUESTION_NAME"]["consultation_message"];?>"></textarea>
                </div>
                <input type="hidden" name="web_form_submit" value="<?=$arResult["arForm"]["BUTTON"];?>">
                        <input type="hidden" name="<?=$arResult["QUESTION_NAME"]["consultation_url"];?>" class="js-url" value="<?=$APPLICATION->GetCurDir();?>">
                <button class="btn submit__btn" type="submit">Отправить</button>
            </form>
        </div>
    </div>

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
