<?

AddEventHandler("form","onBeforeResultAdd","onBeforeResultAddHandler");

function onBeforeResultAddHandler($WEB_FORM_ID,$arFields,&$arrVALUES)
{
    global $APPLICATION;

    $request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
    $token = $request->get("smart-token");
    $curl = "https://smartcaptcha.yandexcloud.net/";
    $check_captcha = check_captcha($token);
    if ($check_captcha === "ok" || check_curl($curl)) {
            if (CForm::GetDataByID($WEB_FORM_ID, 
            $form, 
            $questions, 
            $answers, 
            $dropdown, 
            $multiselect))
        {
            if ($answers["CAPTCHA_TOKEN"][0]["ID"]) { $arrVALUES["form_text_".$answers["CAPTCHA_TOKEN"][0]["ID"]] = $token; }
            if ($answers["CAPTCHA_VERIFY_DATA"][0]["ID"]) { $arrVALUES["form_text_".$answers["CAPTCHA_VERIFY_DATA"][0]["ID"]] = $check_captcha; }
            if ($answers["CAPTCHA_VERIFY_DATA_FULL"][0]["ID"]) { $arrVALUES["form_text_".$answers["CAPTCHA_VERIFY_DATA_FULL"][0]["ID"]] = 
                check_curl($curl)?("Unreachable ".$curl.". Error code ".check_curl($curl)):"Allow"; }
    }
    } else {
        $APPLICATION->ThrowException($check_captcha);
    }

}

function check_curl($curl) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $curl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);

    $server_output = curl_exec($ch);

    return curl_errno($ch);
}

function check_captcha($token) {
    $ch = curl_init();
    $args = http_build_query([
        "secret" => YA_CAPTCHA_SERVER_API_KEY,
        "token" => $token,
        "ip" => $_SERVER['REMOTE_ADDR'], // Нужно передать IP-адрес пользователя.
                                         // Способ получения IP-адреса пользователя зависит от вашего прокси.
    ]);
    curl_setopt($ch, CURLOPT_URL, "https://smartcaptcha.yandexcloud.net/validate?$args");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);

    $server_output = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode !== 200) {
        //echo "Allow access due to an error: code=$httpcode; message=$server_output\n";
        return true;
    }
    $resp = json_decode($server_output);
    return $resp->status;
}