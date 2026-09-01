<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title><?php $APPLICATION->ShowTitle() ?></title>
    <?php $APPLICATION->ShowHead() ?>
    <meta name="robots" content="index, follow">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" href="/favicon.webp">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@26.0.6/build/css/intlTelInput.css">
    <link href="<?=LAYOUT_DIR?>assets/css/main.css" rel="stylesheet">
    <!-- Yandex.Metrika counter --> <script type="text/javascript">     (function(m,e,t,r,i,k,a){         m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};         m[i].l=1*new Date();         for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}         k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)     })(window, document,'script','https://mc.yandex.ru/metrika/tag.js', 'ym');      ym(44219954, 'init', {webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true}); </script> <noscript><div><img src="https://mc.yandex.ru/watch/44219954" style="position:absolute; left:-9999px;" alt="" /></div></noscript> <!-- /Yandex.Metrika counter -->  
    <script async src="//widgets.mango-office.ru/site/26349"></script>
	<?/*
    <script src="//code.jivo.ru/widget/seZHKaOq36" async></script>
	*/?>
    <?if (($APPLICATION->GetCurDir() === "/prachechnaya/") || ($APPLICATION->GetCurDir() === "/product-category/prachechnoe-oborudovanie/")):?>
        <!-- Marquiz script start -->
        <script>
        (function(w, d, s, o){
        var j = d.createElement(s); j.async = true; j.src = '//script.marquiz.ru/v2.js';j.onload = function() {
            if (document.readyState !== 'loading') Marquiz.init(o);
            else document.addEventListener("DOMContentLoaded", function() {
            Marquiz.init(o);
            });
        };
        d.head.insertBefore(j, d.head.firstElementChild);
        })(window, document, 'script', {
            host: '//quiz.marquiz.ru',
            region: 'ru',
            id: '686e3d1c9147f80019d0a437',
            autoOpen: 25,
            autoOpenFreq: 'once',
            openOnExit: false,
            disableOnMobile: false,
            widget: {
            position: 'right'
            }
        }
        );
        </script>
        <!-- Marquiz script end -->
    <?endif;?>     
    <?if (($APPLICATION->GetCurDir() === "/kuhnya/") || ($APPLICATION->GetCurDir() === "/product-category/oborudovanie-professionalnoj-kuhni/")):?>
       <!-- Marquiz script start -->
        <script>
        (function(w, d, s, o){
        var j = d.createElement(s); j.async = true; j.src = '//script.marquiz.ru/v2.js';j.onload = function() {
            if (document.readyState !== 'loading') Marquiz.init(o);
            else document.addEventListener("DOMContentLoaded", function() {
            Marquiz.init(o);
            });
        };
        d.head.insertBefore(j, d.head.firstElementChild);
        })(window, document, 'script', {
            host: '//quiz.marquiz.ru',
            region: 'ru',
            id: '69ea19408042f500199545e8',
            autoOpen: 25,
            autoOpenFreq: 'once',
            openOnExit: false,
            disableOnMobile: false,
            widget: {
            position: 'right'
            }
        }
        );
        </script>
        <!-- Marquiz script end -->
    <?endif;?>  
    <?if (($APPLICATION->GetCurDir() === "/tekstil/") || ($APPLICATION->GetCurDir() === "/product-category/professionalnyj-tekstil/")):?>
        <!-- Marquiz script start -->
        <script>
        (function(w, d, s, o){
        var j = d.createElement(s); j.async = true; j.src = '//script.marquiz.ru/v2.js';j.onload = function() {
            if (document.readyState !== 'loading') Marquiz.init(o);
            else document.addEventListener("DOMContentLoaded", function() {
            Marquiz.init(o);
            });
        };
        d.head.insertBefore(j, d.head.firstElementChild);
        })(window, document, 'script', {
            host: '//quiz.marquiz.ru',
            region: 'ru',
            id: '69ea19578ae1ec00198f8014',
            autoOpen: 25,
            autoOpenFreq: 'once',
            openOnExit: false,
            disableOnMobile: false,
            widget: {
            position: 'right'
            }
        }
        );
        </script>
        <!-- Marquiz script end -->
    <?endif;?>  
</head>

<body>
        <? $APPLICATION->ShowPanel(); ?>
    <header class="header <?if($APPLICATION->GetCurDir() !== "/"):?>black<?endif;?>">
        <div class="header__inner container"><a class="logo" href="/">
                <div class="white-logo">
                    <picture>
                        <source srcset="/upload/logo-white.png, /upload/logo-white.png 2x" type="image/webp">
                        <img src="/upload/logo-white.png" srcset="/upload/logo-white.png, /upload/logo-white.png 2x" alt="logo">
                    </picture>
                </div>
                <div class="color-logo">
                    <picture>
                        <source srcset="/upload/logo.png" type="image/webp">
                        <img src="/upload/logo.png" srcset="/upload/logo.png" alt="logo">
                    </picture>
                </div>
            </a><button class="btn primary s burger__btn" type="button"><span class="burger__icon"><span></span><span></span><span></span></span><span class="btn__text">Каталог</span></button>
            <?$APPLICATION->IncludeComponent(
                "bitrix:menu",
                "header",
                array(
                    "ALLOW_MULTI_SELECT" => "N",
                    "DELAY" => "N",
                    "MAX_LEVEL" => "1",
                    "MENU_CACHE_GET_VARS" => array(
                    ),
                    "MENU_CACHE_TIME" => "3600",
                    "MENU_CACHE_TYPE" => "N",
                    "MENU_CACHE_USE_GROUPS" => "N",
                    "ROOT_MENU_TYPE" => "header",
                    "USE_EXT" => "Y",
                    "COMPONENT_TEMPLATE" => "header"
                ),
                false
            );?>
            <div class="header__features">
                <?$APPLICATION->IncludeComponent(
                    "bitrix:news.detail", 
                    "contacts_head", 
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
                <? $phoneMain = getMainPhoneFromIblock();
                    if ($phoneMain):?>
                        <a href="tel:<?=$phoneMain;?>" class="btn phone__btn" type="button" aria-label="Телефон"><svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#phone-fill"></use>
                    </svg></a>
                <?endif;?>    
                <div class="search-btns"><button class="btn search__btn" type="button" aria-label="Поиск"><svg>
                            <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#search"></use>
                        </svg></button><button class="btn close-search__btn hidden" type="button" aria-label="закрыть поиск"><svg>
                            <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#close"></use>
                        </svg></button></div><button class="btn burger__btn" type="button" aria-label="Меню" data-modal-load="/local/ajax/mob_menu/"><svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#burger"></use>
                    </svg></button>
            </div>
        </div>
        <div class="direction-catalog">
            <div class="direction-catalog__inner container">
                <?$APPLICATION->IncludeComponent("bitrix:news.list", "directions_header", array(
                    "IBLOCK_TYPE" => "content",
                    "IBLOCK_ID" => GetIBlockIDByCode("directionshome"),
                    "NEWS_COUNT" => "20",
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
        </div>
        <div class="services-catalog">
            <div class="services-catalog__inner container">
                <?$APPLICATION->IncludeComponent("bitrix:news.list", "directions_header", array(
                    "IBLOCK_TYPE" => "content",
                    "IBLOCK_ID" => GetIBlockIDByCode("services"),
                    "NEWS_COUNT" => "20",
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
        </div>
        <div class="header-catalog">
            <?$APPLICATION->IncludeComponent(
                "bitrix:catalog.section.list", 
                "section_head", 
                array(
                    "IBLOCK_ID" => GetIBlockIDByCode("catalog"),
                    "IBLOCK_TYPE" => "catalog",
                    "FILTER_NAME" => "", // Имя переменной с фильтром
                    "SECTION_ID" => "",
                    "COUNT_ELEMENTS" => "N",
                    "TOP_DEPTH" => 1, 
                    "SECTION_FIELDS" => array("NAME", "CODE", "PICTURE", "DESCRIPTION"),
                    "SECTION_USER_FIELDS" => array("UF_*"),
                    "CACHE_TYPE" => "A",
                    "CACHE_TIME" => "36000000",
                    "CACHE_GROUPS" => "Y",
                    "ADD_SECTIONS_CHAIN" => "N",
                    "VIEW_MODE" => "LIST",
                    "SHOW_PARENT_NAME" => "Y",
                    "COMPONENT_TEMPLATE" => "section_categories"
                ),
                false
            );?>
        </div>
        <div class="search-block">
            <form class="search-block__inner container" action="/search/">
                <div class="input-wrapper">
                    <div class="input__row">
                        <div class="input-wrapper"><input class="input input__valid-name" placeholder="Поиск" value="" name="s"></div>
                    </div><button class="btn submit-search__btn"><svg>
                            <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#search"></use>
                        </svg></button>
                </div>
            </form>
        </div>
    </header>
    <main>
	