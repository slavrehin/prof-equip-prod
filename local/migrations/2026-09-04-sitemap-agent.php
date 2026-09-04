<?php
/**
 * Регистрирует агент периодической перегенерации sitemap.xml
 * (App\Sitemap\SitemapGenerator::agentGenerate(), см. local/app/Sitemap/).
 *
 * Каждые 15 минут (агент выполняется на реальном хите к сайту, когда подошло
 * время NEXT_EXEC — на проде с живым трафиком этого достаточно; на test3 для
 * немедленной проверки использовать local/deploy/generate_sitemap.php).
 */

$agentName = '\App\Sitemap\SitemapGenerator::agentGenerate();';

$existing = \CAgent::GetList([], ['NAME' => $agentName])->Fetch();

if ($existing) {
    echo "Агент генерации sitemap уже зарегистрирован (ID={$existing['ID']}), пропускаю.\n";
} else {
    $agentId = \CAgent::AddAgent(
        $agentName,
        'main',
        'N',
        900,
        '',
        'Y',
        date('d.m.Y H:i:s')
    );

    if (!$agentId) {
        throw new \RuntimeException('Не удалось зарегистрировать агент генерации sitemap');
    }

    $check = \CAgent::GetByID($agentId)->Fetch();
    if (!$check) {
        throw new \RuntimeException('Агент генерации sitemap не найден в базе после AddAgent() (ID=' . $agentId . ')');
    }

    echo "Зарегистрирован агент генерации sitemap (ID=$agentId, каждые 900 сек).\n";
}
