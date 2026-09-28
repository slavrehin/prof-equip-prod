<?php
/**
 * Структура для лендингов направлений (первым — «Прачечная», /prachechnaya/).
 * Компонент: local/components/custom/direction.landing.
 *
 * Всё содержимое лендинга (блоки, слайды, решения, этапы, гарантия) правится в админке в карточке
 * элемента направления — вкладка «Лендинг» (редактор
 * local/php_interface/include/landing_editor.php). Отдельных инфоблоков под лендинг нет.
 * Стартовое содержимое заливает 2026-09-18-landing-prachechnaya-seed.php.
 *
 * Что создаёт:
 *  - свойства лендинга и вкладку «Лендинг» в инфоблоке направления «Прачечная»;
 *  - свойства CARD_META/CARD_TEXT/CARD_SCOPE в инфоблоке проектов (карточка
 *    проекта на лендинге);
 *  - веб-форму SIMPLE_FORM_4 «Лендинг направления» (единая для всех форм
 *    лендинга, источник лида — поле land_source) + почтовый шаблон;
 *  - SEO-шаблоны (title/description) элемента направления «Прачечная».
 *
 * Идемпотентна: всё ищется по CODE/SID.
 */

global $DB;

CModule::IncludeModule('iblock');
CModule::IncludeModule('form');

// ---------------------------------------------------------------------------
// helpers
// ---------------------------------------------------------------------------

/**
 * @param array $p CODE, NAME, SORT, [TYPE S|N|L|F|E], [MULTIPLE], [WITH_DESCRIPTION], [ROWS], [HINT],
 *                 [ENUM => [xml_id => value]], [LINK_IBLOCK_ID], [REQUIRED], [DEFAULT]
 */
function landing_ensure_property(int $iblockId, array $p): int
{
    global $DB;
    $exists = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $p['CODE']])->Fetch();
    if ($exists) {
        echo "  свойство {$p['CODE']} уже есть, пропускаю\n";
        return (int)$exists['ID'];
    }
    $type = $p['TYPE'] ?? 'S';
    $fields = [
        'IBLOCK_ID' => $iblockId,
        'CODE' => $p['CODE'],
        'NAME' => $p['NAME'],
        'SORT' => $p['SORT'],
        'PROPERTY_TYPE' => $type,
        'MULTIPLE' => $p['MULTIPLE'] ?? 'N',
        'IS_REQUIRED' => $p['REQUIRED'] ?? 'N',
        'WITH_DESCRIPTION' => $p['WITH_DESCRIPTION'] ?? 'N',
        'ACTIVE' => 'Y',
        'ROW_COUNT' => $p['ROWS'] ?? 1,
        'COL_COUNT' => 60,
        'HINT' => $p['HINT'] ?? '',
        'FILTRABLE' => ($type === 'L' || $type === 'E') ? 'Y' : 'N',
        'MULTIPLE_CNT' => 5,
    ];
    if ($type === 'L') {
        $fields['LIST_TYPE'] = 'L';
        $values = [];
        $i = 10;
        foreach ($p['ENUM'] as $xml => $val) {
            $values['n' . $i] = ['VALUE' => $val, 'XML_ID' => $xml, 'SORT' => $i, 'DEF' => (($p['DEFAULT'] ?? null) === $xml) ? 'Y' : 'N'];
            $i += 10;
        }
        $fields['VALUES'] = $values;
    }
    if ($type === 'E') {
        $fields['LINK_IBLOCK_ID'] = $p['LINK_IBLOCK_ID'];
    }
    $prop = new \CIBlockProperty();
    $id = $prop->Add($fields);
    if (!$id) {
        throw new \RuntimeException("Не создано свойство {$p['CODE']}: " . $prop->LAST_ERROR);
    }
    if (!$DB->Query('SELECT ID FROM b_iblock_property WHERE ID = ' . (int)$id . ' AND IBLOCK_ID = ' . (int)$iblockId)->Fetch()) {
        throw new \RuntimeException("Свойство {$p['CODE']} не найдено в базе после Add()");
    }
    echo "  создано свойство {$p['CODE']} (ID=$id)\n";
    return (int)$id;
}

// ---------------------------------------------------------------------------
// 1. свойства лендинга в инфоблоке направления «Прачечная» + вкладка «Лендинг» в форме элемента
// ---------------------------------------------------------------------------

echo "Свойства лендинга в инфоблоке направления:\n";
$ibDirectionForProps = (int)(\CIBlock::GetList([], ['CODE' => 'prachechnaya', 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'] ?? 0);
if (!$ibDirectionForProps) {
    throw new \RuntimeException('Не найден инфоблок направления prachechnaya');
}
foreach (profequip_LandingEnsureDirectionProps($ibDirectionForProps) as $code => $propId) {
    echo "  $code (ID=$propId)\n";
}

// ---------------------------------------------------------------------------
// 2. карточки проектов (инфоблок projects)
// ---------------------------------------------------------------------------

echo "Свойства карточки проекта:\n";
$ibProjects = (int)(\CIBlock::GetList([], ['CODE' => 'projects'])->Fetch()['ID'] ?? 0);
if (!$ibProjects) {
    throw new \RuntimeException('Не найден инфоблок projects');
}
landing_ensure_property($ibProjects, [
    'CODE' => 'CARD_META', 'NAME' => 'Карточка на лендинге: строка над названием', 'SORT' => 900, 'MULTIPLE' => 'Y',
    'HINT' => 'До 3 коротких пунктов: тип объекта, город, ключевая цифра (например «Курорт 5*», «Сочи», «400 номеров»).',
]);
landing_ensure_property($ibProjects, [
    'CODE' => 'CARD_TEXT', 'NAME' => 'Карточка на лендинге: описание', 'SORT' => 910, 'ROWS' => 4,
    'HINT' => '2–3 предложения. Если пусто — карточка без описания.',
]);
landing_ensure_property($ibProjects, [
    'CODE' => 'CARD_SCOPE', 'NAME' => 'Карточка на лендинге: теги работ', 'SORT' => 920, 'MULTIPLE' => 'Y',
    'HINT' => 'Короткие теги: «Проектирование», «Монтаж», названия брендов и т. п.',
]);

// ---------------------------------------------------------------------------
// 3. веб-форма SIMPLE_FORM_4
// ---------------------------------------------------------------------------

echo "Веб-форма SIMPLE_FORM_4:\n";
$form = \CForm::GetBySID('SIMPLE_FORM_4')->Fetch();
if ($form) {
    $formId = (int)$form['ID'];
    echo "  форма уже есть (ID=$formId), пропускаю создание\n";
} else {
    $formId = (int)\CForm::Set([
        'NAME' => 'Лендинг направления',
        'SID' => 'SIMPLE_FORM_4',
        'BUTTON' => 'Отправить',
        'C_SORT' => 400,
        'FIRST_SITE_ID' => 's1',
        'USE_CAPTCHA' => 'N',
        'USE_DEFAULT_TEMPLATE' => 'Y',
        'SHOW_TEMPLATE' => '',
        'MAIL_EVENT_TYPE' => 'FORM_FILLING_SIMPLE_FORM_4',
        'STAT_EVENT1' => 'form',
        'STAT_EVENT2' => 'SIMPLE_FORM_4',
        'arSITE' => ['s1'],
        'arMENU' => ['ru' => 'Лендинг направления', 'en' => 'Direction landing'],
        'arGROUP' => [],
        'DESCRIPTION' => 'Все формы лендингов направлений. Из какого блока пришла заявка — в поле «Источник лида».',
        'DESCRIPTION_TYPE' => 'text',
    ], false, 'N');
    if (!$formId) {
        global $strError;
        throw new \RuntimeException('Не создана веб-форма SIMPLE_FORM_4: ' . ($strError ?? ''));
    }
    echo "  создана форма (ID=$formId)\n";

    // Права на форму — как у SIMPLE_FORM_3 (гостям — «Заполнение», админам — всё). Копируем из БД.
    $donor = \CForm::GetBySID('SIMPLE_FORM_3')->Fetch();
    if ($donor) {
        $rs = $DB->Query('SELECT GROUP_ID, PERMISSION FROM b_form_2_group WHERE FORM_ID = ' . (int)$donor['ID']);
        while ($row = $rs->Fetch()) {
            $DB->Query('INSERT INTO b_form_2_group (FORM_ID, GROUP_ID, PERMISSION) VALUES (' . $formId . ', ' . (int)$row['GROUP_ID'] . ", '" . $DB->ForSql($row['PERMISSION']) . "')");
        }
    }
}

// Статус по умолчанию (без него результат не сохраняется)
$hasStatus = $DB->Query('SELECT ID FROM b_form_status WHERE FORM_ID = ' . $formId . " AND DEFAULT_VALUE = 'Y'")->Fetch();
if (!$hasStatus) {
    $statusId = \CFormStatus::Set([
        'FORM_ID' => $formId,
        'C_SORT' => 100,
        'ACTIVE' => 'Y',
        'TITLE' => 'DEFAULT',
        'DESCRIPTION' => 'DEFAULT',
        'CSS' => 'statusgreen',
        'DEFAULT_VALUE' => 'Y',
        'arPERMISSION_VIEW' => [0],
        'arPERMISSION_MOVE' => [0],
        'arPERMISSION_EDIT' => [0],
        'arPERMISSION_DELETE' => [0],
    ], false, 'N');
    if (!$statusId) {
        throw new \RuntimeException('Не создан статус формы SIMPLE_FORM_4');
    }
    echo "  создан статус по умолчанию (ID=$statusId)\n";
}

$fields = [
    // sid => [title, answer type, width/height, in results table]
    ['land_name', 'Имя', 'text', 'Y'],
    ['land_phone', 'Телефон / контакт', 'text', 'Y'],
    ['land_email', 'Email', 'text', 'Y'],
    ['land_company', 'Компания', 'text', 'Y'],
    ['land_topic', 'Тема обращения', 'text', 'Y'],
    ['land_model', 'Производитель и модель оборудования', 'text', 'Y'],
    ['land_article', 'Артикул', 'text', 'Y'],
    ['land_comment', 'Комментарий', 'textarea', 'Y'],
    ['land_source', 'Источник лида (блок страницы)', 'text', 'Y'],
    ['land_url', 'Страница', 'text', 'Y'],
    ['land_file_1', 'Файл 1', 'file', 'N'],
    ['land_file_2', 'Файл 2', 'file', 'N'],
    ['land_file_3', 'Файл 3', 'file', 'N'],
    ['CAPTCHA_TOKEN', 'CAPTCHA_TOKEN', 'text', 'Y'],
    ['CAPTCHA_VERIFY_DATA', 'CAPTCHA_VERIFY_DATA', 'text', 'Y'],
    ['CAPTCHA_VERIFY_DATA_FULL', 'CAPTCHA_VERIFY_DATA_FULL', 'text', 'Y'],
    ['land_client_id', 'Yandex Client ID', 'text', 'N'],
    ['land_utm_source', 'UTM Source', 'text', 'N'],
    ['land_utm_medium', 'UTM Medium', 'text', 'N'],
    ['land_utm_campaign', 'UTM Campaign', 'text', 'N'],
    ['land_utm_content', 'UTM Content', 'text', 'N'],
    ['land_utm_term', 'UTM Term', 'text', 'N'],
];
foreach ($fields as [$sid, $title, $type, $inTable]) {
    if (\CFormField::GetBySID($sid, $formId)->Fetch()) {
        echo "  поле $sid уже есть, пропускаю\n";
        continue;
    }
    $fieldId = \CFormField::Set([
        'FORM_ID' => $formId,
        'ACTIVE' => 'Y',
        'TITLE' => $title,
        'TITLE_TYPE' => 'text',
        'SID' => $sid,
        'C_SORT' => \CFormField::GetNextSort($formId),
        'ADDITIONAL' => 'N',
        'REQUIRED' => 'N',
        'IN_RESULTS_TABLE' => $inTable,
        'IN_EXCEL_TABLE' => 'Y',
        'arANSWER' => [[
            'MESSAGE' => ' ',
            'VALUE' => '',
            'C_SORT' => 100,
            'ACTIVE' => 'Y',
            'FIELD_TYPE' => $type,
            'FIELD_WIDTH' => 0,
            'FIELD_HEIGHT' => 0,
            'FIELD_PARAM' => '',
        ]],
    ], false, 'N', 'N');
    if (!$fieldId) {
        global $strError;
        throw new \RuntimeException("Не создано поле формы $sid: " . ($strError ?? ''));
    }
    $chk = $DB->Query('SELECT a.ID FROM b_form_answer a WHERE a.FIELD_ID = ' . (int)$fieldId)->Fetch();
    if (!$chk) {
        throw new \RuntimeException("У поля $sid нет ответа (b_form_answer) после Set()");
    }
    echo "  создано поле $sid (ID=$fieldId)\n";
}

// Почтовое правило «форма заполнена» — тем же адресатом, что у SIMPLE_FORM_1..3.
// SetMailTemplate() создаёт только правило (event message) и возвращает массив ID —
// привязку к форме (b_form_2_mail_template) делаем сами; правило ищем по EVENT_NAME,
// а не приводим результат к int (иначе легко перезаписать чужое правило: ID=1 — NEW_USER).
$msg = $DB->Query("SELECT ID, EMAIL_TO FROM b_event_message WHERE EVENT_NAME = 'FORM_FILLING_SIMPLE_FORM_4' ORDER BY ID LIMIT 1")->Fetch();
if (!$msg) {
    \CForm::SetMailTemplate($formId, 'Y', '', false);
    $msg = $DB->Query("SELECT ID, EMAIL_TO FROM b_event_message WHERE EVENT_NAME = 'FORM_FILLING_SIMPLE_FORM_4' ORDER BY ID LIMIT 1")->Fetch();
    if (!$msg) {
        throw new \RuntimeException('Почтовое правило FORM_FILLING_SIMPLE_FORM_4 не создано');
    }
    echo "  создано почтовое правило (ID={$msg['ID']})\n";
}
if (!$DB->Query('SELECT 1 FROM b_form_2_mail_template WHERE FORM_ID = ' . $formId . ' AND MAIL_TEMPLATE_ID = ' . (int)$msg['ID'])->Fetch()) {
    $DB->Query('INSERT INTO b_form_2_mail_template (FORM_ID, MAIL_TEMPLATE_ID) VALUES (' . $formId . ', ' . (int)$msg['ID'] . ')');
    echo "  правило привязано к форме\n";
}
if ($msg['EMAIL_TO'] === '#DEFAULT_EMAIL_FROM#') {
    $donorTpl = $DB->Query("SELECT EMAIL_TO FROM b_event_message WHERE EVENT_NAME = 'FORM_FILLING_SIMPLE_FORM_3' ORDER BY ID LIMIT 1")->Fetch();
    $em = new \CEventMessage();
    $em->Update((int)$msg['ID'], [
        'EMAIL_TO' => $donorTpl['EMAIL_TO'] ?? 'info@prof-equip.ru',
        'SUBJECT' => '#SERVER_NAME#: заявка с лендинга [#RS_FORM_ID#] #RS_FORM_NAME#',
    ]);
    $chk = $DB->Query('SELECT EMAIL_TO FROM b_event_message WHERE ID = ' . (int)$msg['ID'])->Fetch();
    if ($chk['EMAIL_TO'] === '#DEFAULT_EMAIL_FROM#') {
        throw new \RuntimeException('Адресат почтового правила SIMPLE_FORM_4 не обновился');
    }
    echo "  адресат почтового правила (ID={$msg['ID']}): {$chk['EMAIL_TO']}\n";
}
if (!$DB->Query('SELECT 1 FROM b_form_2_mail_template WHERE FORM_ID = ' . $formId)->Fetch()) {
    throw new \RuntimeException('Почтовое правило не привязано к форме SIMPLE_FORM_4');
}

// ---------------------------------------------------------------------------
// 4. SEO-шаблоны элемента направления «Прачечная» (вкладка «SEO» в админке)
// ---------------------------------------------------------------------------

echo "SEO элемента направления:\n";
$ibDirection = (int)(\CIBlock::GetList([], ['CODE' => 'prachechnaya'])->Fetch()['ID'] ?? 0);
$dirEl = $ibDirection ? \CIBlockElement::GetList([], ['IBLOCK_ID' => $ibDirection, 'CODE' => 'prachechnaya'], false, ['nTopCount' => 1], ['ID'])->Fetch() : null;
if ($dirEl) {
    $tpl = new \Bitrix\Iblock\InheritedProperty\ElementTemplates($ibDirection, (int)$dirEl['ID']);
    $current = $tpl->findTemplates();
    $toSet = [];
    if (empty($current['ELEMENT_META_TITLE']['TEMPLATE'])) {
        $toSet['ELEMENT_META_TITLE'] = 'Оборудование для прачечных и проектирование под ключ — ПРОФЭКВИП';
    }
    if (empty($current['ELEMENT_META_DESCRIPTION']['TEMPLATE'])) {
        $toSet['ELEMENT_META_DESCRIPTION'] = 'Проектирование, поставка, монтаж и сервис профессиональных прачечных. Оборудование и запчасти в наличии на складе. Официальный дилер JENSEN и TOLON.';
    }
    if ($toSet) {
        // set() заменяет набор шаблонов целиком — переносим уже заданные
        $keep = [];
        foreach ($current as $code => $row) {
            if (!empty($row['TEMPLATE'])) {
                $keep[$code] = $row['TEMPLATE'];
            }
        }
        $tpl->set($toSet + $keep);
        echo "  заданы SEO-шаблоны: " . implode(', ', array_keys($toSet)) . "\n";
    } else {
        echo "  SEO-шаблоны уже заданы, пропускаю\n";
    }
} else {
    echo "  элемент направления prachechnaya не найден — SEO не задано\n";
}

// Итоговая проверка запросом к базе
$n = (int)$DB->Query("SELECT COUNT(*) C FROM b_iblock_property WHERE IBLOCK_ID = $ibDirectionForProps AND CODE LIKE 'LAND\\_%'")->Fetch()['C'];
if ($n < 8) {
    throw new \RuntimeException("В инфоблоке направления $n свойств лендинга, ожидалось 8");
}
if (!$DB->Query("SELECT COUNT(*) C FROM b_form_field WHERE FORM_ID = $formId")->Fetch()['C']) {
    throw new \RuntimeException('У формы SIMPLE_FORM_4 нет полей');
}
echo "Структура лендингов направлений готова.\n";

return true;
