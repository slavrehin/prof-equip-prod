<?php
/**
 * Заводит вопросы FAQ разделов каталога из faq.php: создаёт/обновляет элементы инфоблока faq (по CODE)
 * и записывает их в поле раздела UF_FAQ (порядок вопросов = SORT элементов, шаг 10).
 *
 * Раздел и элементы ищутся по CODE, общие вопросы ('shared') — по NAME. Идемпотентно: повторный запуск
 * обновляет тексты и привязку. Вопросы, привязанные к разделу в админке и отсутствующие в faq.php,
 * из UF_FAQ убираются (сами элементы не удаляются).
 *
 * Флаги: --dry (только показать), --only=code1,code2
 * Запуск:
 *   docker compose exec web php /var/www/html/local/deploy/catalog_seo/seed_faq.php --dry   # test3
 *   php local/deploy/catalog_seo/seed_faq.php                                              # прод
 */

$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 3);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

CModule::IncludeModule('iblock');

$dry = in_array('--dry', $argv, true);
$only = [];
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = array_filter(explode(',', substr($arg, 7)));
    }
}

$catalogId = (int)GetIBlockIDByCode('catalog');
$faqId = (int)GetIBlockIDByCode('faq');
if (!$catalogId || !$faqId) {
    exit("Нет инфоблока catalog или faq\n");
}

global $USER_FIELD_MANAGER;
$el = new CIBlockElement();

foreach (require __DIR__ . '/faq.php' as $sectionCode => $conf) {
    if ($only && !in_array($sectionCode, $only, true)) {
        continue;
    }
    $section = CIBlockSection::GetList([], ['IBLOCK_ID' => $catalogId, 'CODE' => $sectionCode], false, ['ID', 'NAME'])->Fetch();
    if (!$section) {
        echo "[$sectionCode] раздел не найден — пропуск\n";
        continue;
    }
    $ids = [];
    $sort = 0;
    foreach ($conf['items'] as [$code, $question, $answer]) {
        $sort += 10;
        $fields = ['IBLOCK_ID' => $faqId, 'ACTIVE' => 'Y', 'CODE' => $code, 'NAME' => $question, 'SORT' => $sort,
            'DETAIL_TEXT' => $answer, 'DETAIL_TEXT_TYPE' => 'html'];
        $existing = CIBlockElement::GetList([], ['IBLOCK_ID' => $faqId, '=CODE' => $code], false, ['nTopCount' => 1],
            ['ID', 'NAME', 'SORT', 'DETAIL_TEXT', 'ACTIVE'])->Fetch();
        if ($existing) {
            $same = $existing['NAME'] === $question && (int)$existing['SORT'] === $sort && trim($existing['DETAIL_TEXT']) === $answer && $existing['ACTIVE'] === 'Y';
            echo "[$sectionCode] " . ($same ? 'без изменений' : 'обновить') . " #{$existing['ID']} $question\n";
            if (!$same && !$dry && !$el->Update((int)$existing['ID'], $fields)) {
                exit("Ошибка обновления: {$el->LAST_ERROR}\n");
            }
            $ids[] = (int)$existing['ID'];
        } else {
            echo "[$sectionCode] создать: $question\n";
            if (!$dry) {
                $newId = (int)$el->Add($fields);
                if (!$newId) {
                    exit("Ошибка создания: {$el->LAST_ERROR}\n");
                }
                $ids[] = $newId;
            }
        }
    }
    foreach ($conf['shared'] ?? [] as $name) {
        $shared = CIBlockElement::GetList([], ['IBLOCK_ID' => $faqId, '=NAME' => $name, 'ACTIVE' => 'Y'], false, ['nTopCount' => 1], ['ID'])->Fetch();
        if ($shared) {
            $ids[] = (int)$shared['ID'];
            echo "[$sectionCode] общий вопрос #{$shared['ID']} $name\n";
        } else {
            echo "[$sectionCode] общий вопрос не найден: $name\n";
        }
    }

    $current = $USER_FIELD_MANAGER->GetUserFieldValue('IBLOCK_' . $catalogId . '_SECTION', 'UF_FAQ', (int)$section['ID']) ?: [];
    $current = array_map('intval', (array)$current);
    sort($current);
    $target = $ids;
    sort($target);
    if ($current === $target) {
        echo "[$sectionCode] UF_FAQ без изменений (" . count($ids) . ")\n";
        continue;
    }
    echo "[$sectionCode] UF_FAQ: " . implode(',', $current) . ' → ' . implode(',', $ids) . "\n";
    if (!$dry) {
        $USER_FIELD_MANAGER->Update('IBLOCK_' . $catalogId . '_SECTION', (int)$section['ID'], ['UF_FAQ' => $ids]);
    }
}

if (!$dry) {
    BXClearCache(true, '/profequip/catalog_seo/');
    $GLOBALS['CACHE_MANAGER']->ClearByTag('iblock_id_' . $catalogId);
}
echo $dry ? "DRY RUN — ничего не записано\n" : "Готово\n";
