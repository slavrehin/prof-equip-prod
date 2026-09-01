<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

// Проверка прав доступа - только для администраторов
global $USER;
if (php_sapi_name() !== 'cli' && !$USER->IsAdmin()) {
    die('Access denied');
}

use Bitrix\Main\Loader;

/**
 * Класс для парсинга страниц старого сайта
 */
class OldSiteParser
{
    private $baseUrl = 'https://prof-equip.ru';
    private $productLinks = [];
    private $errors = [];

    private const DEFAULT_BRAND_PROPERTY_CODE = 'BRAND';
    private const DEFAULT_BRAND_IBLOCK_ID = 14;

    /** @var array<int,array<string,array{id:int,value:string,sort:int}>> */
    private $enumCacheByPropertyId = [];
    
    /**
     * Проверяет существование страницы по URL
     * 
     * @param string $url URL для проверки
     * @return bool
     */
    public function checkPageExists($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode == 200;
    }
    
    /**
     * Получает содержимое страницы
     * 
     * @param string $url URL страницы
     * @return string|false HTML содержимое или false при ошибке
     */
    private function getPageContent($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->errors[] = "Ошибка CURL для $url: $error";
            return false;
        }
        
        if ($httpCode != 200) {
            $this->errors[] = "HTTP код $httpCode для $url";
            return false;
        }
        
        return $content;
    }
    
    /**
     * Извлекает ссылки на товары со страницы каталога
     * 
     * @param string $html HTML содержимое страницы
     * @return array Массив ссылок на товары
     */
    private function extractProductLinks($html)
    {
        $links = [];
        
        // Создаем DOMDocument для парсинга
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Ищем ссылки на товары различными способами
        
        // Вариант 1: Ищем ссылки внутри контейнеров товаров (WooCommerce структура)
        $productContainers = $xpath->query("
            //div[contains(@class, 'product')]//a[@href] | 
            //div[contains(@class, 'item')]//a[@href] | 
            //article[contains(@class, 'product')]//a[@href] | 
            //li[contains(@class, 'product')]//a[@href] |
            //div[contains(@class, 'woocommerce')]//a[contains(@class, 'woocommerce-LoopProduct-link')] |
            //ul[contains(@class, 'products')]//a[@href] |
            //div[contains(@class, 'products')]//a[@href] |
            //div[contains(@class, 'type-product')]//a[@href] |
            //li[contains(@class, 'type-product')]//a[@href] |
            //article[contains(@class, 'type-product')]//a[@href]
        ");
        
        foreach ($productContainers as $link) {
            $href = $link->getAttribute('href');
            if ($href && $this->isProductLink($href)) {
                $fullUrl = $this->normalizeUrl($href);
                if ($fullUrl && !in_array($fullUrl, $links)) {
                    $links[] = $fullUrl;
                }
            }
        }
        
        // Вариант 2: Ищем заголовки товаров (обычно они обернуты в ссылки)
        if (empty($links)) {
            $productTitles = $xpath->query("
                //h2[contains(@class, 'product')]//a[@href] |
                //h3[contains(@class, 'product')]//a[@href] |
                //h2[contains(@class, 'woocommerce-loop-product__title')]//a[@href] |
                //h2[contains(@class, 'entry-title')]//a[@href] |
                //h3[contains(@class, 'entry-title')]//a[@href]
            ");
            
            foreach ($productTitles as $link) {
                $href = $link->getAttribute('href');
                if ($href && $this->isProductLink($href)) {
                    $fullUrl = $this->normalizeUrl($href);
                    if ($fullUrl && !in_array($fullUrl, $links)) {
                        $links[] = $fullUrl;
                    }
                }
            }
        }
        
        // Вариант 2.5: Ищем ссылки в карточках товаров (более общий поиск)
        if (empty($links)) {
            // Ищем все ссылки внутри элементов, которые могут быть карточками товаров
            $productCards = $xpath->query("
                //div[contains(@class, 'product')]//a[@href] |
                //li[contains(@class, 'product')]//a[@href] |
                //article//a[@href] |
                //div[contains(@class, 'entry')]//a[@href]
            ");
            
            foreach ($productCards as $link) {
                $href = $link->getAttribute('href');
                $linkText = trim($link->textContent);
                
                // Пропускаем ссылки с коротким текстом (вероятно не товары)
                if (strlen($linkText) < 5) {
                    continue;
                }
                
                if ($href && $this->isProductLink($href)) {
                    $fullUrl = $this->normalizeUrl($href);
                    if ($fullUrl && !in_array($fullUrl, $links)) {
                        $links[] = $fullUrl;
                    }
                }
            }
        }
        
        // Вариант 3: Ищем все ссылки в основном контенте и фильтруем
        if (empty($links)) {
            // Ищем ссылки в основном контенте страницы
            $mainContent = $xpath->query("//main//a[@href] | //div[contains(@class, 'content')]//a[@href] | //div[contains(@class, 'main')]//a[@href]");
            
            foreach ($mainContent as $link) {
                $href = $link->getAttribute('href');
                if ($href && $this->isProductLink($href)) {
                    $fullUrl = $this->normalizeUrl($href);
                    if ($fullUrl && !in_array($fullUrl, $links)) {
                        $links[] = $fullUrl;
                    }
                }
            }
        }
        
        // Вариант 4: Если все еще не нашли, ищем все ссылки и фильтруем более строго
        if (empty($links)) {
            $allLinks = $xpath->query("//a[@href]");
            foreach ($allLinks as $link) {
                $href = $link->getAttribute('href');
                $linkText = trim($link->textContent);
                
                // Пропускаем пустые ссылки и ссылки с коротким текстом
                if (strlen($linkText) < 3) {
                    continue;
                }
                
                if ($href && $this->isProductLink($href)) {
                    $fullUrl = $this->normalizeUrl($href);
                    if ($fullUrl && !in_array($fullUrl, $links)) {
                        $links[] = $fullUrl;
                    }
                }
            }
        }
        
        return $links;
    }
    
    /**
     * Проверяет, является ли ссылка ссылкой на товар
     * 
     * @param string $url URL для проверки
     * @return bool
     */
    private function isProductLink($url)
    {
        // Исключаем служебные ссылки
        $excludePatterns = [
            '/product-category/',
            '/category/',
            '/catalog/',
            '/wp-admin/',
            '/wp-content/',
            '/wp-includes/',
            '/feed/',
            '/rss/',
            '/sitemap/',
            '/tag/',
            '/author/',
            '/page/',
            '/#',
            'javascript:',
            'mailto:',
            'tel:',
            '.jpg',
            '.png',
            '.gif',
            '.pdf',
            '.zip',
            '.css',
            '.js',
            '/cart/',
            '/checkout/',
            '/my-account/',
            '/shop/',
            '/wp-json/',
            '/wp-login.php',
            '/search',
            '/?',
            'add-to-cart',
            'replytocom',
            'comment-page'
        ];
        
        foreach ($excludePatterns as $pattern) {
            if (stripos($url, $pattern) !== false) {
                return false;
            }
        }
        
        // Проверяем, что это не главная страница или раздел каталога
        if (preg_match('#^/product-category/[^/]+/?$#', $url)) {
            return false;
        }
        
        // Проверяем, что это не якорь или пустая ссылка
        if (empty($url) || $url === '/' || $url === '#') {
            return false;
        }
        
        // Проверяем, что ссылка выглядит как ссылка на товар
        // Товары обычно имеют структуру типа /название-товара/ или содержат название товара в URL
        // Исключаем слишком короткие URL (меньше 5 символов без домена)
        $urlPath = parse_url($url, PHP_URL_PATH);
        if ($urlPath && strlen($urlPath) < 5) {
            return false;
        }
        
        // Принимаем ссылки, которые:
        // 1. Имеют структуру /название-товара/ (не начинаются с product-category)
        // 2. Содержат несколько сегментов пути
        // 3. Не являются служебными страницами
        
        // Проверяем структуру URL - должен быть хотя бы один сегмент после первого слеша
        if (preg_match('#^/[^/]+/[^/]+#', $urlPath)) {
            return true;
        }
        
        // Или если это один сегмент, но достаточно длинный (вероятно товар)
        if (preg_match('#^/[^/]+$#', $urlPath) && strlen($urlPath) > 10) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Нормализует URL (делает абсолютным)
     * 
     * @param string $url Относительный или абсолютный URL
     * @return string|false Абсолютный URL или false
     */
    private function normalizeUrl($url)
    {
        // Если уже абсолютный URL
        if (preg_match('#^https?://#', $url)) {
            // Проверяем, что это наш домен
            if (strpos($url, $this->baseUrl) === 0) {
                return $url;
            }
            return false;
        }
        
        // Если относительный URL
        if (strpos($url, '/') === 0) {
            return $this->baseUrl . $url;
        }
        
        return false;
    }
    
    /**
     * Получает количество страниц пагинации
     * 
     * @param string $html HTML содержимое страницы
     * @return int Количество страниц
     */
    private function getTotalPages($html)
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Ищем пагинацию различными способами
        
        // Вариант 1: Ищем ссылки на страницы в пагинации (WooCommerce)
        $pageLinks = $xpath->query("
            //nav[contains(@class, 'pagination')]//a | 
            //div[contains(@class, 'pagination')]//a | 
            //ul[contains(@class, 'page-numbers')]//a |
            //ul[contains(@class, 'pagination')]//a |
            //div[contains(@class, 'woocommerce-pagination')]//a |
            //nav[contains(@class, 'woocommerce-pagination')]//a
        ");
        
        $maxPage = 1;
        $foundPages = [];
        
        foreach ($pageLinks as $link) {
            $href = $link->getAttribute('href');
            $text = trim($link->textContent);
            
            // Пытаемся извлечь номер страницы из ссылки
            if (preg_match('/page[=\/](\d+)/i', $href, $matches)) {
                $pageNum = (int)$matches[1];
                $foundPages[] = $pageNum;
                if ($pageNum > $maxPage) {
                    $maxPage = $pageNum;
                }
            }
            
            // Пытаемся извлечь номер страницы из текста ссылки
            if (preg_match('/^(\d+)$/', $text, $matches)) {
                $pageNum = (int)$matches[1];
                $foundPages[] = $pageNum;
                if ($pageNum > $maxPage) {
                    $maxPage = $pageNum;
                }
            }
        }
        
        // Вариант 2: Ищем текст "Страница X из Y" или "Page X of Y"
        $pagerText = $xpath->query("//*[contains(text(), 'Страница') or contains(text(), 'Page') or contains(text(), 'из')]");
        foreach ($pagerText as $element) {
            $text = $element->textContent;
            if (preg_match('/(\d+)\s*(?:из|of|of)\s*(\d+)/i', $text, $matches)) {
                $totalPages = (int)$matches[2];
                if ($totalPages > $maxPage) {
                    $maxPage = $totalPages;
                }
            }
        }
        
        // Вариант 3: Ищем информацию о пагинации в тексте (например, "Отображение 1–16 из 584")
        $pagerInfo = $xpath->query("//*[contains(text(), 'Отображение') or contains(text(), 'Showing')]");
        foreach ($pagerInfo as $element) {
            $text = $element->textContent;
            // Ищем паттерн типа "1–16 из 584" или "1-16 of 584"
            if (preg_match('/\d+[–-]\d+\s*(?:из|of)\s*(\d+)/i', $text, $matches)) {
                $totalItems = (int)$matches[1];
                // Извлекаем количество товаров на странице из текста
                if (preg_match('/(\d+)[–-](\d+)/', $text, $rangeMatches)) {
                    $itemsPerPage = (int)$rangeMatches[2] - (int)$rangeMatches[1] + 1;
                } else {
                    $itemsPerPage = 16; // По умолчанию
                }
                $calculatedPages = ceil($totalItems / $itemsPerPage);
                if ($calculatedPages > $maxPage) {
                    $maxPage = $calculatedPages;
                }
            }
        }
        
        // Вариант 4: Ищем информацию о пагинации в атрибутах data-*
        $pagerElements = $xpath->query("//*[@data-total-pages or @data-total]");
        foreach ($pagerElements as $element) {
            $totalPagesAttr = $element->getAttribute('data-total-pages');
            $totalAttr = $element->getAttribute('data-total');
            
            if ($totalPagesAttr) {
                $totalPages = (int)$totalPagesAttr;
                if ($totalPages > $maxPage) {
                    $maxPage = $totalPages;
                }
            } elseif ($totalAttr) {
                $totalItems = (int)$totalAttr;
                $itemsPerPage = 16; // Предполагаем стандартное значение
                $calculatedPages = ceil($totalItems / $itemsPerPage);
                if ($calculatedPages > $maxPage) {
                    $maxPage = $calculatedPages;
                }
            }
        }
        
        // Если нашли несколько страниц, но не нашли максимальную, проверяем наличие ссылки "Следующая"
        $nextLink = $xpath->query("//a[contains(@class, 'next') or contains(text(), 'Следующая') or contains(text(), 'Next')]");
        if ($nextLink->length > 0 && $maxPage == 1) {
            // Если есть ссылка "Следующая", значит есть как минимум 2 страницы
            $maxPage = 2;
        }
        
        // Дополнительная проверка: если нашли ссылки на страницы, но maxPage = 1,
        // проверяем, есть ли ссылки на страницы 2, 3 и т.д. в URL
        if ($maxPage == 1 && !empty($foundPages)) {
            $maxPage = max($foundPages);
        }
        
        return max(1, $maxPage); // Минимум 1 страница
    }
    
    /**
     * Собирает все ссылки на товары из раздела каталога
     * 
     * @param string $sectionCode Символьный код раздела
     * @return array Массив ссылок на товары
     */
    public function collectProductLinks($sectionCode)
    {
        $this->productLinks = [];
        $this->errors = [];
        
        $categoryUrl = $this->baseUrl . '/product-category/' . $sectionCode . '/';
        
        // Проверяем существование страницы
        if (!$this->checkPageExists($categoryUrl)) {
            $this->errors[] = "Страница не существует: $categoryUrl";
            return [];
        }
        
        // Получаем первую страницу
        $html = $this->getPageContent($categoryUrl);
        if (!$html) {
            return [];
        }
        
        // Извлекаем ссылки с первой страницы
        $links = $this->extractProductLinks($html);
        $this->productLinks = array_merge($this->productLinks, $links);
        
        // Определяем количество страниц
        $totalPages = $this->getTotalPages($html);
        
        // Ограничиваем максимальное количество страниц для предотвращения таймаута
        $maxPages = 50; // Максимум 50 страниц за один запрос
        if ($totalPages > $maxPages) {
            $this->errors[] = "Обнаружено слишком много страниц ($totalPages). Обработано будет максимум $maxPages страниц.";
            $totalPages = $maxPages;
        }
        
        // Обрабатываем остальные страницы
        for ($page = 2; $page <= $totalPages; $page++) {
            // Проверяем лимит времени выполнения
            if (isset($_SERVER['REQUEST_TIME']) && function_exists('ini_get') && ini_get('max_execution_time') > 0) {
                $elapsed = time() - $_SERVER['REQUEST_TIME'];
                $maxTime = ini_get('max_execution_time') - 30; // Оставляем запас 30 секунд
                if ($elapsed > $maxTime) {
                    $this->errors[] = "Превышен лимит времени выполнения. Обработано страниц: " . ($page - 1);
                    break;
                }
            }
            
            $pageUrl = $categoryUrl . 'page/' . $page . '/';
            
            // Проверяем существование страницы
            if (!$this->checkPageExists($pageUrl)) {
                break; // Если страницы нет, прекращаем
            }
            
            $pageHtml = $this->getPageContent($pageUrl);
            if ($pageHtml) {
                $pageLinks = $this->extractProductLinks($pageHtml);
                $this->productLinks = array_merge($this->productLinks, $pageLinks);
            } else {
                // Если не удалось получить страницу, пропускаем её
                continue;
            }
            
            // Небольшая задержка, чтобы не перегружать сервер
            usleep(200000); // 0.2 секунды
        }
        
        // Удаляем дубликаты
        $this->productLinks = array_unique($this->productLinks);
        
        return $this->productLinks;
    }
    
    /**
     * Сохраняет ссылки в XML файл
     * 
     * @param array $links Массив ссылок
     * @param string $sectionName Название раздела
     * @param int $sectionId Идентификатор раздела
     * @param string $sectionCode Символьный код раздела
     * @return string|false Путь к файлу или false при ошибке
     */
    public function saveToXml($links, $sectionName, $sectionId = null, $sectionCode = null)
    {
        // Проверяем, что sectionId передан
        if ($sectionId === null) {
            $this->errors[] = "Не указан идентификатор раздела";
            return false;
        }
        
        $sectionId = (int)$sectionId;
        $dataDir = $_SERVER['DOCUMENT_ROOT'] . '/local/import/data/';
        
        // Создаем директорию data, если её нет
        if (!is_dir($dataDir)) {
            if (!mkdir($dataDir, 0755, true)) {
                $this->errors[] = "Не удалось создать директорию: $dataDir";
                return false;
            }
        }
        
        // Формируем имя файла с ID раздела
        $fileName = 'import_' . $sectionId . '.xml';
        $filePath = $dataDir . $fileName;
        
        // Создаем XML документ
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        $root = $xml->createElement('import');
        $xml->appendChild($root);
        
        // Добавляем информацию о разделе
        $sectionInfo = $xml->createElement('section');
        $sectionInfo->setAttribute('name', htmlspecialchars($sectionName));
        $sectionInfo->setAttribute('date', date('Y-m-d H:i:s'));
        $sectionInfo->setAttribute('total', count($links));
        $sectionInfo->setAttribute('id', $sectionId);
        
        // Добавляем символьный код раздела, если передан
        if ($sectionCode !== null) {
            $sectionInfo->setAttribute('code', htmlspecialchars($sectionCode));
        }
        
        $root->appendChild($sectionInfo);
        
        // Добавляем ссылки
        $products = $xml->createElement('products');
        $root->appendChild($products);
        
        foreach ($links as $index => $link) {
            $product = $xml->createElement('product');
            $product->setAttribute('id', $index + 1);
            $product->setAttribute('url', htmlspecialchars($link));
            $products->appendChild($product);
        }
        
        // Сохраняем файл
        if ($xml->save($filePath)) {
            return '/local/import/data/' . $fileName;
        } else {
            $this->errors[] = "Не удалось сохранить файл: $filePath";
            return false;
        }
    }
    
    /**
     * Получает массив ошибок
     * 
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Импортирует товары из XML файла для указанного раздела
     *
     * @param int $sectionId ID раздела инфоблока
     * @param int $offset Смещение (количество уже обработанных товаров)
     * @param int $limit Количество товаров за один шаг
     * @param int $iblockId ID инфоблока
     * @return array Результат импорта
     */
    public function importProductsFromXml($sectionId, $offset = 0, $limit = 10, $iblockId = 11, $mode = 'full')
    {
        global $USER;

        $sectionId = (int)$sectionId;
        $offset = max(0, (int)$offset);
        $limit = max(1, (int)$limit);
        $iblockId = (int)$iblockId;
        $mode = ($mode === 'simple') ? 'simple' : 'full';

        $dataDir = $_SERVER['DOCUMENT_ROOT'] . '/local/import/data/';
        $filePath = $dataDir . 'import_' . $sectionId . '.xml';

        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'message' => 'XML файл для раздела не найден',
            ];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($filePath);
        libxml_clear_errors();

        if ($xml === false || !isset($xml->products->product)) {
            return [
                'success' => false,
                'message' => 'Некорректная структура XML файла',
            ];
        }

        $products = $xml->products->product;
        $total = count($products);

        if ($total === 0) {
            return [
                'success' => false,
                'message' => 'В XML файле нет товаров',
            ];
        }

        if ($offset >= $total) {
            return [
                'success' => true,
                'message' => 'Импорт уже завершен',
                'total' => $total,
                'processed_in_step' => 0,
                'processed_total' => $total,
                'finished' => true,
                'next_offset' => $total,
            ];
        }

        $end = min($offset + $limit, $total);
        $processedInStep = 0;
        $errors = [];

        for ($i = $offset; $i < $end; $i++) {
            $productNode = $products[$i];
            $url = (string)$productNode['url'];

            if (!$url) {
                $errors[] = 'Пустой URL для товара с индексом ' . $i;
                continue;
            }

            $result = $this->importSingleProduct($url, $sectionId, $iblockId, $mode);
            if (!$result['success']) {
                $errors[] = $result['message'];
                continue;
            }

            $processedInStep++;
        }

        $processedTotal = $offset + $processedInStep;
        $finished = $processedTotal >= $total;
        $nextOffset = $finished ? $total : $processedTotal;

        $response = [
            'success' => true,
            'message' => 'Импорт выполнен',
            'total' => $total,
            'processed_in_step' => $processedInStep,
            'processed_total' => $processedTotal,
            'finished' => $finished,
            'next_offset' => $nextOffset,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return $response;
    }

    /**
     * Импорт одного товара по URL
     *
     * @param string $url URL товара на старом сайте
     * @param int $sectionId ID раздела инфоблока
     * @param int $iblockId ID инфоблока
     * @return array
     */
    private function importSingleProduct($url, $sectionId, $iblockId, $mode = 'full')
    {
        global $USER;

        $data = $this->getProductDataFromUrl($url);
        if (!$data['success']) {
            return $data;
        }

        $name = $data['name'];
        $code = $data['code'];
        $previewText = $data['preview_text'];
        $detailText = $data['detail_text'];
        $imageUrl = $data['image'];
        $attributes = isset($data['attributes']) && is_array($data['attributes']) ? $data['attributes'] : [];

        if (!$code) {
            return [
                'success' => false,
                'message' => 'Не удалось определить символьный код для URL: ' . $url,
            ];
        }

        // Проверяем, существует ли уже элемент с таким CODE
        $existingId = 0;
        $res = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $iblockId,
                '=CODE' => $code,
            ],
            false,
            false,
            ['ID', 'IBLOCK_SECTION_ID']
        );
        if ($arExisting = $res->GetNext()) {
            $existingId = (int)$arExisting['ID'];
        }

        $el = new CIBlockElement();

        $elementId = 0;

        if ($existingId > 0) {
            // Упрощенный режим: только обновляем привязку к разделу, данные товара не трогаем
            if ($mode === 'simple') {
                $sectionIds = [];
                $groupRes = CIBlockElement::GetElementGroups($existingId, true);
                while ($arGroup = $groupRes->Fetch()) {
                    $sectionIds[] = (int)$arGroup['ID'];
                }
                if (!in_array($sectionId, $sectionIds, true)) {
                    $sectionIds[] = $sectionId;
                }

                CIBlockElement::SetElementSection($existingId, $sectionIds);

                return [
                    'success' => true,
                    'message' => 'Элемент уже существовал, обновлена привязка к разделу ID ' . $sectionId,
                    'element_id' => $existingId,
                ];
            }

            // Обновляем существующий элемент (имя, описания, картинки)
            $updateFields = [
                'MODIFIED_BY' => $USER && is_object($USER) ? (int)$USER->GetID() : 1,
            ];

            if ($name) {
                $updateFields['NAME'] = $name;
            }

            if ($previewText !== '') {
                $updateFields['PREVIEW_TEXT'] = $previewText;
                $updateFields['PREVIEW_TEXT_TYPE'] = 'html';
            }

            if ($detailText !== '') {
                $updateFields['DETAIL_TEXT'] = $detailText;
                $updateFields['DETAIL_TEXT_TYPE'] = 'html';
            }

            if ($imageUrl) {
                $fileArray = \CFile::MakeFileArray($imageUrl);
                if ($fileArray) {
                    $updateFields['PREVIEW_PICTURE'] = $fileArray;
                    $updateFields['DETAIL_PICTURE'] = $fileArray;
                }
            }

            if (!empty($updateFields)) {
                if (!$el->Update($existingId, $updateFields)) {
                    return [
                        'success' => false,
                        'message' => 'Ошибка обновления элемента: ' . $el->LAST_ERROR,
                    ];
                }
            }

            // Привязываем к новому разделу (добавляем раздел, не теряя старые)
            $sectionIds = [];
            $groupRes = CIBlockElement::GetElementGroups($existingId, true);
            while ($arGroup = $groupRes->Fetch()) {
                $sectionIds[] = (int)$arGroup['ID'];
            }
            if (!in_array($sectionId, $sectionIds, true)) {
                $sectionIds[] = $sectionId;
            }

            CIBlockElement::SetElementSection($existingId, $sectionIds);

            $elementId = $existingId;
        } else {
            // Создаем новый элемент
            $fields = [
                'MODIFIED_BY' => $USER && is_object($USER) ? (int)$USER->GetID() : 1,
                'IBLOCK_SECTION_ID' => $sectionId,
                'IBLOCK_ID' => $iblockId,
                'ACTIVE' => 'Y',
                'NAME' => $name ?: $code,
                'CODE' => $code,
                'PREVIEW_TEXT' => $previewText,
                'PREVIEW_TEXT_TYPE' => 'html',
                'DETAIL_TEXT' => $detailText,
                'DETAIL_TEXT_TYPE' => 'html',
            ];

            if ($imageUrl) {
                $fileArray = \CFile::MakeFileArray($imageUrl);
                if ($fileArray) {
                    $fields['PREVIEW_PICTURE'] = $fileArray;
                    $fields['DETAIL_PICTURE'] = $fileArray;
                }
            }

            $elementId = $el->Add($fields);
            if (!$elementId) {
                return [
                    'success' => false,
                    'message' => 'Ошибка создания элемента: ' . $el->LAST_ERROR,
                ];
            }
        }

        // Свойства (создаем/дополняем/обновляем и проставляем)
        $propsResult = $this->applyProductProperties($elementId, $iblockId, $attributes);
        if (!$propsResult['success']) {
            return $propsResult;
        }

        return [
            'success' => true,
            'message' => ($existingId > 0 ? 'Элемент обновлен' : 'Создан новый элемент') . ' ID ' . $elementId,
            'element_id' => $elementId,
        ];
    }

    /**
     * Получает данные товара по URL
     *
     * @param string $url
     * @return array
     */
    private function getProductDataFromUrl($url)
    {
        $html = $this->getPageContent($url);
        if ($html === false) {
            return [
                'success' => false,
                'message' => 'Не удалось получить страницу товара: ' . $url,
            ];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Название товара <h1 class="entry-title"></h1>
        $nameNode = $xpath->query("//h1[contains(@class, 'entry-title')]");
        $name = '';
        if ($nameNode->length > 0) {
            $name = trim($nameNode->item(0)->textContent);
        }

        // Краткое описание <div class="woocommerce-product-details__short-description"></div>
        $shortDescNode = $xpath->query("//div[contains(@class, 'woocommerce-product-details__short-description')]");
        $previewText = '';
        if ($shortDescNode->length > 0) {
            $previewText = $this->getInnerHtml($shortDescNode->item(0));
        }

        // Описание внутри id="tab-description"
        $detailNode = $xpath->query("//*[@id='tab-description']");
        $detailText = '';
        if ($detailNode->length > 0) {
            $detailText = $this->getInnerHtml($detailNode->item(0));
        }

        // Изображение: берем первое из галереи
        $imageUrl = '';
        $imgNode = $xpath->query("//div[contains(@class, 'woocommerce-product-gallery__wrapper')]//div[contains(@class, 'woocommerce-product-gallery__image')]//img[1]");
        if ($imgNode->length > 0) {
            $img = $imgNode->item(0);
            $imageUrl = $img->getAttribute('data-large_image');
            if (!$imageUrl) {
                $imageUrl = $img->getAttribute('src');
            }
        }

        // Символьный код товара по URL
        $code = $this->getCodeFromUrl($url);

        // Свойства/атрибуты (таб "Доп. информация")
        $attributes = $this->extractProductAttributes($xpath);

        return [
            'success' => true,
            'name' => $name,
            'code' => $code,
            'preview_text' => $previewText,
            'detail_text' => $detailText,
            'image' => $imageUrl,
            'attributes' => $attributes,
        ];
    }

    /**
     * Извлекает атрибуты товара (доп. информация) в структурированном виде
     *
     * @param \DOMXPath $xpath
     * @return array
     */
    private function extractProductAttributes(\DOMXPath $xpath)
    {
        $attributes = [];

        // Таблица может быть в блоке дополнительных сведений
        $rows = $xpath->query("//table[contains(@class,'woocommerce-product-attributes')]//tr[contains(@class,'woocommerce-product-attributes-item')]");
        if (!$rows || $rows->length === 0) {
            return $attributes;
        }

        foreach ($rows as $row) {
            /** @var \DOMElement $row */
            $labelNode = $xpath->query(".//th[contains(@class,'woocommerce-product-attributes-item__label')]", $row);
            $valueNode = $xpath->query(".//td[contains(@class,'woocommerce-product-attributes-item__value')]", $row);

            $rawLabel = ($labelNode && $labelNode->length > 0) ? trim($labelNode->item(0)->textContent) : '';
            $rawLabel = trim(str_replace(["\n", "\r", "\t"], ' ', $rawLabel));
            $rawLabel = trim($rawLabel);
            $rawLabel = rtrim($rawLabel, ':');

            if ($rawLabel === '' || !$valueNode || $valueNode->length === 0) {
                continue;
            }

            // Собираем значения как список ссылок (a rel=tag), допускается несколько
            $links = $xpath->query(".//a[@href]", $valueNode->item(0));
            if ($links && $links->length > 0) {
                $values = [];
                $attrSlug = '';

                foreach ($links as $a) {
                    /** @var \DOMElement $a */
                    $href = trim($a->getAttribute('href'));
                    $text = trim($a->textContent);
                    if ($href === '' || $text === '') {
                        continue;
                    }

                    $parsed = $this->parseWooAttributeHref($href);
                    if (!$parsed) {
                        continue;
                    }

                    if ($attrSlug === '' && !empty($parsed['attr'])) {
                        $attrSlug = $parsed['attr'];
                    }

                    if (empty($parsed['value'])) {
                        continue;
                    }

                    $values[] = [
                        'text' => $text,
                        'xml_id' => $parsed['value'],
                    ];
                }

                if ($attrSlug === '' || empty($values)) {
                    continue;
                }

                $attributes[] = [
                    'label' => $rawLabel,
                    'type' => 'L',
                    'attr_slug' => $attrSlug,
                    'property_code' => $this->makePropertyCodeFromAttrSlug($attrSlug),
                    'values' => $values,
                    'is_multiple' => count($values) > 1,
                ];
            } else {
                // Бывает значение без ссылки: <p>2800</p>. Тогда делаем строковое свойство
                $valueText = trim($valueNode->item(0)->textContent);
                $valueText = preg_replace('/\s+/', ' ', $valueText);
                $valueText = trim($valueText);

                if ($valueText === '') {
                    continue;
                }

                $propertyCode = $this->makePropertyCodeFromLabelTranslit($rawLabel);
                if ($propertyCode === '') {
                    continue;
                }

                $attributes[] = [
                    'label' => $rawLabel,
                    'type' => 'S',
                    'property_code' => $propertyCode,
                    'value' => $valueText,
                ];
            }
        }

        return $attributes;
    }

    /**
     * Парсит ссылку вида /attribute-name/<attr>/<value>/ и возвращает слаги
     *
     * @param string $href
     * @return array|null
     */
    private function parseWooAttributeHref($href)
    {
        $path = parse_url($href, PHP_URL_PATH);
        if (!$path) {
            return null;
        }

        // Надёжнее парсим через регулярку: /attribute-name/<attr>/<value>/
        if (!preg_match('~/(?:attribute-name)/([^/]+)/([^/]+)/?~', $path, $m)) {
            return null;
        }

        $attr = trim($m[1]);
        $value = trim($m[2]);

        if ($attr === '' || $value === '') {
            return null;
        }

        return [
            'attr' => $attr,
            'value' => $value,
        ];
    }

    /**
     * Код свойства из слага атрибута (tip-nagreva -> TIP_NAGREVA)
     *
     * @param string $attrSlug
     * @return string
     */
    private function makePropertyCodeFromAttrSlug($attrSlug)
    {
        $attrSlug = mb_strtolower($attrSlug);
        $code = preg_replace('/[^a-z0-9\-_]/', '_', $attrSlug);
        $code = str_replace('-', '_', $code);
        $code = preg_replace('/_+/', '_', $code);
        $code = trim($code, '_');
        $code = mb_strtoupper($code);

        return $code;
    }

    /**
     * Код свойства из названия (транслитерация средствами Битрикса) для строковых атрибутов
     *
     * @param string $label
     * @return string
     */
    private function makePropertyCodeFromLabelTranslit($label)
    {
        $label = trim($label);
        $label = rtrim($label, ':');
        $label = preg_replace('/\s+/', ' ', $label);
        $label = trim($label);

        if ($label === '') {
            return '';
        }

        if (class_exists('\CUtil')) {
            $code = \CUtil::translit($label, 'ru', [
                'replace_space' => '_',
                'replace_other' => '_',
                'change_case' => 'U',
                'max_len' => 50,
            ]);
        } else {
            $code = $label;
        }

        $code = preg_replace('/[^A-Z0-9_]/', '_', (string)$code);
        $code = preg_replace('/_+/', '_', $code);
        $code = trim($code, '_');

        return (string)$code;
    }

    /**
     * Применяет свойства к элементу: создает свойства/значения если нужно и обновляет элемент
     *
     * @param int $elementId
     * @param int $iblockId
     * @param array $attributes
     * @return array
     */
    private function applyProductProperties($elementId, $iblockId, array $attributes)
    {
        $elementId = (int)$elementId;
        $iblockId = (int)$iblockId;

        if ($elementId <= 0) {
            return [
                'success' => false,
                'message' => 'Не удалось применить свойства: неверный ID элемента',
            ];
        }

        if (empty($attributes)) {
            return [
                'success' => true,
                'message' => 'Свойства отсутствуют',
            ];
        }

        $propertyValuesToSet = [];

        foreach ($attributes as $attr) {
            $label = $attr['label'] ?? '';
            $attrSlug = $attr['attr_slug'] ?? '';
            $propertyCode = $attr['property_code'] ?? '';
            $values = $attr['values'] ?? [];
            $isMultiple = !empty($attr['is_multiple']);
            $type = $attr['type'] ?? 'L';

            if ($propertyCode === '' || $attrSlug === '' || empty($values)) {
                // Для строковых свойств attr_slug/values могут отсутствовать
                if ($type !== 'S') {
                    continue;
                }
            }

            // Исключение: ПРОИЗВОДИТЕЛЬ -> BRAND (привязка к элементам инфоблока 14)
            if ($attrSlug === 'proizvoditel') {
                $brandName = $values[0]['text'] ?? '';
                if ($brandName === '') {
                    continue;
                }

                $brandId = $this->findBrandElementIdByName($brandName, self::DEFAULT_BRAND_IBLOCK_ID);
                if ($brandId > 0) {
                    $propertyValuesToSet[self::DEFAULT_BRAND_PROPERTY_CODE] = $brandId;
                } else {
                    $this->errors[] = 'Не найден производитель "' . $brandName . '" в инфоблоке ' . self::DEFAULT_BRAND_IBLOCK_ID;
                }

                // Дополнительно дублируем производителя в свойство типа "Список"
                $producerListCode = $this->pickNonConflictingPropertyCode($iblockId, 'PROIZVODITEL', 'L');
                $producerPropId = $this->ensureListProperty($iblockId, $producerListCode, 'Производитель', false);
                if ($producerPropId) {
                    $xmlId = $this->makeXmlIdFromText($brandName);
                    $enumId = $this->ensureEnumValue($producerPropId, $brandName, $xmlId, $this->calculateEnumSort($brandName, $xmlId));
                    if ($enumId) {
                        $propertyValuesToSet[$producerListCode] = $enumId;
                    }
                }

                continue;
            }

            // Специальное сопоставление: "СТРАНА" из старого сайта -> готовое свойство COUNTRY
            if ($attrSlug === 'strana') {
                $propertyCode = 'COUNTRY';
            }

            // Строковые свойства (без ссылок)
            if ($type === 'S') {
                $propertyName = $this->normalizePropertyName($label);
                $valueText = $attr['value'] ?? '';
                $valueText = is_string($valueText) ? trim($valueText) : '';
                if ($valueText === '') {
                    continue;
                }

                // Если уже есть свойство с таким CODE и оно типа E (привязка),
                // пробуем заполнить его через поиск элемента в LINK_IBLOCK_ID
                $existing = $this->getIblockPropertyByCode($iblockId, $propertyCode);
                if ($existing && $existing['PROPERTY_TYPE'] === 'E') {
                    $linkedId = (int)($existing['LINK_IBLOCK_ID'] ?? 0);
                    if ($linkedId > 0) {
                        $linkedElementId = $this->findLinkedElementIdByName($valueText, $linkedId);
                        if ($linkedElementId > 0) {
                            $propertyValuesToSet[$propertyCode] = $linkedElementId;
                            continue;
                        }
                        $this->errors[] = 'Не найдено значение "' . $valueText . '" для привязки ' . $propertyCode . ' (инфоблок ' . $linkedId . ')';
                    }
                }

                $propertyId = $this->ensureStringProperty($iblockId, $propertyCode, $propertyName);
                if (!$propertyId) {
                    $this->errors[] = 'Не удалось создать/найти строковое свойство ' . $propertyCode . ' (' . $propertyName . ')';
                    continue;
                }

                $propertyValuesToSet[$propertyCode] = $valueText;
                continue;
            }

            // Обычные свойства: тип "Список"
            $propertyName = $this->normalizePropertyName($label);

            // Если уже есть свойство с таким CODE и оно типа E (привязка),
            // пробуем заполнить его через поиск элементов в LINK_IBLOCK_ID
            $existing = $this->getIblockPropertyByCode($iblockId, $propertyCode);
            if ($existing && $existing['PROPERTY_TYPE'] === 'E') {
                $linkedId = (int)($existing['LINK_IBLOCK_ID'] ?? 0);
                if ($linkedId > 0) {
                    $linkedIds = [];
                    foreach ($values as $v) {
                        $text = $v['text'] ?? '';
                        $text = is_string($text) ? trim($text) : '';
                        if ($text === '') {
                            continue;
                        }
                        $linkedElementId = $this->findLinkedElementIdByName($text, $linkedId);
                        if ($linkedElementId > 0) {
                            $linkedIds[] = $linkedElementId;
                        } else {
                            $this->errors[] = 'Не найдено значение "' . $text . '" для привязки ' . $propertyCode . ' (инфоблок ' . $linkedId . ')';
                        }
                    }

                    if (!empty($linkedIds)) {
                        $propertyValuesToSet[$propertyCode] = $isMultiple ? $linkedIds : $linkedIds[0];
                    }
                    continue;
                }
            }

            $propertyId = $this->ensureListProperty($iblockId, $propertyCode, $propertyName, $isMultiple);
            if (!$propertyId) {
                // Если не смогли создать из-за коллизии или ошибки — пропускаем конкретное свойство,
                // чтобы не падал весь импорт товара
                $this->errors[] = 'Не удалось создать/найти свойство ' . $propertyCode . ' (' . $propertyName . ')';
                continue;
            }

            $enumIds = [];
            foreach ($values as $v) {
                $text = $v['text'] ?? '';
                $xmlId = $v['xml_id'] ?? '';
                if ($text === '' || $xmlId === '') {
                    continue;
                }

                $sort = $this->calculateEnumSort($text, $xmlId);
                $enumId = $this->ensureEnumValue($propertyId, $text, $xmlId, $sort);
                if ($enumId) {
                    $enumIds[] = $enumId;
                }
            }

            if (empty($enumIds)) {
                continue;
            }

            $propertyValuesToSet[$propertyCode] = $isMultiple ? $enumIds : $enumIds[0];
        }

        if (!empty($propertyValuesToSet)) {
            \CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, $propertyValuesToSet);
        }

        return [
            'success' => true,
            'message' => 'Свойства применены',
        ];
    }

    /**
     * Получает свойство инфоблока по коду
     *
     * @param int $iblockId
     * @param string $code
     * @return array|null
     */
    private function getIblockPropertyByCode($iblockId, $code)
    {
        $iblockId = (int)$iblockId;
        $code = trim((string)$code);
        if ($iblockId <= 0 || $code === '') {
            return null;
        }

        $propRes = \CIBlockProperty::GetList(
            [],
            [
                'IBLOCK_ID' => $iblockId,
                'CODE' => $code,
            ]
        );
        while ($arProp = $propRes->Fetch()) {
            $foundCode = trim((string)$arProp['CODE']);
            if ($foundCode === '') {
                continue;
            }
            if (mb_strtoupper($foundCode) === mb_strtoupper($code)) {
                return $arProp;
            }
        }
        return null;
    }

    /**
     * Поиск элемента по названию в связанном инфоблоке (для свойств типа E)
     *
     * @param string $name
     * @param int $linkedIblockId
     * @return int
     */
    private function findLinkedElementIdByName($name, $linkedIblockId)
    {
        $name = trim((string)$name);
        $linkedIblockId = (int)$linkedIblockId;
        if ($name === '' || $linkedIblockId <= 0) {
            return 0;
        }

        $res = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $linkedIblockId,
                '=NAME' => $name,
            ],
            false,
            ['nTopCount' => 1],
            ['ID']
        );
        if ($ar = $res->Fetch()) {
            return (int)$ar['ID'];
        }

        // fallback: частичное совпадение
        $res = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $linkedIblockId,
                '%NAME' => $name,
            ],
            false,
            ['nTopCount' => 1],
            ['ID']
        );
        if ($ar = $res->Fetch()) {
            return (int)$ar['ID'];
        }

        return 0;
    }

    /**
     * Подбирает не конфликтующий код свойства, если базовый занят другим типом
     *
     * @param int $iblockId
     * @param string $baseCode
     * @param string $desiredType 'L'|'S'
     * @return string
     */
    private function pickNonConflictingPropertyCode($iblockId, $baseCode, $desiredType)
    {
        $iblockId = (int)$iblockId;
        $baseCode = trim((string)$baseCode);
        $desiredType = (string)$desiredType;

        if ($baseCode === '') {
            return '';
        }

        $existing = $this->getIblockPropertyByCode($iblockId, $baseCode);
        if (!$existing) {
            return $baseCode;
        }

        if (($existing['PROPERTY_TYPE'] ?? '') === $desiredType) {
            return $baseCode;
        }

        $suffix = $desiredType === 'S' ? '_STR' : '_LIST';
        $candidate = $baseCode . $suffix;
        $i = 1;
        while ($this->getIblockPropertyByCode($iblockId, $candidate)) {
            $candidate = $baseCode . $suffix . '_' . $i;
            $i++;
            if ($i > 50) {
                break;
            }
        }
        return $candidate;
    }

    /**
     * XML_ID из текста (для значений списков, когда нет ссылки)
     *
     * @param string $text
     * @return string
     */
    private function makeXmlIdFromText($text)
    {
        $text = trim((string)$text);
        if ($text === '') {
            return '';
        }

        if (class_exists('\CUtil')) {
            $xml = \CUtil::translit($text, 'ru', [
                'replace_space' => '-',
                'replace_other' => '-',
                'change_case' => 'L',
                'max_len' => 50,
            ]);
        } else {
            $xml = mb_strtolower($text);
        }

        $xml = preg_replace('/[^a-z0-9\-]/', '-', (string)$xml);
        $xml = preg_replace('/-+/', '-', (string)$xml);
        $xml = trim((string)$xml, '-');

        return (string)$xml;
    }

    /**
     * Создает/находит строковое свойство (PROPERTY_TYPE = S)
     *
     * @param int $iblockId
     * @param string $code
     * @param string $name
     * @return int
     */
    private function ensureStringProperty($iblockId, $code, $name)
    {
        $iblockId = (int)$iblockId;
        $code = trim($code);
        if ($iblockId <= 0 || $code === '') {
            return 0;
        }

        $propRes = \CIBlockProperty::GetList(
            [],
            [
                'IBLOCK_ID' => $iblockId,
                'CODE' => $code,
            ]
        );
        while ($arProp = $propRes->Fetch()) {
            if (mb_strtoupper(trim((string)$arProp['CODE'])) !== mb_strtoupper($code)) {
                continue;
            }
            $propertyId = (int)$arProp['ID'];

            // Если существует, но тип не строка — не трогаем
            if ($arProp['PROPERTY_TYPE'] !== 'S') {
                $this->errors[] = 'Коллизия кода свойства ' . $code
                    . ': найдено свойство ID=' . $propertyId
                    . ' IBLOCK_ID=' . (int)$arProp['IBLOCK_ID']
                    . ' NAME="' . (string)$arProp['NAME'] . '"'
                    . ' TYPE=' . (string)$arProp['PROPERTY_TYPE']
                    . ', пропускаем строковое';
                return 0;
            }

            if (!empty($name) && $arProp['NAME'] !== $name) {
                $p = new \CIBlockProperty();
                $p->Update($propertyId, ['NAME' => $name]);
            }

            return $propertyId;
        }

        $p = new \CIBlockProperty();
        $newId = $p->Add([
            'IBLOCK_ID' => $iblockId,
            'NAME' => $name ?: $code,
            'ACTIVE' => 'Y',
            'SORT' => 500,
            'CODE' => $code,
            'PROPERTY_TYPE' => 'S',
            'MULTIPLE' => 'N',
        ]);

        if (!$newId) {
            $this->errors[] = 'Ошибка создания строкового свойства ' . $code . ': ' . (string)$p->LAST_ERROR;
        }

        return $newId ? (int)$newId : 0;
    }

    /**
     * Нормализация названия свойства (убираем двоеточие, приводим к "Заглавные")
     *
     * @param string $label
     * @return string
     */
    private function normalizePropertyName($label)
    {
        $label = trim($label);
        $label = rtrim($label, ':');
        $label = preg_replace('/\s+/', ' ', $label);

        $lower = mb_strtolower($label, 'UTF-8');
        return mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Создает/находит свойство типа "Список"
     *
     * @param int $iblockId
     * @param string $code
     * @param string $name
     * @param bool $multiple
     * @return int
     */
    private function ensureListProperty($iblockId, $code, $name, $multiple)
    {
        $iblockId = (int)$iblockId;
        $code = trim($code);
        if ($iblockId <= 0 || $code === '') {
            return 0;
        }

        $propertyId = 0;
        $propRes = \CIBlockProperty::GetList(
            [],
            [
                'IBLOCK_ID' => $iblockId,
                'CODE' => $code,
            ]
        );
        while ($arProp = $propRes->Fetch()) {
            if (mb_strtoupper(trim((string)$arProp['CODE'])) !== mb_strtoupper($code)) {
                continue;
            }
            $propertyId = (int)$arProp['ID'];

            // Защита от коллизий: если свойство существует, но оно не типа "Список",
            // ничего не перезаписываем (иначе можно "сломать" уже созданные свойства, например BRAND)
            if ($arProp['PROPERTY_TYPE'] !== 'L') {
                $this->errors[] = 'Коллизия кода свойства ' . $code
                    . ': найдено свойство ID=' . $propertyId
                    . ' IBLOCK_ID=' . (int)$arProp['IBLOCK_ID']
                    . ' NAME="' . (string)$arProp['NAME'] . '"'
                    . ' TYPE=' . (string)$arProp['PROPERTY_TYPE']
                    . ', пропускаем автосоздание/обновление';
                return 0;
            }

            // Если по факту нужно MULTIPLE, а свойство не множественное — обновим
            if ($multiple && ($arProp['MULTIPLE'] !== 'Y')) {
                $p = new \CIBlockProperty();
                $p->Update($propertyId, ['MULTIPLE' => 'Y']);
            }

            // Обновим имя, если вдруг пустое/другое
            if (!empty($name) && $arProp['NAME'] !== $name) {
                $p = new \CIBlockProperty();
                $p->Update($propertyId, ['NAME' => $name]);
            }

            return $propertyId;
        }

        $p = new \CIBlockProperty();
        $newId = $p->Add([
            'IBLOCK_ID' => $iblockId,
            'NAME' => $name ?: $code,
            'ACTIVE' => 'Y',
            'SORT' => 500,
            'CODE' => $code,
            'PROPERTY_TYPE' => 'L',
            'LIST_TYPE' => 'L',
            'MULTIPLE' => $multiple ? 'Y' : 'N',
            'MULTIPLE_CNT' => $multiple ? 20 : 1,
        ]);

        if (!$newId) {
            $this->errors[] = 'Ошибка создания свойства-списка ' . $code . ': ' . (string)$p->LAST_ERROR;
        }

        return $newId ? (int)$newId : 0;
    }

    /**
     * Создает/находит значение списка по XML_ID
     *
     * @param int $propertyId
     * @param string $valueText
     * @param string $xmlId
     * @param int $sort
     * @return int ID перечисления
     */
    private function ensureEnumValue($propertyId, $valueText, $xmlId, $sort = 500)
    {
        $propertyId = (int)$propertyId;
        $valueText = trim($valueText);
        $xmlId = trim($xmlId);
        $sort = (int)$sort;

        if ($propertyId <= 0 || $valueText === '' || $xmlId === '') {
            return 0;
        }

        // В некоторых конфигурациях фильтры по XML_ID могут работать некорректно.
        // Поэтому строим карту всех значений свойства и ищем точное совпадение XML_ID.
        if (!isset($this->enumCacheByPropertyId[$propertyId])) {
            $this->enumCacheByPropertyId[$propertyId] = [];
            $enumResAll = \CIBlockPropertyEnum::GetList(
                ['SORT' => 'ASC', 'VALUE' => 'ASC'],
                ['PROPERTY_ID' => $propertyId]
            );
            while ($arEnum = $enumResAll->Fetch()) {
                $enumXml = trim((string)$arEnum['XML_ID']);
                if ($enumXml === '') {
                    continue;
                }
                $this->enumCacheByPropertyId[$propertyId][$enumXml] = [
                    'id' => (int)$arEnum['ID'],
                    'value' => (string)$arEnum['VALUE'],
                    'sort' => (int)$arEnum['SORT'],
                ];
            }
        }

        if (isset($this->enumCacheByPropertyId[$propertyId][$xmlId])) {
            $enum = $this->enumCacheByPropertyId[$propertyId][$xmlId];
            $enumId = (int)$enum['id'];

            $upd = [];
            // Приводим VALUE в соответствие XML_ID (исправляет ранее перезаписанные значения)
            if ((string)$enum['value'] !== $valueText) {
                $upd['VALUE'] = $valueText;
            }
            if ((int)$enum['sort'] !== $sort && $sort > 0) {
                $upd['SORT'] = $sort;
            }
            if (!empty($upd)) {
                $e = new \CIBlockPropertyEnum();
                $e->Update($enumId, $upd);
                // обновляем кеш
                $this->enumCacheByPropertyId[$propertyId][$xmlId]['value'] = $upd['VALUE'] ?? $this->enumCacheByPropertyId[$propertyId][$xmlId]['value'];
                $this->enumCacheByPropertyId[$propertyId][$xmlId]['sort'] = $upd['SORT'] ?? $this->enumCacheByPropertyId[$propertyId][$xmlId]['sort'];
            }

            return $enumId;
        }

        $e = new \CIBlockPropertyEnum();
        $newEnumId = $e->Add([
            'PROPERTY_ID' => $propertyId,
            'VALUE' => $valueText,
            'XML_ID' => $xmlId,
            'SORT' => $sort > 0 ? $sort : 500,
        ]);

        if ($newEnumId) {
            $this->enumCacheByPropertyId[$propertyId][$xmlId] = [
                'id' => (int)$newEnumId,
                'value' => $valueText,
                'sort' => $sort > 0 ? $sort : 500,
            ];
        } else {
            $this->errors[] = 'Ошибка добавления значения списка XML_ID=' . $xmlId . ' для PROPERTY_ID=' . $propertyId . ': ' . (string)$e->LAST_ERROR;
        }

        return $newEnumId ? (int)$newEnumId : 0;
    }

    /**
     * SORT для значений списка: если есть число — сортируем по нему
     *
     * @param string $text
     * @param string $xmlId
     * @return int
     */
    private function calculateEnumSort($text, $xmlId)
    {
        $candidate = $text . ' ' . $xmlId;

        if (preg_match('/(\d+(?:[.,]\d+)?)/u', $candidate, $m)) {
            $num = str_replace(',', '.', $m[1]);
            $float = (float)$num;
            $sort = (int)round($float * 10);
            return max(1, min(999999, $sort));
        }

        return 500;
    }

    /**
     * Поиск производителя (элемента) в инфоблоке 14 по названию
     *
     * @param string $name
     * @param int $iblockId
     * @return int
     */
    private function findBrandElementIdByName($name, $iblockId)
    {
        $name = trim($name);
        $iblockId = (int)$iblockId;
        if ($name === '' || $iblockId <= 0) {
            return 0;
        }

        $res = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $iblockId,
                '=NAME' => $name,
            ],
            false,
            ['nTopCount' => 1],
            ['ID']
        );
        if ($ar = $res->Fetch()) {
            return (int)$ar['ID'];
        }

        return 0;
    }

    /**
     * Вспомогательный метод: innerHTML для DOMElement
     *
     * @param \DOMElement $element
     * @return string
     */
    private function getInnerHtml(\DOMElement $element)
    {
        $innerHTML = '';
        foreach ($element->childNodes as $child) {
            $innerHTML .= $element->ownerDocument->saveHTML($child);
        }
        return $innerHTML;
    }

    /**
     * Получает символьный код товара из URL
     *
     * @param string $url
     * @return string
     */
    private function getCodeFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return '';
        }
        $path = rtrim($path, '/');
        $basename = basename($path);

        // Перестраховка: приводим к нижнему регистру
        $basename = mb_strtolower($basename);

        // Удаляем все лишнее, оставляем допустимые символы для кода
        $code = preg_replace('/[^a-z0-9\-_]/', '-', $basename);
        $code = trim(preg_replace('/-+/', '-', $code), '-');

        return $code;
    }
}
