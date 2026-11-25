<?php
/**
 * Адрес отправителя писем сайта (#DEFAULT_EMAIL_FROM#) — info@prof-equip.ru.
 *
 * После восстановления базы стоял admin@prof-equip.local: с таким From письма
 * не принимает SMTP Яндекса (отправка идёт через ящик info@ по msmtp, /etc/msmtprc),
 * а DMARC домена (p=quarantine) отправил бы их в спам.
 * Меняется только адрес вне домена prof-equip.ru.
 */

(function () {
    global $DB;
    $from = 'info@prof-equip.ru';
    $cur = (string)\COption::GetOptionString('main', 'email_from', '');
    if (preg_match('/@prof-equip\.ru$/i', $cur)) {
        echo "email_from уже $cur, пропускаю\n";
        return;
    }
    \COption::SetOptionString('main', 'email_from', $from);
    $chk = $DB->Query("SELECT VALUE FROM b_option WHERE MODULE_ID = 'main' AND NAME = 'email_from' AND (SITE_ID IS NULL OR SITE_ID = '')")->Fetch();
    if (($chk['VALUE'] ?? '') !== $from) {
        throw new \RuntimeException('email_from не обновился');
    }
    echo "email_from: '$cur' -> '$from'\n";
})();
