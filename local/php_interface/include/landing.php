<?php
/**
 * Хелперы лендингов направлений (компонент local/components/custom/direction.landing).
 * Подключается из init.php. Всё здесь — с префиксом profequip_Landing*, чтобы не
 * пересекаться с остальными хелперами сайта (function.php).
 */

use Bitrix\Main\Loader;

/**
 * Значение свойства элемента, полученное через CIBlockElement::GetProperties():
 * скаляр (для одиночных) либо массив значений (для множественных).
 */
function profequip_LandingProp(array $props, string $code, bool $multiple = false)
{
    $p = $props[$code] ?? null;
    if (!$p) {
        return $multiple ? [] : '';
    }
    if ($multiple) {
        $vals = is_array($p['VALUE']) ? $p['VALUE'] : ($p['VALUE'] !== '' && $p['VALUE'] !== null ? [$p['VALUE']] : []);
        return array_values(array_filter($vals, static fn($v) => $v !== '' && $v !== null && $v !== false));
    }
    $v = is_array($p['VALUE']) ? reset($p['VALUE']) : $p['VALUE'];
    return $v === null || $v === false ? '' : $v;
}

/**
 * <picture> с WebP-источником и srcset (те же принципы, что profequip_RenderProjectCard):
 * ширины из $widths, ресайз через CFile::ResizeImageGet. На test3 resize_cache смонтирован
 * read-only — ResizeImageGet откатывается на оригинал, WebP тогда не строится (это ожидаемо).
 *
 * @param array $o alt, class, sizes, lazy(bool), priority(bool)
 */
function profequip_LandingPicture(int $fileId, array $widths, array $o = []): string
{
    if ($fileId <= 0) {
        return '';
    }
    $raster = [];
    $webp = [];
    $src = '';
    $w0 = 0;
    $h0 = 0;
    foreach ($widths as $width) {
        $img = \CFile::ResizeImageGet($fileId, ['width' => $width, 'height' => $width * 4], BX_RESIZE_IMAGE_PROPORTIONAL, true);
        if (empty($img['src'])) {
            continue;
        }
        $realW = (int)$img['width'];
        $raster[] = $img['src'] . ' ' . $realW . 'w';
        $wp = profequip_GetWebpVariant($img['src']);
        if ($wp) {
            $webp[] = $wp . ' ' . $realW . 'w';
        }
        if ($src === '') {
            $src = $img['src'];
            $w0 = $realW;
            $h0 = (int)$img['height'];
        }
    }
    if ($src === '') {
        return '';
    }
    // оригинал мог быть меньше самой большой ширины — одинаковые строки в srcset не нужны
    $raster = array_values(array_unique($raster));
    $webp = array_values(array_unique($webp));
    $sizes = htmlspecialcharsbx($o['sizes'] ?? '100vw');
    $alt = htmlspecialcharsbx($o['alt'] ?? '');
    $class = !empty($o['class']) ? ' class="' . htmlspecialcharsbx($o['class']) . '"' : '';
    $loading = !empty($o['priority']) ? ' fetchpriority="high"' : (($o['lazy'] ?? true) ? ' loading="lazy" decoding="async"' : '');
    $dims = $w0 > 0 && $h0 > 0 ? ' width="' . $w0 . '" height="' . $h0 . '"' : '';

    $html = '<picture>';
    if ($webp) {
        $html .= '<source type="image/webp" srcset="' . htmlspecialcharsbx(implode(', ', $webp)) . '" sizes="' . $sizes . '">';
    }
    $html .= '<img' . $class . ' src="' . htmlspecialcharsbx($src) . '"' . $dims . $loading
        . (count($raster) > 1 ? ' srcset="' . htmlspecialcharsbx(implode(', ', $raster)) . '" sizes="' . $sizes . '"' : '')
        . ' alt="' . $alt . '"></picture>';
    return $html;
}

/** Цена товара в рублях (базовая цена; иностранная валюта — по курсу магазина с наценкой). null — цена не задана. */
function profequip_LandingProductPrice(int $productId): ?float
{
    if (!Loader::includeModule('catalog') || !Loader::includeModule('currency')) {
        return null;
    }
    $row = \CPrice::GetList([], ['PRODUCT_ID' => $productId, 'CATALOG_GROUP_ID' => 1])->Fetch();
    if (!$row || (float)$row['PRICE'] <= 0) {
        return null;
    }
    $price = (float)$row['PRICE'];
    if ($row['CURRENCY'] !== 'RUB') {
        $price = (float)\CCurrencyRates::ConvertCurrency($price, $row['CURRENCY'], 'RUB');
    }
    return $price > 0 ? round($price) : null;
}

/**
 * До 3 ключевых характеристик для карточки товара — берутся из собственных
 * свойств товара (их редактируют в админке), первые заполненные по приоритету.
 * Подпись — название свойства до запятой, единица — после («Загрузка, Кг» → «Загрузка» / «кг»).
 *
 * @param array $rows строки CIBlockElement::GetProperty() товара (одним вызовом, все свойства)
 * @return array<int, array{label: string, value: string}>
 */
function profequip_LandingProductSpecs(array $rows, int $limit = 3): array
{
    static $priority = [
        'ZAGRUZKA_KG', 'VMESTIMOST_KG', 'PROIZVODITELNOST_KG_CH', 'SKOROST_PRI_OTZHIME_OB_MIN', 'G_FACTOR',
        'DLINA_VALA_MM', 'DIAMETR_VALA_MM', 'SHIRINA_ZONY_GLAZHENIYA_MM', 'SKOROST_GLAZHENIYA_M_MIN',
        'UDALENIE_PYATEN', 'MOSHHNOST_KVT', 'RAZMER_RABOCHEJ_POVERHNOSTI', 'GABARITNYE_RAZMERY_SH_G_V_MM',
        'REVERS_BARABANA', 'VES_KG',
    ];
    $found = [];
    foreach ($rows as $p) {
        if (!in_array($p['CODE'], $priority, true) || isset($found[$p['CODE']])) {
            continue; // у множественных берём первое значение
        }
        $val = trim((string)($p['PROPERTY_TYPE'] === 'L' ? $p['VALUE_ENUM'] : $p['VALUE']));
        if ($val === '') {
            continue;
        }
        $parts = array_map('trim', explode(',', $p['NAME'], 2));
        $label = $parts[0];
        $unit = isset($parts[1]) ? mb_strtolower($parts[1]) : '';
        if (preg_match('/^(.*?)\s*\((.+)\)$/u', $label, $m)) { // «Ширина Вала (Мм)»
            $label = trim($m[1]);
            $unit = mb_strtolower($m[2]);
        }
        $label = mb_strtoupper(mb_substr($label, 0, 1)) . mb_strtolower(mb_substr($label, 1));
        $found[$p['CODE']] = ['label' => $label, 'value' => $val . ($unit !== '' ? ' ' . $unit : '')];
    }
    $out = [];
    foreach ($priority as $code) {
        if (isset($found[$code])) {
            $out[] = $found[$code];
        }
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

/**
 * Имена полей формы SIMPLE_FORM_4 (form_text_<ID ответа> и т. п.): SID вопроса → имя input.
 *
 * @return array{form_id: int, names: array<string,string>}
 */
function profequip_LandingFormFields(string $formSid = 'SIMPLE_FORM_4'): array
{
    if (!Loader::includeModule('form')) {
        return ['form_id' => 0, 'names' => []];
    }
    $form = \CForm::GetBySID($formSid)->Fetch();
    if (!$form) {
        return ['form_id' => 0, 'names' => []];
    }
    $formId = (int)$form['ID'];
    $arForm = $arQuestions = $arAnswers = $arDropDown = $arMultiSelect = [];
    \CForm::GetDataByID($formId, $arForm, $arQuestions, $arAnswers, $arDropDown, $arMultiSelect);
    $names = [];
    foreach ($arAnswers as $sid => $answers) {
        $a = reset($answers);
        $names[$sid] = 'form_' . $a['FIELD_TYPE'] . '_' . $a['ID'];
    }
    return ['form_id' => $formId, 'names' => $names];
}

/** Русская дата «5 сентября 2026» */
function profequip_LandingDate(?string $bxDate): string
{
    $ts = $bxDate ? MakeTimeStamp($bxDate) : 0;
    if (!$ts) {
        return '';
    }
    static $months = [1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    return (int)date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

// ---------------------------------------------------------------------------
// CRM: заявка с формы лендинга (SIMPLE_FORM_4) → лид Битрикс24
// ---------------------------------------------------------------------------

/**
 * Собирает поля лида по результату веб-формы. Без сетевых вызовов — чтобы можно было
 * проверить состав лида на test3, куда лиды не уходят.
 *
 * @return array|null null — форма не наша / нет контакта
 */
function profequip_BuildLandingLead(int $resultId): ?array
{
    if (!Loader::includeModule('form')) {
        return null;
    }
    $res = \CFormResult::GetByID($resultId)->Fetch();
    if (!$res) {
        return null;
    }
    $form = \CForm::GetByID((int)$res['FORM_ID'])->Fetch();
    if (!$form || $form['SID'] !== 'SIMPLE_FORM_4') {
        return null;
    }

    $raw = $answers = [];
    \CFormResult::GetDataByID($resultId, [], $raw, $answers);
    $data = [];
    $fileLinks = [];
    foreach ($answers as $sid => $rows) {
        $first = reset($rows);
        if (!$first) {
            continue;
        }
        if (!empty($first['USER_FILE_ID'])) {
            $fileLinks[] = ($first['USER_FILE_NAME'] ?? 'файл') . ' — https://prof-equip.ru/bitrix/tools/form_show_file.php?rid='
                . $resultId . '&hash=' . ($first['USER_FILE_HASH'] ?? '') . '&action=download';
            continue;
        }
        $text = trim((string)($first['USER_TEXT'] ?? ''));
        if ($text !== '') {
            $data[$sid] = $text;
        }
    }

    $phone = $data['land_phone'] ?? '';
    $email = $data['land_email'] ?? '';
    if ($phone !== '' && strpos($phone, '@') !== false) { // форма подбора запчасти: «телефон или e-mail» в одном поле
        $email = $email ?: $phone;
        $phone = '';
    }
    if ($phone === '' && $email === '') {
        return null;
    }

    $source = $data['land_source'] ?? 'Лендинг направления';
    $comments = "Форма: SIMPLE_FORM_4 (лендинг направления)\n";
    $comments .= 'Дата: ' . date('d.m.Y H:i:s') . "\n";
    $comments .= "Источник лида: $source\n";
    foreach (['land_topic' => 'Тема обращения', 'land_objtype' => 'Тип объекта', 'land_company' => 'Компания', 'land_model' => 'Оборудование (производитель, модель)',
                 'land_article' => 'Артикул', 'land_comment' => 'Сообщение', 'land_url' => 'URL'] as $sid => $label) {
        if (!empty($data[$sid])) {
            $comments .= "$label: {$data[$sid]}\n";
        }
    }
    foreach ($fileLinks as $link) {
        $comments .= "Вложение: $link\n";
    }

    $fields = [
        'TITLE' => 'Заявка с лендинга: ' . $source,
        'NAME' => $data['land_name'] ?? 'Клиент',
        'COMMENTS' => $comments,
        'SOURCE_DESCRIPTION' => $source, // отдельное поле лида «Дополнительно об источнике»
    ];
    if ($phone !== '') {
        $fields['PHONE'] = [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']];
    }
    if ($email !== '') {
        $fields['EMAIL'] = [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']];
    }
    if (!empty($data['land_company'])) {
        $fields['COMPANY_TITLE'] = $data['land_company'];
    }
    if (!empty($data['land_client_id'])) {
        // те же поля CRM, что у остальных форм сайта (ветка crm-clientid-utm)
        $fields['UF_CRM_1771401080'] = $data['land_client_id'];
        $fields['UF_CRM_YA_CID'] = $data['land_client_id'];
        $fields['UF_CRM_YA_COUNTER_ID'] = (string)YANDEX_METRIKA_COUNTER_ID;
    }
    foreach (['source', 'medium', 'campaign', 'content', 'term'] as $utm) {
        if (!empty($data['land_utm_' . $utm])) {
            $fields['UTM_' . strtoupper($utm)] = $data['land_utm_' . $utm];
        }
    }
    return $fields;
}

function profequip_SendLandingLeadToBitrix24($resultId, $arFields = null)
{
    $id = (int)(is_numeric($arFields) && $arFields > 0 ? $arFields : $resultId);
    if ($id <= 0) {
        return;
    }
    // На тестовой копии не отправляем лиды в боевой Bitrix24 (как sendFormToBitrix24 в init.php)
    if (($_SERVER['HTTP_HOST'] ?? '') === 'test3.prof-equip.ru') {
        return;
    }
    $fields = profequip_BuildLandingLead($id);
    if (!$fields) {
        return;
    }
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => BITRIX24_WEBHOOK_URL . 'crm.lead.add.json',
        CURLOPT_POSTFIELDS => http_build_query(['fields' => $fields]),
        CURLOPT_TIMEOUT => 30,
    ]);
    curl_exec($curl);
    curl_close($curl);
}

\Bitrix\Main\EventManager::getInstance()->addEventHandler('form', 'onAfterResultAdd', 'profequip_SendLandingLeadToBitrix24');
