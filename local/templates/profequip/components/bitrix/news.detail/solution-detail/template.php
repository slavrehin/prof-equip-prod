<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult["ID"])) return;
?>

    <?php if (!empty($arResult["PREVIEW_TEXT"])): ?>
        <div class="complete-solution__preview complete-solution-inner-content">
            <?= $arResult["~PREVIEW_TEXT"] ?>
        </div>
    <?php endif; ?>

    <?php 
    // Галлерея изображений (свойство GALLERY)
    if (!empty($arResult["PROPERTIES"]["GALLERY"]["VALUE"])): 
        $gallery = $arResult["PROPERTIES"]["GALLERY"]["VALUE"];
    ?>
        <div class="text-block__images-swiper">
            <div class="swiper-wrapper">
                <?php foreach ($gallery as $imageId): 
                    $image = CFile::GetFileArray($imageId);
                    if ($image):
                        // Ресайз для WebP и обычных изображений
                        $width = 800;
                        $height = 600;
                        $width2x = 1600;
                        $height2x = 1200;

                        $resizedImage = CFile::ResizeImageGet(
                            $image,
                            array("width" => $width, "height" => $height),
                            BX_RESIZE_IMAGE_PROPORTIONAL,
                            true
                        );
                        
                        $resizedImage2x = CFile::ResizeImageGet(
                            $image,
                            array("width" => $width2x, "height" => $height2x),
                            BX_RESIZE_IMAGE_PROPORTIONAL,
                            true
                        );
                        
                ?>
                        <div class="image-wrapper swiper-slide">
                            <picture>
                                <source srcset="<?= $resizedImage["src"] ?> 1x, <?= $resizedImage2x["src"] ?> 2x" type="image/webp">
                                <img src="<?= $resizedImage["src"] ?>" 
                                     srcset="<?= $resizedImage["src"] ?> 1x, <?= $resizedImage2x["src"] ?> 2x" 
                                     alt="<?= $arResult["NAME"] ?>"
                                     width="<?= $width ?>" 
                                     height="<?= $height ?>">
                            </picture>
                        </div>
                <?php 
                    endif;
                endforeach; ?>
            </div>
            <div class="pagination"></div>
            <div class="navigation">
                <button class="btn text-block__prev" type="button">
                    <svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-left"></use>
                    </svg>
                </button>
                <button class="btn text-block__next" type="button">
                    <svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-right"></use>
                    </svg>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <?php 
    if (!empty($arResult["PROPERTIES"]["TEXT_BLOCKS"]["VALUE"])): 
        $textBlocks = $arResult["PROPERTIES"]["TEXT_BLOCKS"]["~VALUE"];
        $textBlocksDesc = $arResult["PROPERTIES"]["TEXT_BLOCKS"]["DESCRIPTION"];
    ?>
        <div class="text-block__info">
            <?php foreach ($textBlocks as $index => $blockText): 
                $blockTitle = $textBlocksDesc[$index];
            ?>
                <div class="info__item">
                    <h2 class="info__title"><?= htmlspecialcharsbx($blockTitle) ?></h2>
                    <div class="info__content">
                        <?= $blockText["TEXT"] ?? $blockText ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>



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


    <?php if (!empty($arResult["DETAIL_TEXT"])): ?>
        <div class="complete-solution__detail complete-solution-inner-content">
            <?= $arResult["~DETAIL_TEXT"] ?>
        </div>
    <?php endif; ?>

