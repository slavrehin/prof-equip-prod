<?php
/**
 * Регистрирует ежедневный агент обновления курсов валют (EUR, USD) из ЦБ РФ
 * (profequip_UpdateCurrencyRatesFromCBR(), см. local/php_interface/include/function.php).
 *
 * От этого курса зависит конвертер цен каталога: товары с базовой ценой,
 * заведённой в EUR, показываются на витрине в рублях через
 * CONVERT_CURRENCY/CURRENCY_ID компонентов bitrix:catalog(.section/.element),
 * который использует CCurrencyRates::ConvertCurrency() — а тот берёт курс
 * из модуля "currency" (b_catalog_currency.AMOUNT), который этот агент и обновляет.
 *
 * Выполняется на реальном хите к сайту, когда подошло время NEXT_EXEC —
 * на проде с живым трафиком этого достаточно. Первый запуск делаем сразу
 * здесь же, чтобы курс был актуален немедленно, не дожидаясь хита.
 */

$agentName = '\profequip_UpdateCurrencyRatesFromCBR();';

$existing = \CAgent::GetList([], ['NAME' => $agentName])->Fetch();

if ($existing) {
    echo "Агент обновления курсов валют уже зарегистрирован (ID={$existing['ID']}), пропускаю.\n";
} else {
    $agentId = \CAgent::AddAgent(
        $agentName,
        'main',
        'N',
        86400,
        '',
        'Y',
        date('d.m.Y H:i:s')
    );

    if (!$agentId) {
        throw new \RuntimeException('Не удалось зарегистрировать агент обновления курсов валют');
    }

    $check = \CAgent::GetByID($agentId)->Fetch();
    if (!$check) {
        throw new \RuntimeException('Агент обновления курсов валют не найден в базе после AddAgent() (ID=' . $agentId . ')');
    }

    echo "Зарегистрирован агент обновления курсов валют (ID=$agentId, раз в сутки).\n";
}

// Первый прогон сразу, не дожидаясь хита с подошедшим NEXT_EXEC.
profequip_UpdateCurrencyRatesFromCBR();

global $DB;
$eurRate = $DB->Query("SELECT AMOUNT, AMOUNT_CNT, CURRENT_BASE_RATE FROM b_catalog_currency WHERE CURRENCY = 'EUR'")->Fetch();
if (!$eurRate || (float)$eurRate['AMOUNT'] <= 0) {
    throw new \RuntimeException('Курс EUR не обновился после запуска profequip_UpdateCurrencyRatesFromCBR()');
}
echo "Курс EUR->RUB после обновления: {$eurRate['AMOUNT']} (CURRENT_BASE_RATE={$eurRate['CURRENT_BASE_RATE']}).\n";
