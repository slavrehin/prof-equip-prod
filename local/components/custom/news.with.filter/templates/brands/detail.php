<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true){die();}
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
$this->setFrameMode(true);
$idBrand = GetIDByCode($arResult["VARIABLES"]["ELEMENT_CODE"],$arParams["IBLOCK_ID"]);
?>

<section class="brand">
    <div class="brand__inner container">
        <div class="title-block">
            <h1 class="title-block__title"><?=GetNameByCode($arResult["VARIABLES"]["ELEMENT_CODE"],$arParams["IBLOCK_ID"]);?></h1>
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
        <? global $arrFilter;
        $arrFilter = array("IBLOCK_ID" =>  GetIBlockIDByCode("catalog"), "PROPERTY_BRAND"=>$idBrand);
		$countElements = CIBlockElement::GetList(
			array(),
			$arrFilter,
			array(),
			false
		);
		if ($idBrand && $countElements):?>
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
								'HIDE_NOT_AVAILABLE' => $arParams["HIDE_NOT_AVAILABLE"],
								"PREFILTER_NAME"=>"arrFilter",
								"FILTER_NAME" => "arrFilter",
								"SEF_MODE"=>"Y",
								"SEF_RULE"=> "/brends/".$arResult["VARIABLES"]["ELEMENT_CODE"]."/f/#SMART_FILTER_PATH#/",
								"SMART_FILTER_PATH" => $_REQUEST["SMART_FILTER_PATH"],
								"SHOW_ALL_WO_SECTION" => "Y",
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
					"bitrix:catalog.section", 
					"",
					array(
						"IBLOCK_TYPE" => "catalog",
						"IBLOCK_ID" => GetIBlockIDByCode("catalog"), 
						"SECTION_ID" => "",
						"SECTION_CODE" => "",
						"IS_FAVORITE" => "Y",
						"SECTION_USER_FIELDS" => array(),
						'ELEMENT_SORT_FIELD' => $sortField,
						'ELEMENT_SORT_ORDER' => $sortOrder,
						'ELEMENT_SORT_FIELD2' => $sortField2,
						'ELEMENT_SORT_ORDER2' => $sortOrder2,
						"FILTER_NAME" => "arrFilter", 
						"INCLUDE_SUBSECTIONS" => "Y",
						"SHOW_ALL_WO_SECTION" => "Y",
						"PAGE_ELEMENT_COUNT" => "20",
						"LINE_ELEMENT_COUNT" => "3",
						"PROPERTY_CODE" => array(
							0 => "SIZE",
							1 => "COLOR",
						),
						"OFFERS_FIELD_CODE" => array(),
						"OFFERS_PROPERTY_CODE" => array(),
						"OFFERS_SORT_FIELD" => "sort",
						"OFFERS_SORT_ORDER" => "asc",
						"OFFERS_SORT_FIELD2" => "id",
						"OFFERS_SORT_ORDER2" => "desc",
						"OFFERS_LIMIT" => "5",
						"TEMPLATE_THEME" => "blue",
						"PRODUCT_DISPLAY_MODE" => "Y",
						"ADD_PICT_PROP" => "-",
						"LABEL_PROP" => "-",
						"OFFER_ADD_PICT_PROP" => "-",
						"OFFER_TREE_PROPS" => array(),
						"PRODUCT_SUBSCRIPTION" => "N",
						"SHOW_DISCOUNT_PERCENT" => "N",
						"SHOW_OLD_PRICE" => "N",
						"SHOW_CLOSE_POPUP" => "N",
						"MESS_BTN_BUY" => "Купить",
						"MESS_BTN_ADD_TO_BASKET" => "В корзину",
						"MESS_BTN_SUBSCRIBE" => "Подписаться",
						"MESS_BTN_DETAIL" => "Подробнее",
						"MESS_NOT_AVAILABLE" => "Нет в наличии",
						"SECTION_URL" => "",
						"DETAIL_URL" => "",
						"SECTION_ID_VARIABLE" => "SECTION_ID",
						"AJAX_MODE" => "N",
						"AJAX_OPTION_JUMP" => "N",
						"AJAX_OPTION_STYLE" => "Y",
						"AJAX_OPTION_HISTORY" => "N",
						"CACHE_TYPE" => "A",
						"CACHE_TIME" => "36000000",
						"CACHE_GROUPS" => "Y",
						"SET_META_KEYWORDS" => "Y",
						"META_KEYWORDS" => "-",
						"SET_META_DESCRIPTION" => "Y",
						"META_DESCRIPTION" => "-",
						"BROWSER_TITLE" => "-",
						"ADD_SECTIONS_CHAIN" => "N",
						"SET_TITLE" => "N",
						"SET_STATUS_404" => "N",
						"CACHE_FILTER" => "N",
						"CONVERT_CURRENCY" => "N",
						"BASKET_URL" => "/personal/basket.php",
						"ACTION_VARIABLE" => "action",
						"PRODUCT_ID_VARIABLE" => "id",
						"PRODUCT_QUANTITY_VARIABLE" => "quantity",
						"ADD_PROPERTIES_TO_BASKET" => "Y",
						"PRODUCT_PROPS_VARIABLE" => "prop",
						"PARTIAL_PRODUCT_PROPERTIES" => "N",
						"PRODUCT_PROPERTIES" => array(),
						"OFFERS_CART_PROPERTIES" => array(),
						"DISPLAY_COMPARE" => "N",
						"SET_BROWSER_TITLE" => "Y",
						"SET_LAST_MODIFIED" => "N",
						"USE_MAIN_ELEMENT_SECTION" => "N",
						"PRICE_CODE" => array(
							0 => "BASE",
						),
						"USE_PRICE_COUNT" => "N",
						"SHOW_PRICE_COUNT" => "1",
						"PRICE_VAT_INCLUDE" => "Y",
						"USE_PRODUCT_QUANTITY" => "N",
						"PRODUCT_QUANTITY_VARIABLE" => "",
						"ADD_TO_BASKET_ACTION" => "ADD",
						"PAGER_TEMPLATE" => "arrows_custom",
						"DISPLAY_TOP_PAGER" => "N",
						"DISPLAY_BOTTOM_PAGER" => "Y",
						"PAGER_TITLE" => "Товары",
						"PAGER_SHOW_ALWAYS" => "N",
						"PAGER_DESC_NUMBERING" => "N",
						"PAGER_DESC_NUMBERING_CACHE_TIME" => "36000",
						"PAGER_SHOW_ALL" => "N",
						"PAGER_BASE_LINK_ENABLE" => "N",
						"LAZY_LOAD" => "N",
						"LOAD_ON_SCROLL" => "N",
						"HIDE_NOT_AVAILABLE" => "N",
						"HIDE_NOT_AVAILABLE_OFFERS" => "N",
						"COMPONENT_TEMPLATE" => "favorites",
						"BACKGROUND_IMAGE" => "-",
						"DISABLE_INIT_JS_IN_COMPONENT" => "N",
						"SEF_MODE" => "N",
						"CUSTOM_FILTER" => "",
						"SIZE_PROPERTY_CODE" => "SIZE", 
						"COLOR_PROPERTY_CODE" => "COLOR",
						"IS_FAVORITE"=>"Y"
					),
					false
				); ?>
				</div>
			</div>
		<?endif;?>
        <?
            $ElementID = $APPLICATION->IncludeComponent(
                "bitrix:news.detail",
                "brand-detail",
                [
                    "DISPLAY_DATE" => $arParams["DISPLAY_DATE"],
                    "DISPLAY_NAME" => $arParams["DISPLAY_NAME"],
                    "DISPLAY_PICTURE" => $arParams["DISPLAY_PICTURE"],
                    "DISPLAY_PREVIEW_TEXT" => $arParams["DISPLAY_PREVIEW_TEXT"],
                    "IBLOCK_TYPE" => $arParams["IBLOCK_TYPE"],
                    "IBLOCK_ID" => GetIBlockIDByCode('brands'),
                    "FIELD_CODE" => $arParams["DETAIL_FIELD_CODE"],
                    "PROPERTY_CODE" => $arParams["DETAIL_PROPERTY_CODE"],
                    "DETAIL_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["detail"],
                    "SECTION_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["section"],
                    "META_KEYWORDS" => $arParams["META_KEYWORDS"],
                    "META_DESCRIPTION" => $arParams["META_DESCRIPTION"],
                    "BROWSER_TITLE" =>  "Y",
                    "SET_CANONICAL_URL" => $arParams["DETAIL_SET_CANONICAL_URL"],
                    "SET_LAST_MODIFIED" => $arParams["SET_LAST_MODIFIED"],
                    "SET_TITLE" => "Y",
                    "MESSAGE_404" => $arParams["MESSAGE_404"],
                    "SET_STATUS_404" => $arParams["SET_STATUS_404"],
                    "SHOW_404" => $arParams["SHOW_404"],
                    "FILE_404" => $arParams["FILE_404"],
                    "INCLUDE_IBLOCK_INTO_CHAIN" => "N",
                    "ADD_SECTIONS_CHAIN" => "N",
                    "ACTIVE_DATE_FORMAT" => $arParams["DETAIL_ACTIVE_DATE_FORMAT"],
                    "CACHE_TYPE" => $arParams["CACHE_TYPE"],
                    "CACHE_TIME" => $arParams["CACHE_TIME"],
                    "CACHE_GROUPS" => $arParams["CACHE_GROUPS"],
                    "USE_PERMISSIONS" => $arParams["USE_PERMISSIONS"],
                    "GROUP_PERMISSIONS" => $arParams["GROUP_PERMISSIONS"],
                    "DISPLAY_TOP_PAGER" => $arParams["DETAIL_DISPLAY_TOP_PAGER"],
                    "DISPLAY_BOTTOM_PAGER" => $arParams["DETAIL_DISPLAY_BOTTOM_PAGER"],
                    "PAGER_TITLE" => $arParams["DETAIL_PAGER_TITLE"],
                    "PAGER_SHOW_ALWAYS" => "N",
                    "PAGER_TEMPLATE" => $arParams["DETAIL_PAGER_TEMPLATE"],
                    "PAGER_SHOW_ALL" => $arParams["DETAIL_PAGER_SHOW_ALL"],
                    "CHECK_DATES" => $arParams["CHECK_DATES"],
                    "ELEMENT_ID" => $arResult["VARIABLES"]["ELEMENT_ID"],
                    "ELEMENT_CODE" => $arResult["VARIABLES"]["ELEMENT_CODE"],
                    "SECTION_ID" => $arResult["VARIABLES"]["SECTION_ID"],
                    "SECTION_CODE" => $arResult["VARIABLES"]["SECTION_CODE"],
                    "IBLOCK_URL" => $arResult["FOLDER"].$arResult["URL_TEMPLATES"]["news"],
                    "USE_SHARE" => $arParams["USE_SHARE"],
                    "SHARE_HIDE" => $arParams["SHARE_HIDE"],
                    "SHARE_TEMPLATE" => $arParams["SHARE_TEMPLATE"],
                    "SHARE_HANDLERS" => $arParams["SHARE_HANDLERS"],
                    "SHARE_SHORTEN_URL_LOGIN" => $arParams["SHARE_SHORTEN_URL_LOGIN"],
                    "SHARE_SHORTEN_URL_KEY" => $arParams["SHARE_SHORTEN_URL_KEY"],
                    "ADD_ELEMENT_CHAIN" => "Y",
                    'STRICT_SECTION_CHECK' => $arParams['STRICT_SECTION_CHECK'],
                ],
                $component
            );?>
    </div>
</section>
