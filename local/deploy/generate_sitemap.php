<?php
/**
 * Ручной запуск генерации sitemap.xml — для проверки на test3 и на случай,
 * если нужно обновить карту сайта немедленно, не дожидаясь агента
 * (App\Sitemap\SitemapGenerator::agentGenerate(), см. local/app/Sitemap/
 * и local/migrations/2026-09-04-sitemap-agent.php).
 *
 * Запуск:
 *   docker compose exec web php /var/www/html/local/deploy/generate_sitemap.php   # test3
 *   php local/deploy/generate_sitemap.php                                          # прод
 */

$_SERVER['DOCUMENT_ROOT'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2);
$_SERVER['SERVER_NAME'] = ($_SERVER['SERVER_NAME'] ?? '') ?: 'prof-equip.ru';
$_SERVER['REQUEST_METHOD'] = ($_SERVER['REQUEST_METHOD'] ?? '') ?: 'GET';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

CModule::IncludeModule('iblock');

$stats = \App\Sitemap\SitemapGenerator::generateAll();

echo "Sitemap сгенерирован:\n";
foreach ($stats as $type => $count) {
    echo "  {$type}: {$count} URL\n";
}
