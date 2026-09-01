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
    // Обычная версия (1920x1080 для hero)
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
            // Обычная версия для галереи (800x600)
            $arImage = CFile::ResizeImageGet(
                $fileId,
                array('width' => 800, 'height' => 800),
                BX_RESIZE_IMAGE_PROPORTIONAL,
                true
            );
            
            // Retina версия (1600x1200)
            $arImage2x = CFile::ResizeImageGet(
                $fileId,
                array('width' => 1600, 'height' => 1200),
                BX_RESIZE_IMAGE_PROPORTIONAL,
                true
            );
            
            $gallery[] = array(
                'SRC' => $arImage['src'],
                'SRC_2X' => $arImage2x['src'],
                'ALT' => $arResult["NAME"]
            );
        }
    }
}

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

?>


            <? if (!empty($heroImageSrc)): ?>
            <div class="image-wrapper">
                <picture>
                    <source srcset="<?=$heroImageSrc?> 1x, <?=$heroImageSrc2x?> 2x" type="image/webp">
                    <img src="<?=$heroImageSrc?>" 
                         srcset="<?=$heroImageSrc?> 1x, <?=$heroImageSrc2x?> 2x" 
                         alt="<?=htmlspecialchars($arResult["NAME"])?>"
                         loading="lazy">
                </picture>
            </div>
            <? endif; ?>
            
            <div class="portfolio-inner-hero__text col-xl-6">
                <?=$arResult["DETAIL_TEXT"]?>
            </div>
            
            
            <? if (!empty($infoItems)): ?>
            <div class="portfolio-inner-hero__info">

                <? foreach ($infoItems as $info): ?>
                    <p>
                        <? if (!empty($info['TITLE'])): ?>
                        <strong><?=htmlspecialchars($info['TITLE'])?>:</strong>
                        <? endif; ?>
                        <?=$info['TEXT']?>
                    </p>
                <? endforeach; ?>
            </div>
            <? endif; ?>

        </div>
    </div>
</section>

<? if (!empty($gallery)): ?>
<div class="portfolio-swiper-wrapper">
    <div class="portfolio-swiper-wrapper__inner container">
        <div class="portfolio-swiper">
            <div class="swiper-wrapper">
                <? foreach ($gallery as $index => $image): ?>
                <div class="swiper-slide">
                    <a class="image-wrapper" href="<?=$image['SRC_2X']?>" data-fancybox="portfolio">
                        <picture>
                            <source srcset="<?=$image['SRC']?> 1x, <?=$image['SRC_2X']?> 2x" type="image/webp">
                            <img src="<?=$image['SRC']?>" 
                                 srcset="<?=$image['SRC']?> 1x, <?=$image['SRC_2X']?> 2x" 
                                 alt="<?=$image['ALT']?> - фото <?=($index + 1)?>"
                                 loading="lazy">
                        </picture>
                    </a>
                </div>
                <? endforeach; ?>
            </div>
            
            <? if (count($gallery) > 1): ?>
            <div class="navigation">
                <button class="btn portfolio__prev" type="button">
                    <svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-left"></use>
                    </svg>
                </button>
                <button class="btn portfolio__next" type="button">
                    <svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-right"></use>
                    </svg>
                </button>
            </div>
            <? endif; ?>
        </div>
    </div>
</div>
<? endif; ?>
<section class="text-block">
    <div class="text-block__inner container">
            <?php if (!empty($arResult['CATALOG_1']['ITEMS'])): ?>
            <div class="blog-about-catalog">
                <h2><?= $arResult['CATALOG_1']['TITLE'] ?></h2>
                <div class="blog-catalog-swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($arResult['CATALOG_1']['ITEMS'] as $item): ?>
                        <div class="swiper-slide">
                            <div class="image-wrapper">
                                <picture>
                                    <?php if ($item['PICTURE_SRC']): ?>
                                    <source srcset="<?= $item['PICTURE_SRC'] ?>" type="image/webp">
                                    <img src="<?= $item['PICTURE_SRC'] ?>" srcset="<?= $item['PICTURE_SRC'] ?>, <?= $item['PICTURE_SRC'] ?> 2x" alt="<?= $item['PICTURE_ALT'] ?>">
                                    <?php endif; ?>
                                </picture>
                            </div>
                            <div class="catalog-card__content">
                                <p class="title"><?= $item['NAME'] ?></p>
                                <button class="btn cost__btn" data-modal-load="/local/ajax/form/?WEB_FORM_ID=1&template_form=order&name_product=<?=$item['NAME']?>"><span>ЗАПРОСИТЬ СТОИМОСТЬ</span></button>
                                <a class="btn cost__btn about__btn" href="<?= $item['URL'] ?>"><span>Подробнее</span></a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($arResult['CATALOG_1']['ITEMS']) > 1): ?>
                    <div class="navigation">
                        <button class="btn blog-catalog__prev" type="button">
                            <svg><use xlink:href="<?= LAYOUT_DIR ?>assets/img/sprite.svg#arrow-swiper-left"></use></svg>
                        </button>
                        <button class="btn blog-catalog__next" type="button">
                            <svg><use xlink:href="<?= LAYOUT_DIR ?>assets/img/sprite.svg#arrow-swiper-right"></use></svg>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($arResult['CATALOG_2']['ITEMS'])): ?>
            <div class="blog-about-catalog">
                <h2><?= $arResult['CATALOG_2']['TITLE'] ?></h2>
                <div class="blog-catalog-swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($arResult['CATALOG_2']['ITEMS'] as $item): ?>
                        <div class="swiper-slide">
                            <div class="image-wrapper">
                                <picture>
                                    <?php if ($item['PICTURE_SRC']): ?>
                                    <source srcset="<?= $item['PICTURE_SRC'] ?>" type="image/webp">
                                    <img src="<?= $item['PICTURE_SRC'] ?>" srcset="<?= $item['PICTURE_SRC'] ?>, <?= $item['PICTURE_SRC'] ?> 2x" alt="<?= $item['PICTURE_ALT'] ?>">
                                    <?php endif; ?>
                                </picture>
                            </div>
                            <div class="catalog-card__content">
                                <p class="title"><?= $item['NAME'] ?></p>
                                <button class="btn cost__btn" data-modal-load="/local/ajax/form/?WEB_FORM_ID=1&template_form=order&name_product=<?=$item['NAME']?>"><span>ЗАПРОСИТЬ СТОИМОСТЬ</span></button>
                                <a class="btn cost__btn about__btn" href="<?= $item['URL'] ?>"><span>Подробнее</span></a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($arResult['CATALOG_2']['ITEMS']) > 1): ?>
                    <div class="navigation">
                        <button class="btn blog-catalog__prev" type="button">
                            <svg><use xlink:href="<?= LAYOUT_DIR ?>assets/img/sprite.svg#arrow-swiper-left"></use></svg>
                        </button>
                        <button class="btn blog-catalog__next" type="button">
                            <svg><use xlink:href="<?= LAYOUT_DIR ?>assets/img/sprite.svg#arrow-swiper-right"></use></svg>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

    </div>
</section>

