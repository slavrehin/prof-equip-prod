<?php
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
?><?php
if (
    !empty($_POST["captcha_data"])
    && is_string($_POST["captcha_data"])
) {
    $response = json_decode(
        file_get_contents("https://smartcaptcha.yandexcloud.net/validate?secret=" . YA_CAPTCHA_SERVER_API_KEY . "&token=" . $_POST['captcha_data'] . "&ip=" . $_SERVER['REMOTE_ADDR']),
        true
    );
    if ($response['status'] !== "ok") {
        if (
            !empty($response['message'])
            && is_string($response['message'])
        ) {
            $error_text = "Упс! Капча не пройдена: " . $response['message'];
        } else {
            $error_text = "Упс! Капча не пройдена.";
        }
        $response_text = '<form>
    <div class="alert">
        <p>' . $error_text . '</p>
    </div>
</form>';
        return $response_text;
    }
}
?><?php
$APPLICATION->IncludeComponent(
    "bitrix:system.auth.forgotpasswd",
    "phoenix",
    array(
        "COMPONENT_TEMPLATE" => "phoenix",
        "FORGOT_PASSWORD_URL" => "/local/ajax/forget.php",
        "PROFILE_URL" => "/personal/",
        "REGISTER_URL" => "",
        "AUTH_FORM_URL" => "",
        "SHOW_ERRORS" => "Y"
    )
);
?>