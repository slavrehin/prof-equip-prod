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

                <?php $productPrice = profequip_GetCatalogItemPrice($arResult); ?>
                <?php if ($productPrice !== null): ?>
                <div class="product-price-row">
                    <div class="product-price">
                        <span class="product-price__value"><?= profequip_FormatPriceRub($productPrice) ?></span>
                        <span class="product-price__note">с НДС</span>
                    </div>
                    <button class="btn cost__btn cta-btn" data-modal-load="/local/ajax/form/?WEB_FORM_ID=1&template_form=order&name_product=<?=$arResult['NAME']?>&cta_label=<?=rawurlencode('ПОЛУЧИТЬ КП')?>">
                        <svg class="cta-btn-icon" width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3 1.5h5.5L11 4v8a.5.5 0 0 1-.5.5h-7A.5.5 0 0 1 3 12V1.5Z" stroke="#fff" stroke-width="1" stroke-linejoin="round"/><path d="M8 1.5V4h3M5 7h4M5 9.2h4" stroke="#fff" stroke-width="1" stroke-linecap="round"/></svg>
                        <span>ПОЛУЧИТЬ КП</span>
                    </button>
                </div>
                <?php else: ?>
                <button class="btn cost__btn cta-btn" data-modal-load="/local/ajax/form/?WEB_FORM_ID=1&template_form=order&name_product=<?=$arResult['NAME']?>&cta_label=<?=rawurlencode('Запросить')?>">
                    <span>ЗАПРОСИТЬ СТОИМОСТЬ</span>
                </button>
                <?php endif; ?>
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

<?php
// Блок "Другие товары" — случайная подборка из ТОЙ ЖЕ категории верхнего
// уровня, что и текущий товар (не микс всех пяти). Допустимые категории
// верхнего уровня инфоблока каталога (IBLOCK_ID=11): Прачечное оборудование
// (1), Текстиль (2), Химия (4), Кухня (8), Мебель (120) — товары из прочих
// разделов (напр. "Запасные части") в блок не попадают.
$otherProductsAllowedTopSections = [1, 2, 4, 8, 120];
$otherProductsCount = 6;
$otherProducts = [];

$otherProductsTopSectionId = null;
if (!empty($arResult['SECTION']['PATH'])) {
    $otherProductsTopSection = reset($arResult['SECTION']['PATH']);
    $otherProductsTopSectionId = (int)$otherProductsTopSection['ID'];
}

if (
    $otherProductsTopSectionId
    && in_array($otherProductsTopSectionId, $otherProductsAllowedTopSections, true)
    && \Bitrix\Main\Loader::includeModule('iblock')
) {
    $otherProductsRes = CIBlockElement::GetList(
        ['RAND' => 'ASC'],
        [
            'IBLOCK_ID' => $arParams['IBLOCK_ID'],
            'SECTION_ID' => $otherProductsTopSectionId,
            'INCLUDE_SUBSECTIONS' => 'Y',
            'ACTIVE' => 'Y',
            '!ID' => $arResult['ID'],
        ],
        false,
        ['nTopCount' => $otherProductsCount],
        ['ID', 'NAME', 'DETAIL_PAGE_URL', 'PREVIEW_PICTURE', 'DETAIL_PICTURE']
    );
    while ($otherProductItem = $otherProductsRes->GetNext()) {
        $otherProducts[] = $otherProductItem;
    }
}
?>
<?php if (!empty($otherProducts)): ?>
<section class="interesting-products">
    <div class="interesting-products__inner container">
        <h3 class="interesting-products__title">Другие товары</h3>
        <div class="interesting-products__list">
            <?php foreach ($otherProducts as $otherProduct): ?>
                <?php
                $otherProductPictureId = $otherProduct['PREVIEW_PICTURE'] ?: $otherProduct['DETAIL_PICTURE'];
                $otherProductImage = CFile::ResizeImageGet(
                    $otherProductPictureId,
                    ['width' => 600, 'height' => 600],
                    BX_RESIZE_IMAGE_PROPORTIONAL,
                    true
                );
                $otherProductImageX2 = CFile::ResizeImageGet(
                    $otherProductPictureId,
                    ['width' => 1200, 'height' => 1200],
                    BX_RESIZE_IMAGE_PROPORTIONAL,
                    true
                );
                $otherProductImageSrc = $otherProductImage['src'] ?? '';
                $otherProductImageSrcX2 = $otherProductImageX2['src'] ?? '';
                ?>
                <a class="catalog-card" href="<?= $otherProduct['DETAIL_PAGE_URL'] ?>">
                    <div class="image-wrapper">
                        <picture>
                            <source srcset="<?= $otherProductImageSrc ?>, <?= $otherProductImageSrcX2 ?> 2x" type="image/webp">
                            <img src="<?= $otherProductImageSrc ?>" srcset="<?= $otherProductImageSrc ?>, <?= $otherProductImageSrcX2 ?> 2x" alt="<?= htmlspecialcharsbx($otherProduct['NAME']) ?>">
                        </picture>
                    </div>
                    <div class="catalog-card__content">
                        <p class="catalog-card__title"><?= htmlspecialcharsbx($otherProduct['NAME']) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

