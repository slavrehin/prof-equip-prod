<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
?>

        <div class="blog-inner-about__text">

            <?= $arResult['PREVIEW_TEXT'] ?>

            <?php
            // Блоки «Наш ассортимент»: на месте маркера <!--BLOG_CATALOG_N--> в тексте, иначе — перед текстом
            $catalogHtml = [];
            ?>
            <?php foreach (['CATALOG_1', 'CATALOG_2'] as $catKey): $cat = $arResult[$catKey] ?? []; if (empty($cat['ITEMS'])) { $catalogHtml[$catKey] = ''; continue; } ob_start(); ?>
            <div class="blog-about-catalog">
                <h2><?= $cat['TITLE'] ?></h2>
                <div class="blog-catalog-swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($cat['ITEMS'] as $item): ?>
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
                    <?php if (count($cat['ITEMS']) > 1): ?>
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
            <?php $catalogHtml[$catKey] = ob_get_clean(); endforeach; ?>
            <?php
            $detailText = (string) $arResult['DETAIL_TEXT'];
            foreach (['CATALOG_1' => 1, 'CATALOG_2' => 2] as $catKey => $n) {
                $marker = '<!--BLOG_CATALOG_' . $n . '-->';
                if (strpos($detailText, $marker) !== false) {
                    $detailText = str_replace($marker, $catalogHtml[$catKey], $detailText);
                } else {
                    echo $catalogHtml[$catKey];
                }
            }
            ?>

            <?= $detailText ?>
            <?if ($arResult['PROPERTIES']['ALERT']['~VALUE']):?>
                <div class="alert">
                    <?=$arResult['PROPERTIES']['ALERT']['~VALUE']["TEXT"];?>
                </div>
            <?endif;?>    
        </div>

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "NewsArticle",
      "headline": "<?=$arResult["NAME"];?>",
      "image": "https://<?=$_SERVER['SERVER_NAME'].CFile::GetPath($arResult["PREVIEW_PICTURE"]["ID"])?>",
      "datePublished": "<?=$arResult["ACTIVE_FROM"]?:$arResult["TIMESTAMP_X"];?>",
      "dateModified": "<?=$arResult["TIMESTAMP_X"];?>",
      "author": []
    }
    </script>