<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

$props = $arResult["PROPERTIES"];

// Ресайз главного изображения
$heroImage = $arResult["DETAIL_PICTURE"];
$heroImageSrc = '';
$heroImageSrc2x = '';
$heroImageWebp = '';
$heroImageWebp2x = '';

if (!empty($heroImage["ID"])) {
    // Обычная версия
    $arHeroImage = CFile::ResizeImageGet(
        $heroImage["ID"],
        array('width' => 1920, 'height' => 1080),
        BX_RESIZE_IMAGE_PROPORTIONAL,
        true
    );
    $heroImageSrc = $arHeroImage['src'];
    
    // Retina версия
    $arHeroImage2x = CFile::ResizeImageGet(
        $heroImage["ID"],
        array('width' => 3840, 'height' => 2160),
        BX_RESIZE_IMAGE_PROPORTIONAL,
        true
    );
    $heroImageSrc2x = $arHeroImage2x['src'];
}

// Получаем галерею из свойства GALLERY
$gallery = array();
if (!empty($props['GALLERY']['VALUE'])) {
    $galleryItems = $props['GALLERY']['VALUE'];
    if (!is_array($galleryItems)) {
        $galleryItems = array($galleryItems);
    }
    
    foreach ($galleryItems as $fileId) {
        if ($fileId > 0) {
            // Обычная версия для галереи
            $arImage = CFile::ResizeImageGet(
                $fileId,
                array('width' => 800, 'height' => 800),
                BX_RESIZE_IMAGE_PROPORTIONAL,
                true
            );
            
            // Retina версия
            $arImage2x = CFile::ResizeImageGet(
                $fileId,
                array('width' => 1600, 'height' => 1200),
                BX_RESIZE_IMAGE_PROPORTIONAL,
                true
            );
            
            // Для webp
            $arImageWebp = CFile::ResizeImageGet(
                $fileId,
                array('width' => 800, 'height' => 800),
                BX_RESIZE_IMAGE_PROPORTIONAL,
                true,
                false,
                false,
                100,
                'image/webp'
            );
            
            $arImageWebp2x = CFile::ResizeImageGet(
                $fileId,
                array('width' => 1600, 'height' => 1200),
                BX_RESIZE_IMAGE_PROPORTIONAL,
                true,
                false,
                false,
                100,
                'image/webp'
            );
            
            $gallery[] = array(
                'SRC' => $arImage['src'],
                'SRC_2X' => $arImage2x['src'],
                'SRC_WEBP' => $arImageWebp['src'] ?? $arImage['src'],
                'SRC_WEBP_2X' => $arImageWebp2x['src'] ?? $arImage2x['src'],
                'ALT' => $arResult["NAME"]
            );
        }
    }
}

// Информационные пункты из свойства LIST
$infoItems = array();
if (!empty($props['LIST']['VALUE'])) {
    $infoValues = $props['LIST']['~VALUE'];
    $infoDescriptions = $props['LIST']['~DESCRIPTION'];
    
    if (is_array($infoValues) && is_array($infoDescriptions)) {
        foreach ($infoValues as $key => $value) {
            $infoItems[] = array(
                'TITLE' => $infoDescriptions[$key] ?? '',
                'TEXT' => $value["TEXT"] ?? $value
            );
        }
    }
}

// Получаем блоки из свойства BLOCKS (аналогично LIST)
$blockItems = array();
if (!empty($props['BLOCKS']['VALUE'])) {
    $blockValues = $props['BLOCKS']['~VALUE'];
    $blockDescriptions = $props['BLOCKS']['~DESCRIPTION'];
    
    if (is_array($blockValues) && is_array($blockDescriptions)) {
        foreach ($blockValues as $key => $value) {
            // Проверяем, есть ли заголовок (DESCRIPTION)
            $title = $blockDescriptions[$key] ?? '';
            
            // Получаем текст
            $text = '';
            if (is_array($value) && isset($value['TEXT'])) {
                $text = $value['TEXT'];
            } elseif (is_string($value)) {
                $text = $value;
            }
            
            // Если текст не пустой, добавляем блок
            if (!empty($text)) {
                $blockItems[] = array(
                    'TITLE' => $title,
                    'TEXT' => $text
                );
            }
        }
    }
}

// Получаем заголовок для блока info
$infoTitle = $props['INFO_TITLE']['VALUE'] ?? '';

// Получаем детальный текст для старого шаблона
$detailText = $arResult["DETAIL_TEXT"] ?? '';

// Проверяем, есть ли блоки из BLOCKS
$hasBlocks = !empty($blockItems);
// Проверяем, есть ли DETAIL_TEXT для старого шаблона
$hasDetailText = !empty($detailText);
?>


            
            <!-- ГАЛЕРЕЯ СВОЙСТВО GALLERY -->
            <? if (!empty($gallery)): ?>
            <div class="project-content__swiper-wrapper">
                <div class="project-content__swiper">
                    <div class="swiper-wrapper">
                        <? foreach ($gallery as $index => $image): ?>
                        <div class="swiper-slide">
                            <a class="image-wrapper" href="<?=$image['SRC_2X']?>" data-fancybox="project">
                                <picture>
                                    <source srcset="<?=$image['SRC_WEBP']?> 1x, <?=$image['SRC_WEBP_2X']?> 2x" type="image/webp">
                                    <img src="<?=$image['SRC']?>" 
                                         srcset="<?=$image['SRC']?> 1x, <?=$image['SRC_2X']?> 2x" 
                                         alt="<?=htmlspecialchars($image['ALT'])?> - фото <?=($index + 1)?>"
                                         loading="lazy">
                                </picture>
                            </a>
                        </div>
                        <? endforeach; ?>
                    </div>
                    <? if (count($gallery) > 1): ?>
                    <div class="navigation">
                        <button class="btn swiper-prev" type="button">
                            <svg>
                                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-left"></use>
                            </svg>
                        </button>
                        <button class="btn swiper-next" type="button">
                            <svg>
                                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-right"></use>
                            </svg>
                        </button>
                    </div>
                    <? endif; ?>
                </div>
                
                <!-- THUMBS для галереи -->
                <div class="project-content__swiper-thumbs">
                    <div class="swiper-wrapper">
                        <? foreach ($gallery as $index => $image): ?>
                        <div class="swiper-slide">
                            <div class="image-wrapper">
                                <picture>
                                    <source srcset="<?=$image['SRC_WEBP']?> 1x, <?=$image['SRC_WEBP_2X']?> 2x" type="image/webp">
                                    <img src="<?=$image['SRC']?>" 
                                         srcset="<?=$image['SRC']?> 1x, <?=$image['SRC_2X']?> 2x" 
                                         alt="<?=htmlspecialchars($image['ALT'])?>"
                                         loading="lazy">
                                </picture>
                            </div>
                        </div>
                        <? endforeach; ?>
                    </div>
                </div>
            </div>
            <? endif; ?>
            
            <!-- ИНФОРМАЦИОННЫЙ БЛОК (свойство LIST) -->
            <? if (!empty($infoItems)): ?>
            <div class="project-content__info">
                <h3 class="info__title"><?=htmlspecialchars($infoTitle)?></h3>
                <div class="info__list">
                    <? foreach ($infoItems as $info): ?>
                    <div class="info__item">
                        <p class="label"><?=htmlspecialchars($info['TITLE'])?>:</p>
                        <p class="value"><?=$info['TEXT']?></p>
                    </div>
                    <? endforeach; ?>
                </div>
            </div>
            <? endif; ?>
            
            <!-- ТЕКСТОВЫЙ БЛОК С АККОРДЕОНОМ -->
            <!-- Приоритет: сначала BLOCKS, если их нет - DETAIL_TEXT -->
            <? if ($hasBlocks || $hasDetailText): ?>
            <div class="project-content__text accordion">
                
                <? if ($hasBlocks): ?>
                    <!-- БЛОКИ ИЗ СВОЙСТВА BLOCKS -->
                    <? foreach ($blockItems as $key=>$block): ?>
                    <div class="accordion__item <?if ($key == 0):?>active<?endif;?>">
                        <button class="btn accordion__title" type="button">
                            <h2><?=htmlspecialchars($block['TITLE'] ?: 'Блок')?></h2>
                            <div class="accordion__icon">
                                <svg>
                                    <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#accordion-arrow2"></use>
                                </svg>
                            </div>
                        </button>
                        <div class="accordion__content">
                            <?=$block['TEXT']?>
                        </div>
                    </div>
                    <? endforeach; ?>
                <? endif; ?>
                
                <? if ($hasDetailText && !$hasBlocks): ?>
                    <!-- ДЛЯ СТАРОГО ШАБЛОНА - весь DETAIL_TEXT в одном блоке -->
                    <div class="accordion__item active">
                        <button class="btn accordion__title" type="button">
                            <h2>Описание проекта</h2>
                            <div class="accordion__icon">
                                <svg>
                                    <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#accordion-arrow2"></use>
                                </svg>
                            </div>
                        </button>
                        <div class="accordion__content">
                            <?=$detailText?>
                        </div>
                    </div>
                <? endif; ?>
                
            </div>
            <? endif; ?>
            
            <!-- КАТАЛОГ 1 (свойство CATALOG_PRODUCT_1) -->
            <? if (!empty($arResult['CATALOG_1']['ITEMS'])): ?>
            <div class="project-content__products-swiper-wrapper accordion">
                <div class="accordion__item">
                    <button class="btn accordion__title" type="button">
                        <h3><?=htmlspecialchars($arResult['CATALOG_1']['TITLE'])?></h3>
                        <div class="accordion__icon">
                            <svg>
                                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#accordion-arrow2"></use>
                            </svg>
                        </div>
                    </button>
                    <div class="accordion__content">
                        <div class="project-content__products-swiper">
                            <div class="swiper-wrapper">
                                <? foreach ($arResult['CATALOG_1']['ITEMS'] as $item): ?>
                                <a class="catalog-card swiper-slide" href="<?=$item['URL']?>">
                                    <? if ($item['PICTURE_SRC']): ?>
                                    <div class="image-wrapper">
                                        <picture>
                                            <source srcset="<?=$item['PICTURE_SRC']?>" type="image/webp">
                                            <img src="<?=$item['PICTURE_SRC']?>" 
                                                 srcset="<?=$item['PICTURE_SRC']?>, <?=$item['PICTURE_SRC']?> 2x" 
                                                 alt="<?=htmlspecialchars($item['PICTURE_ALT'])?>"
                                                 loading="lazy">
                                        </picture>
                                    </div>
                                    <? endif; ?>
                                    <div class="catalog-card__content">
                                        <p class="catalog-card__title"><?=htmlspecialchars($item['NAME'])?></p>
                                        <button class="btn cost__btn" type="button" data-modal-load="/local/ajax/form/?WEB_FORM_ID=1&template_form=order&name_product=<?=urlencode($item['NAME'])?>">
                                            <span>ЗАПРОСИТЬ СТОИМОСТЬ</span>
                                        </button>
                                    </div>
                                </a>
                                <? endforeach; ?>
                            </div>
                            <? if (count($arResult['CATALOG_1']['ITEMS']) > 1): ?>
                            <div class="navigation">
                                <button class="btn swiper-prev" type="button">
                                    <svg>
                                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-left"></use>
                                    </svg>
                                </button>
                                <button class="btn swiper-next" type="button">
                                    <svg>
                                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-right"></use>
                                    </svg>
                                </button>
                            </div>
                            <div class="pagination"></div>
                            <? endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <? endif; ?>
            
            <!-- КАТАЛОГ 2 (свойство CATALOG_PRODUCT_2) -->
            <? if (!empty($arResult['CATALOG_2']['ITEMS'])): ?>
            <div class="project-content__products-swiper-wrapper accordion">
                <div class="accordion__item">
                    <button class="btn accordion__title" type="button">
                        <h3><?=htmlspecialchars($arResult['CATALOG_2']['TITLE'])?></h3>
                        <div class="accordion__icon">
                            <svg>
                                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#accordion-arrow2"></use>
                            </svg>
                        </div>
                    </button>
                    <div class="accordion__content">
                        <div class="project-content__products-swiper">
                            <div class="swiper-wrapper">
                                <? foreach ($arResult['CATALOG_2']['ITEMS'] as $item): ?>
                                <a class="catalog-card swiper-slide" href="<?=$item['URL']?>">
                                    <? if ($item['PICTURE_SRC']): ?>
                                    <div class="image-wrapper">
                                        <picture>
                                            <source srcset="<?=$item['PICTURE_SRC']?>" type="image/webp">
                                            <img src="<?=$item['PICTURE_SRC']?>" 
                                                 srcset="<?=$item['PICTURE_SRC']?>, <?=$item['PICTURE_SRC']?> 2x" 
                                                 alt="<?=htmlspecialchars($item['PICTURE_ALT'])?>"
                                                 loading="lazy">
                                        </picture>
                                    </div>
                                    <? endif; ?>
                                    <div class="catalog-card__content">
                                        <p class="catalog-card__title"><?=htmlspecialchars($item['NAME'])?></p>
                                        <button class="btn cost__btn" type="button" data-modal-load="/local/ajax/form/?WEB_FORM_ID=1&template_form=order&name_product=<?=urlencode($item['NAME'])?>">
                                            <span>ЗАПРОСИТЬ СТОИМОСТЬ</span>
                                        </button>
                                    </div>
                                </a>
                                <? endforeach; ?>
                            </div>
                            <? if (count($arResult['CATALOG_2']['ITEMS']) > 1): ?>
                            <div class="navigation">
                                <button class="btn swiper-prev" type="button">
                                    <svg>
                                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-left"></use>
                                    </svg>
                                </button>
                                <button class="btn swiper-next" type="button">
                                    <svg>
                                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-right"></use>
                                    </svg>
                                </button>
                            </div>
                            <div class="pagination"></div>
                            <? endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <? endif; ?>
        </div>
    </div>

    
    <? if (!empty($arResult['BRANDS']['ITEMS'])): ?>
    <div class="project-content__brands">
        <h3 class="container"><?=htmlspecialchars($arResult['BRANDS']['TITLE'])?></h3>
        <div class="project-content__brands-list">
            <div class="project-content__brands-track">
                <? foreach ($arResult['BRANDS']['ITEMS'] as $brand): ?>
                <a href="<?=$brand['URL']?>" class="image-wrapper">
                    <? if (!empty($brand['LOGO']['SRC'])): ?>                      
                        <? if ($brand['LOGO']['IS_SVG']): ?>
                            <img src="<?=$brand['LOGO']['SRC']?>" 
                                alt="<?=htmlspecialchars($brand['ALT'])?>"
                                loading="lazy"
                                class="brand-logo-svg">
                        <? else: ?>
                            <picture>
                                <img src="<?=$brand['LOGO']['SRC']?>" 
                                    alt="<?=htmlspecialchars($brand['ALT'])?>"
                                    loading="lazy">
                            </picture>
                        <? endif; ?>
                    <? else: ?>
                        <span><?=htmlspecialchars($brand['NAME'])?></span>
                    <? endif; ?>
                </a>
                <? endforeach; ?>
            </div>
        </div>
    </div>
    <? endif; ?>
</section>