<?
// Проверяем, является ли запрос AJAX
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php');

$APPLICATION->SetPageProperty("TITLE", "Результаты поиска");
$APPLICATION->SetTitle("Результаты поиска");
\Bitrix\Main\Loader::includeModule('iblock');
$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$search_query = trim($request->get("s"));
$search_template = $request->get("search_template")?:".default";

global $arrFilter;

$arrFilter = array(
    'IBLOCK_ID' => GetIBlockIDByCode("catalog"),
    'ACTIVE' => 'Y',
    'ACTIVE_DATE' => 'Y',
    'SECTION_GLOBAL_ACTIVE' => 'Y',
);

$countElements = 0;
if ($search_query && strlen($search_query) >= 2) {
    $search_query = htmlspecialcharsbx($search_query);
    
    // Включаем модуль поиска
    \Bitrix\Main\Loader::includeModule('search');
    
    $search_words = explode(' ', $search_query);
    $word_conditions = array();
    
    foreach ($search_words as $word) {
        if (strlen($word) < 2) continue;
        
        // Начинаем с оригинального слова
        $word_condition = array(
            'LOGIC' => 'OR',
            array('?NAME' => $word),
            array('?DETAIL_TEXT' => $word),
            // Для свойств типа "список" используем PROPERTY_ARTIKUL_ZAPCHASTI_VALUE
            array('?PROPERTY_ARTIKUL_ZAPCHASTI_VALUE' => $word),
            // Также пробуем через PROPERTY_ARTIKUL_ZAPCHASTI (для других типов свойств)
            array('?PROPERTY_ARTIKUL_ZAPCHASTI' => $word)
        );
        
        // Получаем основы слова через stemming
        $stems = stemming($word);
        
        if (is_array($stems) && !empty($stems)) {
            foreach ($stems as $stem => $frequency) {
                if ($stem && $stem != $word && strlen($stem) >= 2) {
                    $word_condition[] = array('?NAME' => $stem);
                    $word_condition[] = array('?DETAIL_TEXT' => $stem);
                    $word_condition[] = array('?PROPERTY_ARTIKUL_ZAPCHASTI_VALUE' => $stem);
                    $word_condition[] = array('?PROPERTY_ARTIKUL_ZAPCHASTI' => $stem);
                }
            }
        }
        
        $word_conditions[] = $word_condition;
    }
    
    // Объединяем все слова через AND
    if (!empty($word_conditions)) {
        if (count($word_conditions) > 1) {
            $arrFilter[] = array(
                'LOGIC' => 'AND',
                $word_conditions
            );
        } else {
            $arrFilter[] = $word_conditions[0];
        }
    }
    
    // Получаем количество элементов
    $countElements = CIBlockElement::GetList(
        array(),
        $arrFilter,
        array(),
        false
    );
}

// Для отладки - можно вывести сформированный фильтр
// echo '<pre>'; print_r($arrFilter); echo '</pre>';

?>

<section class="catalog">
    <div class="catalog__inner container">
        <div class="title-block">
            <h1 class="title-block__title"><?=$APPLICATION->ShowTitle(false);?></h1>
            <?$APPLICATION->IncludeComponent(
                "bitrix:breadcrumb",
                "",
                array(
                    "START_FROM" => "0",
                    "PATH" => "",
                    "SITE_ID" => "s1"
                )
            ); ?> 
        </div>
        <div class="catalog__features"><button class="btn filter__btn"><svg>
                    <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#filter"></use>
                </svg>Фильтр</button>
            <?$APPLICATION->ShowViewContent('catalog_counter');?>    
            <div class="catalog__list-view"><button class="btn catalog-view__btn active" data-view="columns"><svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#columns"></use>
                    </svg></button><button class="btn catalog-view__btn" data-view="lines"><svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#lines"></use>
                    </svg></button></div>
        </div>
        <div class="catalog__content">
            <sidebar class="catalog-sidebar">
                    <?$APPLICATION->IncludeComponent(
                        "bitrix:catalog.smart.filter",
                        "",
                        array(
                            "IBLOCK_TYPE" => "catalog",
                            "IBLOCK_ID" =>  GetIBlockIDByCode("catalog"),
                            'SECTION_ID' => "",
                            'HIDE_NOT_AVAILABLE' => "Y",
                            "PREFILTER_NAME"=>"arrFilter",
                            "FILTER_NAME" => "arrFilter",
                            "SEF_MODE"=>"Y",
                            "SEF_RULE"=> "/search/f/#SMART_FILTER_PATH#/",
                            "SMART_FILTER_PATH" => $_REQUEST["SMART_FILTER_PATH"],
                            "PRICE_CODE" => array(),
                            "CACHE_TYPE" => "A",
                            "CACHE_TIME" => "36000000",
                            "CACHE_GROUPS" => "Y",
                            "SAVE_IN_SESSION" => "N",
                            "FILTER_VIEW_MODE" => "VERTICAL",
                            "XML_EXPORT" => "Y",
                            "SECTION_TITLE" => "NAME",
                            "SECTION_DESCRIPTION" => "DESCRIPTION"
                        ),
                        $component,
                        array('HIDE_ICONS' => 'Y')
                    );?>
            </sidebar>
            <div class="catalog__list-wrapper js-product-container">
                <?$APPLICATION->IncludeComponent(
                    'bitrix:catalog.section',
                    '',
                    [
                        'IBLOCK_TYPE' => 'catalog',
                        'IBLOCK_ID' => GetIBlockIDByCode("catalog"),
                        'SECTION_ID' => "",
                        'SECTION_CODE' => '',
                        "FILTER_NAME" => "arrFilter",
                        'PROPERTYS_PREVIEW' => [
                            'SIZE',
                            'COLOR',     
                            'MATERIAL',  
                        ],

                        'ELEMENT_SORT_FIELD' => $sortField,
                        'ELEMENT_SORT_ORDER' => $sortOrder,
                        'ELEMENT_SORT_FIELD2' => $sortField2,
                        'ELEMENT_SORT_ORDER2' => $sortOrder2,
                        "ADD_SECTIONS_CHAIN" => $arParams["ADD_SECTIONS_CHAIN"],
                        // Указываем, какие свойства загружать
                        'PROPERTY_CODE' => ['SIZE', 'COLOR', 'MATERIAL'],
                        'HIDE_NOT_AVAILABLE' => $arParams["HIDE_NOT_AVAILABLE"],
                        "HIDE_NOT_AVAILABLE_OFFERS" => $arParams["HIDE_NOT_AVAILABLE_OFFERS"],

                        'SET_TITLE' => 'Y',
                        "USE_FILTER" => "Y",
                        'SET_BROWSER_TITLE' => 'Y',
                        'SET_META_KEYWORDS' => 'Y',
                        'SET_META_DESCRIPTION' => 'Y',
                        'PAGE_ELEMENT_COUNT' => '20',
                        "PAGER_TEMPLATE" => "arrows_custom",
                        
                        'PRICE_CODE' => ['BASE'],
                        'USE_PRICE_COUNT' => 'N',
                        'SHOW_PRICE_COUNT' => '1',
                        
                        'CACHE_TYPE' => 'A',
                        'CACHE_TIME' => '3600',
                        'CACHE_GROUPS' => 'Y',
                        
                        'AJAX_MODE' => 'N',
                        'AJAX_OPTION_JUMP' => 'N',
                        'AJAX_OPTION_STYLE' => 'Y',
                    ],
                    $component
                );
                ?>
            </div>
        </div>
        <div class="catalog__text">
            <?=$arResult["CURRENT_SECTION_DATA"]["DESCRIPTION"];?>
        </div>
    </div>
</section>


<?
if ($isAjax) {
    require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
} else {
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php');
}
?>