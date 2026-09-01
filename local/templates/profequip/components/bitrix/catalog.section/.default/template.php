<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

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
?>
                <? if (isset($arResult['NAV_RESULT']) && $arResult['NAV_RESULT']): ?>
                    <?$this->SetViewTarget('catalog_counter');?>
                        <? 
                        $itemsOnPage = $arResult['NAV_RESULT']->NavPageNomer < $arResult['NAV_RESULT']->NavPageCount 
                            ? $arResult['NAV_RESULT']->NavPageSize 
                            : ($arResult['NAV_RESULT']->NavRecordCount - ($arResult['NAV_RESULT']->NavPageSize * ($arResult['NAV_RESULT']->NavPageCount - 1)));
                        $totalItems = $arResult['NAV_RESULT']->NavRecordCount;
                        ?>
                        <?= $itemsOnPage ?> из <?= $totalItems ?>
                    <?$this->EndViewTarget();?> 
                <? endif; ?>
<div class="catalog__list page-item-list-wrap catalog-products">

    <?
    $generalParams = [
        'SHOW_DISCOUNT_PERCENT' => $arParams['SHOW_DISCOUNT_PERCENT'],
        'PRODUCT_DISPLAY_MODE' => $arParams['PRODUCT_DISPLAY_MODE'],
        'SHOW_MAX_QUANTITY' => $arParams['SHOW_MAX_QUANTITY'],
        'RELATIVE_QUANTITY_FACTOR' => $arParams['RELATIVE_QUANTITY_FACTOR'],
        'MESS_SHOW_MAX_QUANTITY' => $arParams['~MESS_SHOW_MAX_QUANTITY'],
        'MESS_RELATIVE_QUANTITY_MANY' => $arParams['~MESS_RELATIVE_QUANTITY_MANY'],
        'MESS_RELATIVE_QUANTITY_FEW' => $arParams['~MESS_RELATIVE_QUANTITY_FEW'],
        'SHOW_OLD_PRICE' => $arParams['SHOW_OLD_PRICE'],
        'USE_PRODUCT_QUANTITY' => $arParams['USE_PRODUCT_QUANTITY'],
        'PRODUCT_QUANTITY_VARIABLE' => $arParams['PRODUCT_QUANTITY_VARIABLE'],
        'ADD_TO_BASKET_ACTION' => $arParams['ADD_TO_BASKET_ACTION'],
        'ADD_PROPERTIES_TO_BASKET' => $arParams['ADD_PROPERTIES_TO_BASKET'],
        'PRODUCT_PROPS_VARIABLE' => $arParams['PRODUCT_PROPS_VARIABLE'],
        'SHOW_CLOSE_POPUP' => $arParams['SHOW_CLOSE_POPUP'],
        'DISPLAY_COMPARE' => $arParams['DISPLAY_COMPARE'],
        'COMPARE_PATH' => $arParams['COMPARE_PATH'],
        'COMPARE_NAME' => $arParams['COMPARE_NAME'],
        'PRODUCT_SUBSCRIPTION' => $arParams['PRODUCT_SUBSCRIPTION'],
        'PRODUCT_BLOCKS_ORDER' => $arParams['PRODUCT_BLOCKS_ORDER'],
        'LABEL_POSITION_CLASS' => $labelPositionClass,
        'DISCOUNT_POSITION_CLASS' => $discountPositionClass,
        'SLIDER_INTERVAL' => $arParams['SLIDER_INTERVAL'],
        'SLIDER_PROGRESS' => $arParams['SLIDER_PROGRESS'],
        '~BASKET_URL' => $arParams['~BASKET_URL'],
        '~ADD_URL_TEMPLATE' => $arResult['~ADD_URL_TEMPLATE'],
        '~BUY_URL_TEMPLATE' => $arResult['~BUY_URL_TEMPLATE'],
        '~COMPARE_URL_TEMPLATE' => $arResult['~COMPARE_URL_TEMPLATE'],
        '~COMPARE_DELETE_URL_TEMPLATE' => $arResult['~COMPARE_DELETE_URL_TEMPLATE'],
        'TEMPLATE_THEME' => $arParams['TEMPLATE_THEME'],
        'USE_ENHANCED_ECOMMERCE' => $arParams['USE_ENHANCED_ECOMMERCE'],
        'DATA_LAYER_NAME' => $arParams['DATA_LAYER_NAME'],
        'BRAND_PROPERTY' => $arParams['BRAND_PROPERTY'],
        'MESS_BTN_BUY' => $arParams['~MESS_BTN_BUY'],
        'MESS_BTN_DETAIL' => $arParams['~MESS_BTN_DETAIL'],
        'MESS_BTN_COMPARE' => $arParams['~MESS_BTN_COMPARE'],
        'MESS_BTN_SUBSCRIBE' => $arParams['~MESS_BTN_SUBSCRIBE'],
        'MESS_BTN_ADD_TO_BASKET' => $arParams['~MESS_BTN_ADD_TO_BASKET'],
    ];
    ?>

            <? if (!empty($arResult['ITEMS'])): ?>
                <? foreach ($arResult['ITEMS'] as $item): ?>
                    <?
                    $uniqueId = $item['ID'].'_'.md5($this->randString().$component->getAction());
                    $areaIds[$item['ID']] = $this->GetEditAreaId($uniqueId);
                    $this->AddEditAction($uniqueId, $item['EDIT_LINK'], $elementEdit);
                    $this->AddDeleteAction($uniqueId, $item['DELETE_LINK'], $elementDelete, $elementDeleteParams);
                    ?>
                    
                    <?
                    $itemParams = [
                        'SIZE_PROPERTY_CODE' => $sizePropertyCode,
                        'COLOR_PROPERTY_CODE' => $colorPropertyCode,
                        'LAYOUT_PATH' => LAYOUT_PATH,
                    ];
                    
                    // Включаем компонент catalog.item
                    $APPLICATION->IncludeComponent(
                        'bitrix:catalog.item',
                        'card',
                        [
                            'RESULT' => [
                                'ITEM' => $item,
                                'AREA_ID' => $areaIds[$item['ID']],
                                'TYPE' => 'card',
                                'BIG_LABEL' => 'N',
                                'BIG_DISCOUNT_PERCENT' => 'N',
                                'BIG_BUTTONS' => 'N',
                                'SCALABLE' => 'N'
                            ],
                            'PARAMS' => $itemParams,
                        ],
                        $component,
                        ['HIDE_ICONS' => 'N']
                    );
                    ?>
                <? endforeach; ?>
            <? endif; ?>

 
</div>

<?= $arResult['NAV_STRING']; ?>