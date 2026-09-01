<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

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

?>



<? if (!empty($arResult['PROPERTIES']['FAQ_TITLE']['VALUE'])): ?>
<section class="textile-faq">
    <div class="textile-faq__inner container">
        <h2 class="textile-faq__title"><?= $arResult['PROPERTIES']['FAQ_TITLE']['VALUE'] ?></h2>
        
        <div class="textile-faq__content">
            <? if (!empty($arResult['PROPERTIES']['FAQ_IMAGE']['VALUE'])): 
                $faqImage = CFile::GetFileArray($arResult['PROPERTIES']['FAQ_IMAGE']['VALUE']);
            ?>
                <div class="image-wrapper">
                    <picture>
                        <source srcset="<?= $faqImage['SRC'] ?>?webp, <?= $faqImage['SRC'] ?>?webp 2x" type="image/webp">
                        <img src="<?= $faqImage['SRC'] ?>" 
                             srcset="<?= $faqImage['SRC'] ?>, <?= $faqImage['SRC'] ?> 2x" 
                             alt="faq img">
                    </picture>
                </div>
            <? endif; ?>
            
            <div class="textile-faq__accordion-wrapper">
                <? if (!empty($arResult['PROPERTIES']['FAQ_TEXT']['VALUE']['TEXT'])): ?>
                    <?= $arResult['PROPERTIES']['FAQ_TEXT']['~VALUE']['TEXT'] ?>
                <? endif; ?>
                
                <!-- Аккордеон -->
                <? if (!empty($arResult['PROPERTIES']['FAQ_ITEMS']['VALUE'])): 
                    $faqItems = $arResult['PROPERTIES']['FAQ_ITEMS']['~VALUE'];
                ?>
                    <div class="textile-faq__accordion accordion">
                        <? if (is_array($faqItems)): 
                            foreach ($faqItems as $key=>$item): ?>
                                <div class="accordion__item">
                                    <button class="btn accordion__title">
                                        <span class="accordion__title__text"><?=$arResult['PROPERTIES']['FAQ_ITEMS']['DESCRIPTION'][$key]; ?></span>
                                        <span class="plus-icon"><span></span><span></span></span>
                                    </button>
                                    <div class="accordion__content">
                                        <?= $item['TEXT'] ?? '' ?>
                                    </div>
                                </div>
                            <? endforeach; 
                        endif; ?>
                    </div>
                <? endif; ?>
                
                <? if (!empty($arResult['PROPERTIES']['MAIN_BUTTON_LINK']['VALUE'])): ?>
                    <a class="btn cost__btn" href="<?= $arResult['PROPERTIES']['MAIN_BUTTON_LINK']['VALUE'] ?>">
                        <span>В КАТАЛОГ</span>
                    </a>
                <? endif; ?>
            </div>
        </div>
    </div>
</section>
<? endif; ?>

<? if (!empty($arResult['PROPERTIES']['LIBRARY_TITLE']['VALUE']) || 
          !empty($arResult['PROPERTIES']['LIBRARY_IMAGES']['VALUE'])): ?>
<section class="textile-library">
    <div class="textile-library__inner container">
        <h2 class="textile-library__title">
            <?= !empty($arResult['PROPERTIES']['LIBRARY_TITLE']['VALUE']) 
                ? $arResult['PROPERTIES']['LIBRARY_TITLE']['VALUE'] 
                : 'БИБЛИОТЕКА ТЕКСТИЛЯ' ?>
        </h2>
        
        <? if (!empty($arResult['PROPERTIES']['LIBRARY_IMAGES']['VALUE'])): 
            $libraryImages = $arResult['PROPERTIES']['LIBRARY_IMAGES']['VALUE'];
            if (!is_array($libraryImages)) {
                $libraryImages = [$libraryImages];
            }
        ?>
            <div class="textile-library-swiper">
                <div class="swiper-wrapper">
                    <? foreach ($libraryImages as $imageId): 
                        $image = CFile::GetFileArray($imageId);
                        if ($image): ?>
                            <div class="swiper-slide">
                                <div class="image-wrapper">
                                    <picture>
                                        <source srcset="<?= $image['SRC'] ?>?webp, <?= $image['SRC'] ?>?webp 2x" type="image/webp">
                                        <img src="<?= $image['SRC'] ?>" 
                                             srcset="<?= $image['SRC'] ?>, <?= $image['SRC'] ?> 2x" 
                                             alt="library">
                                    </picture>
                                </div>
                            </div>
                        <? endif; ?>
                    <? endforeach; ?>
                </div>
                
                <div class="navigation">
                    <button class="btn textile-library__prev" type="button">
                        <svg><use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-left"></use></svg>
                    </button>
                    <button class="btn textile-library__next" type="button">
                        <svg><use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-right"></use></svg>
                    </button>
                </div>
            </div>
        <? endif; ?>
    </div>
</section>
<? endif; ?>

<? if (!empty($arResult['PROPERTIES']['ABOUT_TITLE']['VALUE'])): ?>
<section class="textile-about">
    <div class="textile-about__inner container">
        <div class="textile-about-container">
            <h2 class="textile-about__title"><?= $arResult['PROPERTIES']['ABOUT_TITLE']['VALUE'] ?></h2>
            
            <div class="textile-about__text-container">
                <? if (!empty($arResult['PROPERTIES']['ABOUT_SUBTITLE']['VALUE']['TEXT'])): ?>
                    <div class="textile-about__text">
                        <?= $arResult['PROPERTIES']['ABOUT_SUBTITLE']['~VALUE']['TEXT'] ?>
                    </div>
                <? endif; ?>
                
                <? if (!empty($arResult['PROPERTIES']['ABOUT_TEXT_LEFT']['VALUE']['TEXT'])): ?>
                    <div class="textile-about__text">
                        <?= $arResult['PROPERTIES']['ABOUT_TEXT_LEFT']['~VALUE']['TEXT'] ?>
                    </div>
                <? endif; ?>
                
                <? if (!empty($arResult['PROPERTIES']['ABOUT_TEXT_RIGHT']['VALUE']['TEXT'])): ?>
                    <div class="textile-about__text">
                        <?= $arResult['PROPERTIES']['ABOUT_TEXT_RIGHT']['~VALUE']['TEXT'] ?>
                    </div>
                <? endif; ?>
                
                <? if (!empty($arResult['PROPERTIES']['ABOUT_FEATURE_1_TITLE']['VALUE']) || 
                          !empty($arResult['PROPERTIES']['ABOUT_FEATURE_2_TITLE']['VALUE'])): ?>
                    <div class="textile-about__features">
                        <? if (!empty($arResult['PROPERTIES']['ABOUT_FEATURE_1_TITLE']['VALUE'])): ?>
                            <div class="feature__item">
                                <p class="feature__title"><?= $arResult['PROPERTIES']['ABOUT_FEATURE_1_TITLE']['VALUE'] ?></p>
                                <p class="feature__descr"><?= $arResult['PROPERTIES']['ABOUT_FEATURE_1_TEXT']['~VALUE'] ?></p>
                            </div>
                        <? endif; ?>
                        
                        <? if (!empty($arResult['PROPERTIES']['ABOUT_FEATURE_2_TITLE']['VALUE'])): ?>
                            <div class="feature__item">
                                <p class="feature__title"><?= $arResult['PROPERTIES']['ABOUT_FEATURE_2_TITLE']['VALUE'] ?></p>
                                <p class="feature__descr"><?= $arResult['PROPERTIES']['ABOUT_FEATURE_2_TEXT']['~VALUE'] ?></p>
                            </div>
                        <? endif; ?>
                    </div>
                <? endif; ?>
            </div>
        </div>
    </div>
</section>
<? endif; ?>
