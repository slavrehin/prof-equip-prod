<?php
/**
 * Копии заявок с сайта на info@prof-equip.ru.
 *
 * После восстановления базы (bootstrap.php, 06.11) почтовые правила
 * FORM_FILLING_SIMPLE_FORM_1..3 существовали, но не были привязаны к формам
 * (b_form_2_mail_template): CForm::SetMailTemplate() создаёт правило, а привязку
 * не делает. form.result.new шлёт письма только по привязанным правилам, поэтому
 * заявки уходили в Bitrix24, а на почту — нет. Привязана была одна SIMPLE_FORM_4.
 *
 * Для каждой формы: правило есть, активно, адресат info@prof-equip.ru,
 * правило привязано к форме. Повторный запуск ничего не меняет.
 */

if (!CModule::IncludeModule('form')) {
    throw new \RuntimeException('Модуль form не подключается');
}

(function () {
    global $DB;
    $to = 'info@prof-equip.ru';

    foreach (['SIMPLE_FORM_1', 'SIMPLE_FORM_2', 'SIMPLE_FORM_3', 'SIMPLE_FORM_4'] as $sid) {
        $form = \CForm::GetBySID($sid)->Fetch();
        if (!$form) {
            throw new \RuntimeException("Форма $sid не найдена");
        }
        $formId = (int)$form['ID'];
        $event = 'FORM_FILLING_' . $sid;
        echo "$sid (ID=$formId):\n";

        $find = fn() => $DB->Query("SELECT ID, ACTIVE, EMAIL_TO FROM b_event_message WHERE EVENT_NAME = '" . $DB->ForSql($event) . "' ORDER BY ID LIMIT 1")->Fetch();
        $msg = $find();
        if (!$msg) {
            \CForm::SetMailTemplate($formId, 'Y', '', false);
            $msg = $find();
            if (!$msg) {
                throw new \RuntimeException("Почтовое правило $event не создано");
            }
            echo "  создано почтовое правило (ID={$msg['ID']})\n";
        }
        $msgId = (int)$msg['ID'];

        $upd = [];
        if ($msg['ACTIVE'] !== 'Y') {
            $upd['ACTIVE'] = 'Y';
        }
        if (stripos((string)$msg['EMAIL_TO'], $to) === false) {
            $upd['EMAIL_TO'] = $to;
        }
        if ($upd) {
            $em = new \CEventMessage();
            if (!$em->Update($msgId, $upd)) {
                throw new \RuntimeException("Правило $event (ID=$msgId) не обновлено: " . $em->LAST_ERROR);
            }
            echo '  правило обновлено: ' . json_encode($upd, JSON_UNESCAPED_UNICODE) . "\n";
        }

        if (!$DB->Query("SELECT 1 FROM b_form_2_mail_template WHERE FORM_ID = $formId AND MAIL_TEMPLATE_ID = $msgId")->Fetch()) {
            $DB->Query("INSERT INTO b_form_2_mail_template (FORM_ID, MAIL_TEMPLATE_ID) VALUES ($formId, $msgId)");
            echo "  правило ID=$msgId привязано к форме\n";
        }

        $chk = $DB->Query("SELECT m.ACTIVE, m.EMAIL_TO FROM b_form_2_mail_template t
            JOIN b_event_message m ON m.ID = t.MAIL_TEMPLATE_ID
            WHERE t.FORM_ID = $formId AND t.MAIL_TEMPLATE_ID = $msgId")->Fetch();
        if (!$chk || $chk['ACTIVE'] !== 'Y' || stripos($chk['EMAIL_TO'], $to) === false) {
            throw new \RuntimeException("$sid: после миграции нет активного привязанного правила на $to");
        }
        echo "  ок: письма на {$chk['EMAIL_TO']}\n";
    }
})();
