<?php

namespace App\Sitemap;

/**
 * Автогенерируемый sitemap.xml (sitemapindex + отдельные файлы по типам).
 * Требования: PRODUCT_IMPORT... нет, см. "Технические требования к
 * автогенерируемому sitemap.xml для prof-equip.ru.md" (докладка заказчика).
 *
 * Файлы пишутся прямо в DOCUMENT_ROOT (sitemap.xml отдаётся статикой,
 * без обращения к ядру Bitrix на каждый запрос бота) через tmp-файл +
 * rename() — атомарная замена, бот никогда не увидит наполовину
 * записанный XML.
 *
 * Список статических страниц (STATIC_PAGES) — вручную поддерживаемый
 * реестр, как urlrewrite.php или .header.menu.php в корне сайта: для
 * произвольных корпоративных страниц без записи в инфоблоке нет дешёвого
 * способа программно проверить noindex/активность, поэтому при
 * добавлении/удалении такой страницы список правится here же.
 */
class SitemapGenerator
{
    private const MAX_URLS_PER_FILE = 50000;

    private const CATALOG_IBLOCK_ID = 11;

    /** IBLOCK_ID => [URL-префикс, проверять ли активность родительской секции] */
    private const ELEMENT_SOURCES = [
        'sitemap-products' => [self::CATALOG_IBLOCK_ID, '/product/', true],
        'sitemap-brands' => [14, '/brends/', false],
        'sitemap-blog' => [23, '/blog/', false],
        'sitemap-news' => [4, '/novosti/', false],
        'sitemap-projects' => [8, '/portfolio/', false],
    ];

    /** URL => путь к файлу относительно DOCUMENT_ROOT, по которому берём lastmod (mtime) */
    private const STATIC_PAGES = [
        '/' => 'index.php',
        '/o-kompanii/' => 'o-kompanii/index.php',
        '/kuhnya/' => 'kuhnya/index.php',
        '/mebel/' => 'mebel/index.php',
        '/tekstil/' => 'tekstil/index.php',
        '/himiya/' => 'himiya/index.php',
        '/prachechnaya/' => 'prachechnaya/index.php',
        '/kompleksnoe-osnashhenie-otelej/' => 'kompleksnoe-osnashhenie-otelej/index.php',
        '/services/' => 'services/index.php',
        '/portfolio/' => 'portfolio/index.php',
        '/gotovye-resheniya/' => 'gotovye-resheniya/index.php',
        '/brends/' => 'brends/index.php',
        '/novosti/' => 'novosti/index.php',
        '/blog/' => 'blog/index.php',
        '/politika-konfidentsialnosti/' => 'politika-konfidentsialnosti/index.php',
        '/partneram/' => 'partneram/index.php',
        '/kontakty/' => 'kontakty/index.php',
    ];

    /**
     * Агентная точка входа (регистрируется миграцией
     * 2026-09-04-sitemap-agent.php). Ошибки не пробрасываются наружу —
     * агент обязан вернуть строку для переустановки себя в очередь, иначе
     * Bitrix снимет его с выполнения при первом же сбое генерации.
     */
    public static function agentGenerate(): string
    {
        try {
            $stats = self::generateAll();
            \CEventLog::Add([
                'SEVERITY' => 'INFO',
                'AUDIT_TYPE_ID' => 'SITEMAP_GENERATE',
                'MODULE_ID' => 'main',
                'ITEM_ID' => 'sitemap',
                'DESCRIPTION' => 'Sitemap перегенерирован: ' . json_encode($stats, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $e) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'SITEMAP_GENERATE',
                'MODULE_ID' => 'main',
                'ITEM_ID' => 'sitemap',
                'DESCRIPTION' => 'Ошибка генерации sitemap: ' . $e->getMessage(),
            ]);
        }

        return '\\App\\Sitemap\\SitemapGenerator::agentGenerate();';
    }

    /**
     * Полная перегенерация всех файлов sitemap. Возвращает статистику
     * (тип => количество URL) для лога/CLI-вывода.
     */
    public static function generateAll(): array
    {
        \CModule::IncludeModule('iblock');

        $base = self::baseUrl();
        $stats = [];
        $indexFiles = [];

        $pages = self::generateStaticPages($base);
        $stats['pages'] = count($pages);
        $indexFiles = array_merge($indexFiles, self::writeUrlset('sitemap-pages', $pages));

        [$categoryUrls, $sectionMap] = self::generateCategories($base);
        $categoryUrls = array_merge($categoryUrls, self::generateSeoFilterPages($base, $sectionMap));
        $stats['categories'] = count($categoryUrls);
        $indexFiles = array_merge($indexFiles, self::writeUrlset('sitemap-categories', $categoryUrls));

        foreach (self::ELEMENT_SOURCES as $baseName => [$iblockId, $urlPrefix, $checkSectionActive]) {
            $urls = self::generateElements($base, $iblockId, $urlPrefix, $checkSectionActive);
            $statKey = substr($baseName, strlen('sitemap-'));
            $stats[$statKey] = count($urls);
            $indexFiles = array_merge($indexFiles, self::writeUrlset($baseName, $urls));
        }

        $documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        self::atomicWrite($documentRoot . '/sitemap.xml', self::buildSitemapIndexXml($indexFiles));

        return $stats;
    }

    private static function baseUrl(): string
    {
        $site = \CSite::GetByID('s1')->Fetch();
        $host = !empty($site['SERVER_NAME']) ? $site['SERVER_NAME'] : $_SERVER['SERVER_NAME'];

        return 'https://' . $host;
    }

    private static function generateStaticPages(string $base): array
    {
        $documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        $urls = [];

        foreach (self::STATIC_PAGES as $path => $relFile) {
            $fullFile = $documentRoot . '/' . $relFile;
            if (!is_file($fullFile)) {
                continue;
            }

            $urls[] = [
                'loc' => $base . $path,
                'lastmod' => date('Y-m-d', filemtime($fullFile)),
            ];
        }

        return $urls;
    }

    /**
     * @return array{0: array, 1: array<string, array{ID: int}>} [URL-список, CODE раздела => данные раздела]
     */
    private static function generateCategories(string $base): array
    {
        global $DB;

        $rs = $DB->Query(
            'SELECT ID, CODE, TIMESTAMP_X FROM b_iblock_section
             WHERE IBLOCK_ID = ' . self::CATALOG_IBLOCK_ID . "
               AND GLOBAL_ACTIVE = 'Y' AND CODE IS NOT NULL AND CODE != ''"
        );

        $urls = [];
        $sectionMap = [];

        while ($row = $rs->Fetch()) {
            $urls[] = [
                'loc' => $base . '/product-category/' . rawurlencode($row['CODE']) . '/',
                'lastmod' => date('Y-m-d', strtotime($row['TIMESTAMP_X'])),
            ];
            $sectionMap[$row['CODE']] = ['ID' => (int)$row['ID']];
        }

        return [$urls, $sectionMap];
    }

    /**
     * SEO-значимые страницы умного фильтра — по правилам из инфоблока
     * "seofilterrules" (см. local/migrations/2026-09-02-seofilterrules-iblock.php
     * и local/templates/profequip/components/bitrix/catalog/catalog/section.php).
     * Правило CODE = "<код раздела>__<код свойства в нижнем регистре>" разрешает
     * индексацию ЛЮБОГО значения этого свойства в этом разделе — в sitemap
     * попадают только те значения, которые реально встречаются хотя бы у одного
     * активного товара (не все теоретически возможные комбинации).
     */
    private static function generateSeoFilterPages(string $base, array $sectionMap): array
    {
        global $DB;

        $seoIblock = \CIBlock::GetList([], ['CODE' => 'seofilterrules'])->Fetch();
        if (!$seoIblock) {
            return [];
        }

        $rs = $DB->Query(
            'SELECT CODE FROM b_iblock_element WHERE IBLOCK_ID = ' . (int)$seoIblock['ID'] . " AND ACTIVE = 'Y'"
        );

        $urls = [];

        while ($row = $rs->Fetch()) {
            $parts = explode('__', (string)$row['CODE'], 2);
            if (count($parts) !== 2) {
                continue;
            }

            [$sectionCode, $propertyCodeLower] = $parts;
            if (!isset($sectionMap[$sectionCode])) {
                continue; // раздел деактивирован/удалён — правило больше не действует
            }

            $sectionId = $sectionMap[$sectionCode]['ID'];

            $propRow = $DB->Query(
                'SELECT ID FROM b_iblock_property WHERE IBLOCK_ID = ' . self::CATALOG_IBLOCK_ID . "
                 AND UPPER(CODE) = '" . $DB->ForSql(strtoupper($propertyCodeLower)) . "'"
            )->Fetch();
            if (!$propRow) {
                continue;
            }

            $rsEnum = $DB->Query(
                'SELECT pe.XML_ID, MAX(e.TIMESTAMP_X) AS LASTMOD
                 FROM b_iblock_element_property ep
                 INNER JOIN b_iblock_property_enum pe ON pe.ID = ep.VALUE_ENUM
                 INNER JOIN b_iblock_element e ON e.ID = ep.IBLOCK_ELEMENT_ID
                 WHERE ep.IBLOCK_PROPERTY_ID = ' . (int)$propRow['ID'] . "
                   AND e.ACTIVE = 'Y'
                   AND e.IBLOCK_SECTION_ID = " . (int)$sectionId . '
                   AND pe.XML_ID IS NOT NULL AND pe.XML_ID != \'\'
                 GROUP BY pe.XML_ID'
            );

            while ($enumRow = $rsEnum->Fetch()) {
                $urls[] = [
                    'loc' => $base . '/product-category/' . rawurlencode($sectionCode) . '/f/'
                        . rawurlencode(strtolower($propertyCodeLower)) . '-is-' . rawurlencode($enumRow['XML_ID']) . '/',
                    'lastmod' => date('Y-m-d', strtotime($enumRow['LASTMOD'])),
                ];
            }
        }

        return $urls;
    }

    /**
     * Активные элементы простого инфоблока (товары/бренды/статьи/новости/проекты).
     * $checkSectionActive — для каталога: товар в деактивированной ветке (мягкий 404,
     * см. fix/empty-category-soft-404) реально недоступен и не должен попасть в sitemap.
     */
    private static function generateElements(string $base, int $iblockId, string $urlPrefix, bool $checkSectionActive): array
    {
        global $DB;

        $sql = 'SELECT e.CODE, e.TIMESTAMP_X FROM b_iblock_element e ';
        if ($checkSectionActive) {
            $sql .= 'LEFT JOIN b_iblock_section s ON s.ID = e.IBLOCK_SECTION_ID ';
        }
        $sql .= 'WHERE e.IBLOCK_ID = ' . (int)$iblockId . " AND e.ACTIVE = 'Y' AND e.CODE IS NOT NULL AND e.CODE != ''";
        if ($checkSectionActive) {
            $sql .= " AND (e.IBLOCK_SECTION_ID IS NULL OR s.GLOBAL_ACTIVE = 'Y')";
        }

        $rs = $DB->Query($sql);
        $urls = [];

        while ($row = $rs->Fetch()) {
            $urls[] = [
                'loc' => $base . $urlPrefix . rawurlencode($row['CODE']) . '/',
                'lastmod' => date('Y-m-d', strtotime($row['TIMESTAMP_X'])),
            ];
        }

        return $urls;
    }

    /**
     * Пишет $urls в $baseName.xml, разбивая на $baseName-1.xml, $baseName-2.xml...
     * при превышении MAX_URLS_PER_FILE. Удаляет файлы предыдущего запуска, которые
     * в этот раз не понадобились (например, каталог сократился до одного файла).
     *
     * @return string[] имена реально записанных файлов (для sitemap.xml)
     */
    private static function writeUrlset(string $baseName, array $urls): array
    {
        $documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
        $chunks = $urls ? array_chunk($urls, self::MAX_URLS_PER_FILE) : [[]];
        $multiFile = count($chunks) > 1;
        $writtenFiles = [];

        foreach ($chunks as $i => $chunkUrls) {
            $filename = $baseName . ($multiFile ? '-' . ($i + 1) : '') . '.xml';
            self::atomicWrite($documentRoot . '/' . $filename, self::buildUrlsetXml($chunkUrls));
            $writtenFiles[] = $filename;
        }

        $stale = glob($documentRoot . '/' . $baseName . '*.xml') ?: [];
        foreach ($stale as $stalePath) {
            $bn = basename($stalePath);
            if (!in_array($bn, $writtenFiles, true) && preg_match('#^' . preg_quote($baseName, '#') . '(-\d+)?\.xml$#', $bn)) {
                @unlink($stalePath);
            }
        }

        return $writtenFiles;
    }

    private static function buildUrlsetXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $u) {
            $loc = htmlspecialchars($u['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $xml .= "    <url>\n        <loc>{$loc}</loc>\n";
            if (!empty($u['lastmod'])) {
                $xml .= "        <lastmod>{$u['lastmod']}</lastmod>\n";
            }
            $xml .= "    </url>\n";
        }

        $xml .= '</urlset>' . "\n";

        return $xml;
    }

    private static function buildSitemapIndexXml(array $files): string
    {
        $today = date('Y-m-d');
        $base = self::baseUrl();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($files as $f) {
            $loc = htmlspecialchars($base . '/' . $f, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $xml .= "    <sitemap>\n        <loc>{$loc}</loc>\n        <lastmod>{$today}</lastmod>\n    </sitemap>\n";
        }

        $xml .= '</sitemapindex>' . "\n";

        return $xml;
    }

    private static function atomicWrite(string $path, string $content): void
    {
        $tmpPath = $path . '.tmp';

        if (file_put_contents($tmpPath, $content, LOCK_EX) === false) {
            throw new \RuntimeException("Не удалось записать временный файл {$tmpPath}");
        }

        if (!rename($tmpPath, $path)) {
            @unlink($tmpPath);
            throw new \RuntimeException("Не удалось переименовать {$tmpPath} в {$path}");
        }

        @chmod($path, 0644);
    }
}
