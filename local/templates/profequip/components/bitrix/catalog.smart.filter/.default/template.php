<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
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
use App\ProductArticleHelper;

// SEO-значимые значения фильтра — те свойства раздела, для которых в
// инфоблоке "seofilterrules" заведено правило (см. подробный комментарий
// "===== SEO умного фильтра =====" в .../catalog/catalog/section.php: CODE
// правила = "<код раздела>__<код свойства в нижнем регистре>"). Для их
// значений ниже добавляем настоящую <a href> на посадочную страницу
// /f/свойство-is-значение/, чтобы поисковый бот мог дойти до неё и без JS —
// сам чекбокс по-прежнему работает через AJAX как раньше.
$seoFilterSectionCode = null;
if (preg_match('#^/product-category/([^/]+)/#', (string)($_SERVER['REQUEST_URI'] ?? ''), $seoFilterSectionMatch)) {
    $seoFilterSectionCode = $seoFilterSectionMatch[1];
}

$seoSignificantProperties = [];
if ($seoFilterSectionCode !== null) {
    $seoFilterRulesIblockId = GetIBlockIDByCode('seofilterrules');
    if ($seoFilterRulesIblockId) {
        $rsSeoFilterRule = CIBlockElement::GetList([], [
            'IBLOCK_ID' => $seoFilterRulesIblockId,
            'ACTIVE' => 'Y',
            '?CODE' => $seoFilterSectionCode . '__%',
        ], false, false, ['ID', 'CODE']);
        while ($arSeoFilterRule = $rsSeoFilterRule->Fetch()) {
            $seoRulePropCode = substr($arSeoFilterRule['CODE'], strlen($seoFilterSectionCode) + 2);
            if ($seoRulePropCode !== '') {
                $seoSignificantProperties[] = $seoRulePropCode; // уже в нижнем регистре
            }
        }
    }
}
?>

<div class="catalog-sidebar-filters">
    <button class="close-filters">
        <svg>
            <use xlink:href="<?= LAYOUT_DIR ?>assets/img/sprite.svg#close"></use>
        </svg>
    </button>
    
    <p class="catalog-sidebar-filters__title">Подбор по параметрам</p>
    
    <form name="<?echo $arResult["FILTER_NAME"]."_form"?>" action="<?echo $arResult["FORM_ACTION"]?>" method="get" class="js-filter-form">
        <?foreach($arResult["HIDDEN"] as $arItem):?>
            <input type="hidden" name="<?echo $arItem["CONTROL_NAME"]?>" id="<?echo $arItem["CONTROL_ID"]?>" value="<?echo $arItem["HTML_VALUE"]?>" />
        <?endforeach;?>
        <div class="accordion">
        <?foreach($arResult["ITEMS"] as $key => $arItem):?>
            <?php
            $key = $arItem["ENCODED_ID"];
            $isExpanded = ($arItem["DISPLAY_EXPANDED"] == "Y") ? "active" : "";
            $isColorProperty = ($arItem["CODE"] == "TSVET" || $arItem["CODE"] == "COLOR" || $arItem["CODE"] == "CVET");
            $isSeoSignificant = $seoFilterSectionCode !== null
                && in_array(mb_strtolower($arItem["CODE"]), $seoSignificantProperties, true);
            ?>

            <?if(isset($arItem["PRICE"]) || !empty($arItem["VALUES"])):?>
                <div class="filter__item <?=$isExpanded?> accordion__item js-filter-section" id="js-filter-<?=$key?>" data-filter-id="<?=$key?>">
                    <div class="btn accordion__title"><span class="filter__title"><?=($arItem["CODE"] == "RRC_PRICE")?"Цена":htmlspecialcharsbx($arItem["NAME"])?>:</span><span class="filter__icon"> <svg>
                                                <use xlink:href="<?= LAYOUT_DIR ?>assets/img/sprite.svg#accordion-arrow"></use>
                                            </svg></span></div>
                    
                    <?if(isset($arItem["PRICE"])):?>
                        <!-- Ценовой диапазон -->
                        <?if($arItem["VALUES"]["MAX"]["VALUE"] - $arItem["VALUES"]["MIN"]["VALUE"] > 0):?>
                            <div class="double-range js-filter-range" data-range-id="<?=$key?>">
                                <div class="slider">
                                    <div class="slider-track js-range-track"></div>
                                    <input 
                                        class="slider-1 js-range-min js-filter-control" 
                                        type="range" 
                                        min="<?=$arItem["VALUES"]["MIN"]["VALUE"]?>" 
                                        max="<?=$arItem["VALUES"]["MAX"]["VALUE"]?>" 
                                        value="<?=$arItem["VALUES"]["MIN"]["HTML_VALUE"] ?: $arItem["VALUES"]["MIN"]["VALUE"]?>" 
                                        step="1"
                                        name="<?=$arItem["VALUES"]["MIN"]["CONTROL_NAME"]?>"
                                        id="<?=$arItem["VALUES"]["MIN"]["CONTROL_ID"]?>"
                                    />
                                    <input 
                                        class="slider-2 js-range-max js-filter-control" 
                                        type="range" 
                                        min="<?=$arItem["VALUES"]["MIN"]["VALUE"]?>" 
                                        max="<?=$arItem["VALUES"]["MAX"]["VALUE"]?>" 
                                        value="<?=$arItem["VALUES"]["MAX"]["HTML_VALUE"] ?: $arItem["VALUES"]["MAX"]["VALUE"]?>" 
                                        step="1"
                                        name="<?=$arItem["VALUES"]["MAX"]["CONTROL_NAME"]?>"
                                        id="<?=$arItem["VALUES"]["MAX"]["CONTROL_ID"]?>"
                                    />
                                </div>
                                <div class="values">
                                    <span class="value1 js-range-value-min"><?=$arItem["VALUES"]["MIN"]["HTML_VALUE"] ?: $arItem["VALUES"]["MIN"]["VALUE"]?></span>
                                    <span class="value2 js-range-value-max"><?=$arItem["VALUES"]["MAX"]["HTML_VALUE"] ?: $arItem["VALUES"]["MAX"]["VALUE"]?></span>
                                </div>
                            </div>
                        <?endif;?>
                    
                    <?elseif($arItem["DISPLAY_TYPE"] == "A" || $arItem["DISPLAY_TYPE"] == "B"):?>
                        <!-- Числовые диапазоны -->
                        <?if($arItem["VALUES"]["MAX"]["VALUE"] - $arItem["VALUES"]["MIN"]["VALUE"] > 0):?>
                            <div class="double-range js-filter-range" data-range-id="<?=$key?>">
                                <div class="slider">
                                    <div class="slider-track js-range-track"></div>
                                    <input 
                                        class="slider-1 js-range-min js-filter-control" 
                                        type="range" 
                                        min="<?=$arItem["VALUES"]["MIN"]["VALUE"]?>" 
                                        max="<?=$arItem["VALUES"]["MAX"]["VALUE"]?>" 
                                        value="<?=$arItem["VALUES"]["MIN"]["HTML_VALUE"] ?: $arItem["VALUES"]["MIN"]["VALUE"]?>" 
                                        step="1"
                                        name="<?=$arItem["VALUES"]["MIN"]["CONTROL_NAME"]?>"
                                        id="<?=$arItem["VALUES"]["MIN"]["CONTROL_ID"]?>"
                                    />
                                    <input 
                                        class="slider-2 js-range-max js-filter-control" 
                                        type="range" 
                                        min="<?=$arItem["VALUES"]["MIN"]["VALUE"]?>" 
                                        max="<?=$arItem["VALUES"]["MAX"]["VALUE"]?>" 
                                        value="<?=$arItem["VALUES"]["MAX"]["HTML_VALUE"] ?: $arItem["VALUES"]["MAX"]["VALUE"]?>" 
                                        step="1"
                                        name="<?=$arItem["VALUES"]["MAX"]["CONTROL_NAME"]?>"
                                        id="<?=$arItem["VALUES"]["MAX"]["CONTROL_ID"]?>"
                                    />
                                </div>
                                <div class="values">
                                    <span class="value1 js-range-value-min"><?=$arItem["VALUES"]["MIN"]["HTML_VALUE"] ?: $arItem["VALUES"]["MIN"]["VALUE"]?></span>
                                    <span class="value2 js-range-value-max"><?=$arItem["VALUES"]["MAX"]["HTML_VALUE"] ?: $arItem["VALUES"]["MAX"]["VALUE"]?></span>
                                </div>
                            </div>
                        <?endif;?>
                    
                    <?elseif($arItem["DISPLAY_TYPE"] == "P" || $arItem["DISPLAY_TYPE"] == "R" || $arItem["DISPLAY_TYPE"] == "K"):?>
                        <!-- Выпадающие списки и радиокнопки -->
                        <div class="filters__list">
                            <?if($arItem["DISPLAY_TYPE"] == "K"):?>
                                <!-- Радиокнопки - опция "Все" -->
                                <label class="filter-label">
                                    <input 
                                        type="radio" 
                                        value="" 
                                        name="<?=$arItem["VALUES"][array_key_first($arItem["VALUES"])]["CONTROL_NAME_ALT"]?>" 
                                        id="all_<?=$arItem["VALUES"][array_key_first($arItem["VALUES"])]["CONTROL_ID"]?>"
                                        class="js-filter-control js-filter-radio"
                                        <?=!$arResult["ACTIVE_VALUES"][$key] ? 'checked="checked"' : ''?>
                                    />
                                    <span><?=GetMessage("CT_BCSF_FILTER_ALL")?></span>
                                </label>
                            <?endif;?>
                            
                            <?foreach($arItem["VALUES"] as $val => $ar):?>
                                <?php
                                $seoValueHref = ($isSeoSignificant && !empty($ar["URL_ID"]))
                                    ? '/product-category/' . $seoFilterSectionCode . '/f/' . mb_strtolower($arItem["CODE"]) . '-is-' . $ar["URL_ID"] . '/'
                                    : null;
                                ?>
                                <?if($arItem["DISPLAY_TYPE"] == "K"):?>
                                    <!-- Радиокнопки -->
                                    <label class="filter-label" data-role="label_<?=$ar["CONTROL_ID"]?>">
                                        <input
                                            type="radio"
                                            value="<?=$ar["HTML_VALUE_ALT"]?>"
                                            name="<?=$ar["CONTROL_NAME_ALT"]?>"
                                            id="<?=$ar["CONTROL_ID"]?>"
                                            class="js-filter-control js-filter-radio"
                                            <?=$ar["CHECKED"] ? 'checked="checked"' : ''?>
                                            <?=$ar["DISABLED"] ? 'disabled="disabled"' : ''?>
                                        />
                                        <span>
                                            <?if($seoValueHref):?><a class="js-filter-seo-link" href="<?=htmlspecialcharsbx($seoValueHref)?>"><?=htmlspecialcharsbx($ar["VALUE"])?></a><?else:?><?=htmlspecialcharsbx($ar["VALUE"])?><?endif;?>
                                            <?if($arParams["DISPLAY_ELEMENT_COUNT"] !== "N" && isset($ar["ELEMENT_COUNT"])):?>
                                                <span data-role="count_<?=$ar["CONTROL_ID"]?>">(<?=$ar["ELEMENT_COUNT"]?>)</span>
                                            <?endif;?>
                                        </span>
                                    </label>

                                <?elseif($arItem["DISPLAY_TYPE"] == "R"):?>
                                    <!-- Цвета с метками (радиокнопки) -->
                                    <label class="filter-label filter__color"
                                           data-balloon="<?=htmlspecialcharsbx($ar["VALUE"])?>"
                                           data-balloon-pos="top"
                                           data-role="label_<?=$ar["CONTROL_ID"]?>">
                                        <input
                                            type="radio"
                                            value="<?=$ar["HTML_VALUE_ALT"]?>"
                                            name="<?=$ar["CONTROL_NAME_ALT"]?>"
                                            id="<?=$ar["CONTROL_ID"]?>"
                                            class="js-filter-control js-filter-radio"
                                            <?=$ar["CHECKED"] ? 'checked="checked"' : ''?>
                                            <?=$ar["DISABLED"] ? 'disabled="disabled"' : ''?>
                                        />
                                        <?php $colorSwatchStyle = ((isset($ar["FILE"]) && !empty($ar["FILE"]["SRC"])) ? "background: url('".$ar["FILE"]["SRC"]."') center/cover;" : (($isColorProperty && isset($ar["COLOR_CODE"])) ? "background: ".$ar["COLOR_CODE"].";" : "")); ?>
                                        <?if($seoValueHref):?><a class="js-filter-seo-link" href="<?=htmlspecialcharsbx($seoValueHref)?>" style="<?=$colorSwatchStyle?>"></a><?else:?><span style="<?=$colorSwatchStyle?>"></span><?endif;?>
                                    </label>
                                <?endif;?>
                            <?endforeach;?>
                        </div>
                    
                    <?else:?>
                        <!-- Чекбоксы (стандартные и цвета) -->
                        <div class="filters__list accordion__content">
                            <div class="filter-label__list">
                            <?foreach($arItem["VALUES"] as $val => $ar):?>
                                <?php
                                $isColor = ($isColorProperty || isset($ar["FILE"]["SRC"]) || isset($ar["COLOR_CODE"]));
                                $colorStyle = "";
                                if($isColorProperty) {
                                    $hex = ProductArticleHelper::getColorHexFromHL($ar["VALUE"]);
                                    if (explode("-",$hex)[0] != $hex){
                                        $colorStyle = "--color-1: ".explode("-",$hex)[0] ."; --color-2: ".explode("-",$hex)[1] .";";
                                    } else {
                                        $colorStyle = "background: " . $hex . ";";
                                    }
                                } elseif(isset($ar["FILE"]["SRC"])) {
                                    $colorStyle = "background: url('" . $ar["FILE"]["SRC"] . "') center/cover;";
                                }
                                $seoValueHref = ($isSeoSignificant && !empty($ar["URL_ID"]))
                                    ? '/product-category/' . $seoFilterSectionCode . '/f/' . mb_strtolower($arItem["CODE"]) . '-is-' . $ar["URL_ID"] . '/'
                                    : null;
                                ?>

                                <?if($isColor):?>
                                    <!-- Цвета как квадратики -->
                                    <label class="filter-label filter__color <?=$isColor ? 'js-filter-color-label' : ''?>"
                                           data-balloon="<?=htmlspecialcharsbx($ar["VALUE"])?>"
                                           data-balloon-pos="top"
                                           data-role="label_<?=$ar["CONTROL_ID"]?>">
                                        <input
                                            type="checkbox"
                                            value="<?=$ar["HTML_VALUE"]?>"
                                            name="<?=$ar["CONTROL_NAME"]?>"
                                            id="<?=$ar["CONTROL_ID"]?>"
                                            class="js-filter-control js-filter-checkbox"
                                            <?=$ar["CHECKED"] ? 'checked="checked"' : ''?>
                                            <?=$ar["DISABLED"] ? 'disabled="disabled"' : ''?>
                                        />
                                        <?if($seoValueHref):?><a class="js-filter-seo-link" href="<?=htmlspecialcharsbx($seoValueHref)?>" style="<?=$colorStyle?>"></a><?else:?><span style="<?=$colorStyle?>"></span><?endif;?>
                                    </label>
                                <?else:?>
                                    <!-- Стандартные чекбоксы -->
                                    <label class="filter-label" data-role="label_<?=$ar["CONTROL_ID"]?>">
                                        <input
                                            type="checkbox"
                                            value="<?=$ar["HTML_VALUE"]?>"
                                            name="<?=$ar["CONTROL_NAME"]?>"
                                            id="<?=$ar["CONTROL_ID"]?>"
                                            class="js-filter-control js-filter-checkbox"
                                            <?=$ar["CHECKED"] ? 'checked="checked"' : ''?>
                                            <?=$ar["DISABLED"] ? 'disabled="disabled"' : ''?>
                                        />
                                        <span>
                                            <?if($seoValueHref):?><a class="js-filter-seo-link" href="<?=htmlspecialcharsbx($seoValueHref)?>"><?=htmlspecialcharsbx($ar["VALUE"])?></a><?else:?><?=htmlspecialcharsbx($ar["VALUE"])?><?endif;?>
                                            <?if($arParams["DISPLAY_ELEMENT_COUNT"] !== "N" && isset($ar["ELEMENT_COUNT"])):?>
                                                <span data-role="count_<?=$ar["CONTROL_ID"]?>">(<?=$ar["ELEMENT_COUNT"]?>)</span>
                                            <?endif;?>
                                        </span>
                                    </label>
                                <?endif;?>
                            <?endforeach;?>
                            </div>
                        </div>
                    <?endif;?>
                </div>
            <?endif;?>
        <?endforeach;?>
        </div>
        <input type="hidden" name="set_filter" value="Y" />
        <input type="hidden" name="set_filter_url" value="<?=$arResult["FILTER_URL"];?>" />
        
        <div class="filter__btns">
            <button class="btn clear__btn js-filter-reset" type="button">Сбросить</button>
        </div>
    </form>
</div>