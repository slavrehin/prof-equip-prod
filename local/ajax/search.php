<?php
/**
 * Живые подсказки поиска в шапке: товары, категории, услуги.
 * GET ?q=... → JSON (см. ProfEquipSearch::suggest()).
 */
define('NO_KEEP_STATISTIC', true);
define('STOP_STATISTICS', true);
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

\Bitrix\Main\Loader::includeModule('iblock');

$query = trim((string)($_GET['q'] ?? ''));
$query = mb_substr($query, 0, 100);

$result = mb_strlen($query) >= 2
    ? ProfEquipSearch::suggest($query)
    : ['query' => $query, 'total' => 0, 'products' => [], 'sections' => [], 'services' => ProfEquipSearch::suggestServices(''), 'allUrl' => ''];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
