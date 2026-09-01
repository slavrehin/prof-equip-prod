<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$response = new \Bitrix\Main\HttpResponse(\Bitrix\Main\Application::getInstance()->getContext());
?>
<?if (empty($request->get("formresult"))){?>    
<?if ($arResult["isFormErrors"] == "Y"):?><?=$arResult["FORM_ERRORS_TEXT"];?><?endif;?>
<?=$arResult["FORM_NOTE"]?>
    <?if ($arResult["isFormNote"] != "Y"){?>


            <form class="form feedback-form fb-form" name="<?=$arResult["WEB_FORM_NAME"];?>" action="/local/ajax/form/">
                <?foreach ($arResult["QUESTIONS"] as $FIELD_SID => $arQuestion)
                    {
                        if ($arQuestion['STRUCTURE'][0]['FIELD_TYPE'] == 'hidden')
                        {
                            echo $arQuestion["HTML_CODE"];
                        }
                    }?>
                <?=bitrix_sessid_post();?>
                <input type="hidden" name="WEB_FORM_ID" value="<?=$arParams['WEB_FORM_ID'];?>" />
                <? $phoneMain = getMainPhoneFromIblock();?>
                <div class="form__title-wrapper">
                    <h2 class="title">Обратная связь</h2><a class="phone" href="tel:<?=$phoneMain;?>"><img src="<?=LAYOUT_DIR?>assets/img/icons/phone.svg" alt="phone"><?=$phoneMain;?></a>
                    <p class="alert">Или позвоните нам по телефону, мы всегда на связи!</p>
                </div>
                <div class="inputs">
                    <div class="input__row outline">
                        <div class="input-wrapper"><input class="input input__valid-name" type="text" placeholder="Имя" value="" name="<?=$arResult["QUESTION_NAME"]["calculate_name"];?>"></div>
                    </div>
                    <div class="input__row outline">
                        <div class="input-wrapper"><input class="input input__valid-phone" type="text" placeholder="Телефон" value="" name="<?=$arResult["QUESTION_NAME"]["calculate_phone"];?>"></div>
                    </div>
                    <div class="input__row outline">
                        <div class="input-wrapper"><input class="input" type="text" placeholder="E-mail" value="" name="<?=$arResult["QUESTION_NAME"]["calculate_email"];?>"></div>
                    </div>
                    <div class="input__row outline">
                        <div class="input-wrapper"><input class="input" type="text" placeholder="Комментарий" value="" name="<?=$arResult["QUESTION_NAME"]["calculate_comment"];?>"></div>
                    </div>
                </div>
                <input type="hidden" name="web_form_submit" value="<?=$arResult["arForm"]["BUTTON"];?>">
                        <input type="hidden" name="<?=$arResult["QUESTION_NAME"]["calculate_url"];?>" value="<?=$APPLICATION->GetCurDir();?>">
                <div class="hint-wrapper">
                            <p class="hint"><span>* </span>- обязательные поля</p>
                            <p class="alert">Нажимая на кнопку, вы соглашаетесь с <a href="/politika-konfidentsialnosti/" target="_blank">политикой обработки персональных данных</a></p>
                        </div><button class="btn gradient" type="submit"><span class="btn__text">Рассчитать</span></button>
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
