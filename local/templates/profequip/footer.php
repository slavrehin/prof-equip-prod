 
    </main>
    <footer class="footer">
        <div class="footer__inner container">
            <div class="footer__content_top row">
                <div class="footer__col col-12 col-lg-6 col-xxl-3">
                    <p class="col__title">Новости</p>
                    <?$APPLICATION->IncludeComponent("bitrix:news.list", "news-list-footer", array(
                        "IBLOCK_TYPE" => "content",
                        "IBLOCK_ID" => GetIBlockIDByCode("news"),
                        "NEWS_COUNT" => "3",
                        "SORT_BY1" => "ACTIVE_FROM",
                        "SORT_ORDER1" => "DESC",
                        "SORT_BY2" => "SORT",
                        "SORT_ORDER2" => "ASC",
                        "FILTER_NAME" => "",
                        "FIELD_CODE" => array("ID", "NAME", "PREVIEW_TEXT", "PREVIEW_PICTURE", "DATE_ACTIVE_FROM"),
                        "PROPERTY_CODE" => array("COUNTRY", "YEAR"),
                        "CHECK_DATES" => "Y",
                        "DETAIL_URL" => "",
                        "AJAX_MODE" => "N",
                        "AJAX_OPTION_JUMP" => "N",
                        "AJAX_OPTION_STYLE" => "Y",
                        "AJAX_OPTION_HISTORY" => "N",
                        "CACHE_TYPE" => "A",
                        "CACHE_TIME" => "36000000",
                        "CACHE_FILTER" => "N",
                        "CACHE_GROUPS" => "Y",
                        "PREVIEW_TRUNCATE_LEN" => "",
                        "ACTIVE_DATE_FORMAT" => "d.m.Y",
                        "SET_TITLE" => "N",
                        "SET_BROWSER_TITLE" => "N",
                        "SET_META_KEYWORDS" => "N",
                        "SET_META_DESCRIPTION" => "N",
                        "SET_LAST_MODIFIED" => "N",
                        "INCLUDE_IBLOCK_INTO_CHAIN" => "N",
                        "ADD_SECTIONS_CHAIN" => "N",
                        "HIDE_LINK_WHEN_NO_DETAIL" => "N",
                        "PARENT_SECTION" => "",
                        "PARENT_SECTION_CODE" => "",
                        "INCLUDE_SUBSECTIONS" => "Y",
                        "STRICT_SECTION_CHECK" => "N",
                        "DISPLAY_DATE" => "Y",
                        "DISPLAY_NAME" => "Y",
                        "DISPLAY_PICTURE" => "Y",
                        "DISPLAY_PREVIEW_TEXT" => "Y",
                        "PAGER_TEMPLATE" => "arrows_custom",
                        "DISPLAY_TOP_PAGER" => "N",
                        "DISPLAY_BOTTOM_PAGER" => "Y",
                        "PAGER_TITLE" => "Новости",
                        "PAGER_SHOW_ALWAYS" => "N",
                        "PAGER_DESC_NUMBERING" => "N",
                        "PAGER_DESC_NUMBERING_CACHE_TIME" => "36000",
                        "PAGER_SHOW_ALL" => "N",
                        "PAGER_BASE_LINK_ENABLE" => "N",
                        "SET_STATUS_404" => "N",
                        "SHOW_404" => "N",
                        "TITLE_BLOCK"=>"Проекты",
                        "MESSAGE_404" => ""
                    ),
                    false
                    );
                    ?>
                </div>
                <div class="footer__col col-12 col-lg-6 col-xxl-3">
                    <p class="col__title">Статьи</p>
                    <?$APPLICATION->IncludeComponent("bitrix:news.list", "news-list-footer", array(
                        "IBLOCK_TYPE" => "content",
                        "IBLOCK_ID" => GetIBlockIDByCode("blog"),
                        "NEWS_COUNT" => "3",
                        "SORT_BY1" => "ACTIVE_FROM",
                        "SORT_ORDER1" => "DESC",
                        "SORT_BY2" => "SORT",
                        "SORT_ORDER2" => "ASC",
                        "FILTER_NAME" => "",
                        "FIELD_CODE" => array("ID", "NAME", "PREVIEW_TEXT", "PREVIEW_PICTURE", "DATE_ACTIVE_FROM"),
                        "PROPERTY_CODE" => array("COUNTRY", "YEAR"),
                        "CHECK_DATES" => "Y",
                        "DETAIL_URL" => "",
                        "AJAX_MODE" => "N",
                        "AJAX_OPTION_JUMP" => "N",
                        "AJAX_OPTION_STYLE" => "Y",
                        "AJAX_OPTION_HISTORY" => "N",
                        "CACHE_TYPE" => "A",
                        "CACHE_TIME" => "36000000",
                        "CACHE_FILTER" => "N",
                        "CACHE_GROUPS" => "Y",
                        "PREVIEW_TRUNCATE_LEN" => "",
                        "ACTIVE_DATE_FORMAT" => "d.m.Y",
                        "SET_TITLE" => "N",
                        "SET_BROWSER_TITLE" => "N",
                        "SET_META_KEYWORDS" => "N",
                        "SET_META_DESCRIPTION" => "N",
                        "SET_LAST_MODIFIED" => "N",
                        "INCLUDE_IBLOCK_INTO_CHAIN" => "N",
                        "ADD_SECTIONS_CHAIN" => "N",
                        "HIDE_LINK_WHEN_NO_DETAIL" => "N",
                        "PARENT_SECTION" => "",
                        "PARENT_SECTION_CODE" => "",
                        "INCLUDE_SUBSECTIONS" => "Y",
                        "STRICT_SECTION_CHECK" => "N",
                        "DISPLAY_DATE" => "Y",
                        "DISPLAY_NAME" => "Y",
                        "DISPLAY_PICTURE" => "Y",
                        "DISPLAY_PREVIEW_TEXT" => "Y",
                        "PAGER_TEMPLATE" => "arrows_custom",
                        "DISPLAY_TOP_PAGER" => "N",
                        "DISPLAY_BOTTOM_PAGER" => "Y",
                        "PAGER_TITLE" => "Новости",
                        "PAGER_SHOW_ALWAYS" => "N",
                        "PAGER_DESC_NUMBERING" => "N",
                        "PAGER_DESC_NUMBERING_CACHE_TIME" => "36000",
                        "PAGER_SHOW_ALL" => "N",
                        "PAGER_BASE_LINK_ENABLE" => "N",
                        "SET_STATUS_404" => "N",
                        "SHOW_404" => "N",
                        "TITLE_BLOCK"=>"Проекты",
                        "MESSAGE_404" => ""
                    ),
                    false
                    );
                    ?>
                </div>
                <div class="footer__col col-12 col-lg-6 col-xxl-3">
                    <p class="col__title">Компания</p>
                    <?$APPLICATION->IncludeComponent(
						"bitrix:menu",
						"footer",
						array(
							"ALLOW_MULTI_SELECT" => "N",
							"DELAY" => "N",
							"MAX_LEVEL" => "2",
							"MENU_CACHE_GET_VARS" => array(
							),
							"MENU_CACHE_TIME" => "3600",
							"MENU_CACHE_TYPE" => "N",
							"MENU_CACHE_USE_GROUPS" => "N",
							"ROOT_MENU_TYPE" => "footer",
							"USE_EXT" => "Y",
							"COMPONENT_TEMPLATE" => "footer"
						),
						false
					);?>
                </div>
                <div class="footer__col col-12 col-lg-6 col-xxl-3">
                    <p class="col__title">Контакты</p>
                    <?$APPLICATION->IncludeComponent(
                        "bitrix:news.detail", 
                        "contacts_footer", 
                        array(
                        "DISPLAY_DATE" => "Y",
                        "DISPLAY_NAME" => "Y",
                        "DISPLAY_PICTURE" => "Y",
                        "DISPLAY_PREVIEW_TEXT" => "Y",
                        "USE_SHARE" => "Y",
                        "SHARE_HIDE" => "N",
                        "SHARE_TEMPLATE" => "",
                        "SHARE_HANDLERS" => array(
                            0 => "delicious",
                        ),
                        "SHARE_SHORTEN_URL_LOGIN" => "",
                        "SHARE_SHORTEN_URL_KEY" => "",
                        "AJAX_MODE" => "N",
                        "IBLOCK_ID" => GetIBlockIDByCode("contacts"),
                        "ELEMENT_ID" => getMainIdFromContactIblock(),
                        "CHECK_DATES" => "Y",
                        "FIELD_CODE" => array(
                            0 => "ID",
                            1 => "",
                        ),
                        "PROPERTY_CODE" => array(
                            0 => "MAP",
                            1 => "DESCRIPTION",
                            2 => "",
                        ),
                        "IBLOCK_URL" => "",
                        "DETAIL_URL" => "",
                        "SET_TITLE" => "N",
                        "SET_CANONICAL_URL" => "N",
                        "SET_BROWSER_TITLE" => "N",
                        "BROWSER_TITLE" => "-",
                        "SET_META_KEYWORDS" => "N",
                        "META_KEYWORDS" => "-",
                        "SET_META_DESCRIPTION" => "N",
                        "META_DESCRIPTION" => "-",
                        "SET_STATUS_404" => "N",
                        "SET_LAST_MODIFIED" => "N",
                        "INCLUDE_IBLOCK_INTO_CHAIN" => "N",
                        "ADD_SECTIONS_CHAIN" => "N",
                        "ADD_ELEMENT_CHAIN" => "N",
                        "ACTIVE_DATE_FORMAT" => "d.m.Y",
                        "USE_PERMISSIONS" => "N",
                        "GROUP_PERMISSIONS" => array(
                            0 => "1",
                        ),
                        "CACHE_TYPE" => "A",
                        "CACHE_TIME" => "3600",
                        "CACHE_GROUPS" => "Y",
                        "DISPLAY_TOP_PAGER" => "Y",
                        "DISPLAY_BOTTOM_PAGER" => "Y",
                        "PAGER_TITLE" => "Страница",
                        "PAGER_TEMPLATE" => "",
                        "PAGER_SHOW_ALL" => "Y",
                        "PAGER_BASE_LINK_ENABLE" => "Y",
                        "SHOW_404" => "N",
                        "MESSAGE_404" => "",
                        "STRICT_SECTION_CHECK" => "Y",
                        "PAGER_BASE_LINK" => "",
                        "PAGER_PARAMS_NAME" => "arrPager",
                        "AJAX_OPTION_JUMP" => "N",
                        "AJAX_OPTION_STYLE" => "Y",
                        "AJAX_OPTION_HISTORY" => "N",
                        "COMPONENT_TEMPLATE" => "about",
                        "AJAX_OPTION_ADDITIONAL" => "",
                        "FILE_404" => ""
                        ),
                        false
                    );?>
                </div>
            </div>
            <div class="footer__content_bottom row">
                <p class="copy">© <?=date("Y")?> Prof-Equip Все права защищены</p>
            </div>
        </div>
    </footer>
    <div class="captcha-container-footer"></div>
    <div class="modals"></div>
    <script src="https://unpkg.com/imagesloaded@5/imagesloaded.pkgd.min.js"></script>
    <script src="https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js"></script>
    <script src="https://api-maps.yandex.ru/2.1/?apikey=43191fbc-c80b-4bf4-98ef-b14487ccd9d8&amp;lang=ru_RU" defer="defer"></script>
    <script src="https://smartcaptcha.yandexcloud.net/captcha.js?render=onload&onload=onloadFunction" defer></script>
    <script src="<?=SITE_TEMPLATE_PATH;?>/js/captcha.js"></script>
    <script defer="defer" src="<?=LAYOUT_DIR?>assets/js/main.js"></script>
    <script src="<?= SITE_TEMPLATE_PATH; ?>/js/dev.js"></script>
	
	<script>
        (function(w,d,u){
                var s=d.createElement('script');s.async=true;s.src=u+'?'+(Date.now()/60000|0);
                var h=d.getElementsByTagName('script')[0];h.parentNode.insertBefore(s,h);
        })(window,document,'https://cdn-ru.bitrix24.ru/b3630091/crm/site_button/loader_3_tqv63s.js');
	</script>
	<script>
            (function(w,d,u){
                    var s=d.createElement('script');s.async=true;s.src=u+'?'+(Date.now()/60000|0);
                    var h=d.getElementsByTagName('script')[0];h.parentNode.insertBefore(s,h);
            })(window,document,'https://cdn-ru.bitrix24.ru/b3630091/crm/site_button/loader_7_1ostxn.js');
    </script>


    <script>
            (function(w,d,u){
                    var s=d.createElement('script');s.async=true;s.src=u+'?'+(Date.now()/60000|0);
                    var h=d.getElementsByTagName('script')[0];h.parentNode.insertBefore(s,h);
            })(window,document,'https://cdn-ru.bitrix24.ru/b3630091/crm/site_button/loader_5_ep5lkn.js');
    </script>
    <?if (($APPLICATION->GetCurDir() === "/tekstil/") || ($APPLICATION->GetCurDir() === "/product-category/professionalnyj-tekstil/")):?>
        <script>
                (function(w,d,u){
                        var s=d.createElement('script');s.async=true;s.src=u+'?'+(Date.now()/60000|0);
                        var h=d.getElementsByTagName('script')[0];h.parentNode.insertBefore(s,h);
                })(window,document,'https://cdn-ru.bitrix24.ru/b3630091/crm/site_button/loader_9_2u53jb.js');
        </script>
    <?endif;?>  
</body>

<?$APPLICATION->IncludeComponent('custom:seo.params', "", array(
    "IBLOCK_ID" => GetIBlockIDByCode("seoparams"),
    "FILTER" => array("NAME" => $APPLICATION->GetCurDir()),
    "SELECT" => array("PROPERTY_*"),
));
?>

<?

    global $APPLICATION;
    
    // Определяем протокол и домен
    $protocol = (CMain::IsHTTPS()) ? "https://" : "http://";
    $domain = $protocol . $_SERVER['SERVER_NAME'];
    
    // Текущий URL страницы
    $curPage = $APPLICATION->GetCurPage();
    $fullUrl = $domain . $curPage;
    $canonicalUrl = $domain . $curPage;

    // Добавляем каноническую ссылку в head
    $APPLICATION->AddHeadString('<link rel="canonical" href="' . $canonicalUrl . '" />'); 

    // Формируем массив Open Graph тегов
    $ogTags = [
        'og:url' => $fullUrl,
        'og:type' => 'website',
        'og:title' => htmlspecialchars($APPLICATION->GetTitle()),
        'og:description' => $APPLICATION->GetProperty("description"),
        'og:site_name' => "ПРОФЭКВИП",
        'og:locale' => 'ru_RU'
    ];
    
    $currentUrl = $APPLICATION->GetCurDir();
    $urlParts = explode('/', trim($currentUrl, '/'));
    $symbolicCode = end($urlParts);

    // Проверяем, что мы не на странице product и есть символьный код
    if (strpos($currentUrl, "/product/") === false && !empty($symbolicCode)) {
        
        // Ищем элемент по символьному коду
        $foundElement = findElementByCode($symbolicCode);
        
        if ($foundElement) {
            // Определяем какую картинку использовать (детальная или анонса)
            $imageId = 0;
            if (!empty($foundElement['DETAIL_PICTURE'])) {
                $imageId = $foundElement['DETAIL_PICTURE'];
            } elseif (!empty($foundElement['PREVIEW_PICTURE'])) {
                $imageId = $foundElement['PREVIEW_PICTURE'];
            }
            
            // Получаем путь к картинке
            if ($imageId > 0) {
                $arImage = CFile::GetFileArray($imageId);
                if ($arImage && !empty($arImage['SRC'])) {
                    $imageSrc = $arImage['SRC'];
                    // Делаем абсолютный URL если нужно
                    if (strpos($imageSrc, 'http') !== 0) {
                        $imageSrc = $domain . $imageSrc;
                    }
                    $ogTags['og:image'] = $imageSrc;
                }
            }
        }
        
        // Если элемент не найден или у него нет картинки, проверяем кастомное og:image
        if (empty($ogTags['og:image'])) {
            $ogImage = $APPLICATION->GetProperty('og:image');
            if ($ogImage) {
                if (strpos($ogImage, 'http') !== 0) {
                    $ogImage = $domain . $ogImage;
                }
                $ogTags['og:image'] = $ogImage;
            }
        }
        
        // Если всё ещё нет картинки, используем логотип
        if (empty($ogTags['og:image'])) {
            $defaultImage = $domain . '/upload/logo.png';
            if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/upload/logo.png')) {
                $ogTags['og:image'] = $defaultImage;
            }
        }
    }

    // Добавляем теги в head
    foreach ($ogTags as $property => $content) {
        if (!empty($content)) {
            $APPLICATION->AddHeadString(
                '<meta property="' . $property . '" content="' . htmlspecialchars($content) . '" />',
                true
            );
        }
    }
    

?>
</html>