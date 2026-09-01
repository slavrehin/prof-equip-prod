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
$sectionCode = '';
if (!empty($arResult['SECTION']['PATH'])) {
    $sectionPath = end($arResult['SECTION']['PATH']);
    $sectionCode = $sectionPath['CODE'];
}

// Функция для формирования ЧПУ-ссылки на фильтр
function getFilterValueForUrl($property, $value) {
    // Если это свойство-список и есть XML_ID
    if ($property['PROPERTY_TYPE'] == 'L' && !empty($property['VALUE_XML_ID'])) {
        // Для множественных свойств
        if (is_array($property['VALUE_XML_ID'])) {
            $key = array_search($value, $property['VALUE']);
            if ($key !== false && !empty($property['VALUE_XML_ID'][$key])) {
                return $property['VALUE_XML_ID'][$key];
            }
        } 
        // Для одиночных свойств
        elseif (!empty($property['VALUE_XML_ID'])) {
            return $property['VALUE_XML_ID'];
        }
    }
    
    // Для всех остальных случаев - транслитерация
    return CUtil::translit($value, 'ru', array(
        "replace_space" => "-",
        "replace_other" => "-"
    ));
}
function getFilterSeoUrl($sectionCode, $propertyCode, $property, $value) {
    $valueForUrl = getFilterValueForUrl($property, $value);
    
    // Формируем путь фильтра в формате brand-is-electrolux
    $filterPath = strtolower($propertyCode) . '-is-' . $valueForUrl;
    
    return '/product-category/' . $sectionCode . '/f/' . $filterPath . '/';
}
?>

        <div class="catalog-product-hero__info">
            <?if(!empty($arResult['DETAIL_PICTURE']) || !empty($arResult['PROPERTIES']['GALLERY']['VALUE'])):?>
            <div class="catalog-product-hero__swiper-wrapper">
                <div class="catalog-product-hero__swiper">
                    <div class="swiper-wrapper">
                        <?php
                        // Массив для хранения всех изображений
                        $arAllImages = [];
                        
                        // 1. Добавляем DETAIL_PICTURE
                        if(!empty($arResult['DETAIL_PICTURE'])):
                            $arAllImages[] = $arResult['DETAIL_PICTURE'];
                        endif;
                        
                        // 2. Добавляем изображения из свойства GALLERY
                        if(!empty($arResult['PROPERTIES']['GALLERY']['VALUE'])):
                            if(is_array($arResult['PROPERTIES']['GALLERY']['VALUE'])):
                                foreach($arResult['PROPERTIES']['GALLERY']['VALUE'] as $fileId):
                                    $arFile = CFile::GetFileArray($fileId);
                                    if($arFile):
                                        $arAllImages[] = $arFile;
                                    endif;
                                endforeach;
                            else:
                                $arFile = CFile::GetFileArray($arResult['PROPERTIES']['GALLERY']['VALUE']);
                                if($arFile):
                                    $arAllImages[] = $arFile;
                                endif;
                            endif;
                        endif;
                        ?>
                        
                        <?php foreach($arAllImages as $arImage):?>
                            <a class="image-wrapper zoom-wrapper swiper-slide" 
                            href="<?=$arImage['SRC']?>" 
                            data-fancybox="exterior">
                                <picture>
                                    <source srcset="<?=$arImage['SRC']?>" type="image/webp">
                                    <img src="<?=$arImage['SRC']?>" alt="<?=$arResult['NAME']?>">
                                </picture>
                                <div class="fancy__button">
                                    <svg>
                                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#search"></use>
                                    </svg>
                                </div>
                            </a>
                        <?php endforeach;?>
                    </div>
                </div>
                
                <!-- Миниатюры -->
                <?php if(count($arAllImages) > 1):?>
                <div class="catalog-product-hero__swiper-thumbs">
                    <div class="swiper-wrapper">
                        <?php foreach($arAllImages as $arImage):
                            // Ресайз для миниатюры 200x200
                            $arThumb = CFile::ResizeImageGet(
                                $arImage,
                                array('width' => 200, 'height' => 200),
                                BX_RESIZE_IMAGE_PROPORTIONAL,
                                true
                            );
                        ?>
                            <div class="image-wrapper swiper-slide">
                                <picture>
                                    <source srcset="<?=$arThumb['src']?>" type="image/webp">
                                    <img src="<?=$arThumb['src']?>" alt="<?=$arResult['NAME']?>">
                                </picture>
                            </div>
                        <?php endforeach;?>
                    </div>
                </div>
                <?php endif;?>

            </div>
            <?php endif; ?>
            
            <div class="catalog-product-hero__info-text">
                <div class="info__list">
                    <?if(!empty($arResult['PREVIEW_TEXT'])):?>
                        <?=$arResult['~PREVIEW_TEXT']?>
                    <?endif;?>
                </div>
                
                <?// Статус наличия (если есть такое свойство) ?>
                <?if(!empty($arResult['PROPERTIES']['NALICHIE']['VALUE'])):?>
                    <?foreach ($arResult['PROPERTIES']['NALICHIE']['~VALUE'] as $item):?>
                        <p class="status"><?=($item)?></p>
                    <?endforeach;?>    
                <?endif;?>
                
                <button class="btn cost__btn" data-modal-load="/local/ajax/form/?WEB_FORM_ID=1&template_form=order&name_product=<?=$arResult['NAME']?>">
                    <span>ЗАПРОСИТЬ СТОИМОСТЬ</span>
                </button>
            </div>
        </div>
    </div>
</section>

<?// Блок с табами ?>
<div class="catalog-product-info">
    <div class="catalog-product-info__inner container">
        <div class="tabs">
            <?if(!empty($arResult['DETAIL_TEXT'])):?>
                <button class="btn tab active" data-tab="0">Описание</button>
            <?endif;?>
            
            <?if(!empty($arResult['PROPERTIES'])):?>
                <button class="btn tab <?=empty($arResult['DETAIL_TEXT']) ? 'active' : ''?>" data-tab="1">Характеристики</button>
            <?endif;?>
        </div>
        
        <?php
        if (!empty($arResult['DETAIL_TEXT'])) {
            // Обрабатываем оба варианта (с тильдой и без)
            $arResult['~DETAIL_TEXT'] = preg_replace(
                '/<iframe[^>]*src=["\'][^"\']*youtube\.com[^"\']*["\'][^>]*>.*?<\/iframe>/is', 
                '', 
                $arResult['~DETAIL_TEXT']
            );
            
            $arResult['DETAIL_TEXT'] = preg_replace(
                '/<iframe[^>]*src=["\'][^"\']*youtube\.com[^"\']*["\'][^>]*>.*?<\/iframe>/is', 
                '', 
                $arResult['DETAIL_TEXT']
            );
            
            // Очистка
            $arResult['~DETAIL_TEXT'] = preg_replace('/<p>\s*<\/p>/', '', $arResult['~DETAIL_TEXT']);
            $arResult['DETAIL_TEXT'] = preg_replace('/<p>\s*<\/p>/', '', $arResult['DETAIL_TEXT']);
            
            $arResult['~DETAIL_TEXT'] = trim($arResult['~DETAIL_TEXT']);
            $arResult['DETAIL_TEXT'] = trim($arResult['DETAIL_TEXT']);
        }
        ?>

        <?// Таб с описанием ?>
        <?if(!empty($arResult['DETAIL_TEXT'])):?>
        <div class="tab-content active">
            <?=$arResult['~DETAIL_TEXT']?>
        </div>
        <?endif;?>
        
        <?// Таб с характеристиками ?>
        <?if(!empty($arResult['PROPERTIES'])):?>
        <div class="tab-content <?=empty($arResult['DETAIL_TEXT']) ? 'active' : ''?>">
            <table>
                <tbody>
                    <?foreach($arResult['PROPERTIES'] as $arProp):?>
                        <?if(
                            !empty($arProp['VALUE']) && 
                            $arProp['CODE'] != 'DETAIL_PICTURE' && 
                            $arProp['CODE'] != 'PREVIEW_PICTURE' &&
                            $arProp['CODE'] != 'MORE_PHOTO' &&
                            $arProp['PROPERTY_TYPE'] != 'F' && // Пропускаем файлы
                            $arProp['PROPERTY_TYPE'] != 'E' && // Пропускаем привязку к элементам
                            $arProp['CODE'] != 'NALICHIE' // Пропускаем статус наличия, он уже выведен
                        ):?>
                            <tr>
                                <th><?=htmlspecialcharsbx($arProp['NAME'])?>:</th>
                                <td>
                                    <?if(is_array($arProp['VALUE'])):?>
                                        <?php
                                        $values = array();
                                        foreach($arProp['VALUE'] as $key => $value) {
                                            if(!empty($value)) {
                                                // Формируем ЧПУ-ссылку на фильтр
                                                $filterUrl = getFilterSeoUrl($sectionCode, $arProp['CODE'], $arProp, $value);
                                                $values[] = '<a href="' . $filterUrl . '">' . htmlspecialcharsbx($value) . '</a>';
                                            }
                                        }
                                        echo implode(', ', $values);
                                        ?>
                                    <?else:?>
                                        <?php
                                        // Для одиночного значения
                                        $filterUrl = getFilterSeoUrl($sectionCode, $arProp['CODE'], $arProp, $arProp['VALUE']);
                                        ?>
                                        <a href="<?=$filterUrl?>"><?=htmlspecialcharsbx($arProp['VALUE'])?></a>
                                    <?endif;?>
                                </td>
                            </tr>
                        <?endif;?>
                    <?endforeach;?>
                </tbody>
            </table>
        </div>
        <?endif;?>
    </div>
</div>

