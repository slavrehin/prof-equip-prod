<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

// Увеличиваем лимиты выполнения для длительных операций
set_time_limit(600); // 10 минут
ini_set('max_execution_time', 600);
ini_set('memory_limit', '512M');

// Отключаем буферизацию вывода для предотвращения таймаута
if (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json');
header('X-Accel-Buffering: no'); // Отключаем буферизацию в nginx
ignore_user_abort(true);

// Проверка прав доступа - только для администраторов
global $USER;
if (php_sapi_name() !== 'cli' && !$USER->IsAdmin()) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Доступ запрещен'
    ]);
    die();
}

use Bitrix\Main\Loader;

if (!Loader::includeModule('iblock')) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Модуль инфоблоков не установлен'
    ]);
    die();
}

require_once(__DIR__ . '/parser.php');

// Проверяем, что это POST запрос
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Метод не поддерживается'
    ]);
    die();
}

$action = $_POST['action'] ?? '';

if ($action === 'import') {
    // Импорт ссылок и сохранение в XML
    $sectionId = intval($_POST['section_id'] ?? 0);
    $sectionCode = trim($_POST['section_code'] ?? '');
    $sectionName = trim($_POST['section_name'] ?? '');

    if (!$sectionId || !$sectionCode) {
        echo json_encode([
            'success' => false,
            'message' => 'Не указан раздел для импорта'
        ]);
        die();
    }

    // Проверяем существование раздела
    $rsSection = CIBlockSection::GetList(
        [],
        [
            'IBLOCK_ID' => 11,
            'ID' => $sectionId,
            'ACTIVE' => 'Y'
        ],
        false,
        ['ID', 'NAME', 'CODE']
    );

    if (!$arSection = $rsSection->GetNext()) {
        echo json_encode([
            'success' => false,
            'message' => 'Раздел не найден'
        ]);
        die();
    }

    // Используем код из базы, если он отличается
    if ($arSection['CODE'] !== $sectionCode) {
        $sectionCode = $arSection['CODE'];
        $sectionName = $arSection['NAME'];
    }

    try {
        $parser = new OldSiteParser();

        $links = $parser->collectProductLinks($sectionCode);

        if (empty($links)) {
            $errors = $parser->getErrors();
            $errorMessage = !empty($errors) ? implode('; ', $errors) : 'Товары не найдены';

            echo json_encode([
                'success' => false,
                'message' => $errorMessage
            ]);
            die();
        }

        $filePath = $parser->saveToXml($links, $sectionName, $sectionId, $sectionCode);

        if (!$filePath) {
            $errors = $parser->getErrors();
            $errorMessage = !empty($errors) ? implode('; ', $errors) : 'Ошибка при сохранении файла';

            echo json_encode([
                'success' => false,
                'message' => $errorMessage
            ]);
            die();
        }

        echo json_encode([
            'success' => true,
            'message' => sprintf(
                'Импорт завершен успешно. Найдено товаров: %d. Файл сохранен: %s',
                count($links),
                $filePath
            ),
            'file_path' => $filePath,
            'total' => count($links),
            'links' => $links
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Ошибка при выполнении импорта: ' . $e->getMessage()
        ]);
    }
} elseif ($action === 'import_products') {
    // Импорт товаров из XML (пакетами)
    $sectionId = intval($_POST['section_id'] ?? 0);
    $offset = intval($_POST['offset'] ?? 0);

    if (!$sectionId) {
        echo json_encode([
            'success' => false,
            'message' => 'Не указан раздел для импорта товаров'
        ]);
        die();
    }

    try {
        $parser = new OldSiteParser();
        $result = $parser->importProductsFromXml($sectionId, $offset, 10, 11);

        if (!$result['success']) {
            $result['parser_errors'] = $parser->getErrors();
            echo json_encode($result);
            die();
        }

        $result['parser_errors'] = $parser->getErrors();
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Ошибка при импорте товаров: ' . $e->getMessage()
        ]);
    }
} elseif ($action === 'import_products_simple') {
    // Упрощенный импорт товаров: только создание новых и обновление привязки к разделу
    $sectionId = intval($_POST['section_id'] ?? 0);
    $offset = intval($_POST['offset'] ?? 0);

    if (!$sectionId) {
        echo json_encode([
            'success' => false,
            'message' => 'Не указан раздел для упрощенного импорта товаров'
        ]);
        die();
    }

    try {
        $parser = new OldSiteParser();
        $result = $parser->importProductsFromXml($sectionId, $offset, 10, 11, 'simple');

        if (!$result['success']) {
            $result['parser_errors'] = $parser->getErrors();
            echo json_encode($result);
            die();
        }

        $result['parser_errors'] = $parser->getErrors();
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Ошибка при упрощенном импорте товаров: ' . $e->getMessage()
        ]);
    }
} else {
    if ($action === 'list_properties') {
        $iblockId = intval($_POST['iblock_id'] ?? 0);
        if ($iblockId <= 0) {
            $iblockId = 11;
        }

        $props = [];
        $res = CIBlockProperty::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            ['IBLOCK_ID' => $iblockId]
        );
        while ($p = $res->Fetch()) {
            $props[] = [
                'ID' => (int)$p['ID'],
                'IBLOCK_ID' => (int)$p['IBLOCK_ID'],
                'NAME' => (string)$p['NAME'],
                'CODE' => (string)$p['CODE'],
                'PROPERTY_TYPE' => (string)$p['PROPERTY_TYPE'],
                'MULTIPLE' => (string)$p['MULTIPLE'],
                'LINK_IBLOCK_ID' => isset($p['LINK_IBLOCK_ID']) ? (int)$p['LINK_IBLOCK_ID'] : 0,
            ];
        }

        echo json_encode([
            'success' => true,
            'iblock_id' => $iblockId,
            'count' => count($props),
            'properties' => $props,
        ]);
        die();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Неизвестное действие'
    ]);
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
