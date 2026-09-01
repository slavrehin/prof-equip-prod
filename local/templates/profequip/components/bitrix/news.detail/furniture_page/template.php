<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
?>

<?
// Получаем все свойства элемента
$props = $arResult['PROPERTIES'];
?>

<?php if (!empty($props['HERO_BG']['VALUE'])): ?>
<section class="furniture-hero">
    <div class="furniture-hero__bg">
        <picture>
            <source srcset="<?=CFile::GetPath($props['HERO_BG']['VALUE'])?>" type="image/webp">
            <img src="<?=CFile::GetPath($props['HERO_BG']['VALUE'])?>" alt="bg">
        </picture>
    </div>
    <div class="furniture-hero__inner container">
        <div class="breadcrumbs">
                    <div class="breadcrumbs__inner container"><a class="breadcrumb__link" href="/">Главная </a><a class="breadcrumb__link">/ Мебель</a></div>
                </div>
        <h1 class="furniture-hero__title"><?=$props['HERO_TITLE']['VALUE']?></h1>
        <p class="furniture-hero__descr"><?=$props['HERO_DESCR']['VALUE']?></p>
        <button class="btn gradient consultation__btn" type="button" data-modal-load="/local/ajax/form/?WEB_FORM_ID=3&template_form=consultation-modal">
            <span class="btn__text">Получить консультацию</span>
        </button>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($props['ABOUT_ICON_1']['VALUE'])): ?>
<div class="furniture-about">
    <div class="furniture-about__inner container">
        <div class="furniture-about__features">
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <?php if (!empty($props['ABOUT_ICON_'.$i]['VALUE'])): ?>
                <div class="feature__item">
                    <img src="<?=CFile::GetPath($props['ABOUT_ICON_'.$i]['VALUE'])?>" alt="feature icon">
                    <p><?=$props['ABOUT_DESC_'.$i]['VALUE']?></p>
                </div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <div class="furniture-about__content">
            <div class="furniture-about__content-text">
                <p><?=$props['ABOUT_TEXT']['VALUE']?></p>
            </div>
            <?php if (!empty($props['ABOUT_IMAGE']['VALUE'])): ?>
            <div class="image-wrapper">
                <picture>
                    <source srcset="<?=CFile::GetPath($props['ABOUT_IMAGE']['VALUE'])?>" type="image/webp">
                    <img src="<?=CFile::GetPath($props['ABOUT_IMAGE']['VALUE'])?>" alt="feature image">
                </picture>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($props['PLACES_IMAGE_1']['VALUE'])): ?>
<section class="furniture-places">
    <div class="furniture-places__inner container">
        <h2 class="furniture-places__title">Мягкая мебель</h2>
        <div class="furniture-places__list">
            <?php for ($i = 1; $i <= 3; $i++): ?>
                <?php if (!empty($props['PLACES_IMAGE_'.$i]['VALUE'])): ?>
                <div class="furniture-places__item">
                    <div class="image-wrapper">
                        <picture>
                            <source srcset="<?=CFile::GetPath($props['PLACES_IMAGE_'.$i]['VALUE'])?>" type="image/webp">
                            <img src="<?=CFile::GetPath($props['PLACES_IMAGE_'.$i]['VALUE'])?>" alt="place">
                        </picture>
                    </div>
                    <div class="furniture-places__item-content">
                        <p class="title"><?=$props['PLACES_TITLE_'.$i]['VALUE']?></p>
                        <p class="descr"><?=$props['PLACES_DESC_'.$i]['VALUE']?></p>
                    </div>
                </div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($props['CABINET_IMAGE']['VALUE'])): ?>
<section class="furniture-cabinet">
    <div class="furniture-cabinet__inner container">
        <h2 class="furniture-cabinet__title">Корпусная мебель</h2>
        <div class="furniture-cabinet__content">
            <?php if (!empty($props['CABINET_IMAGE']['VALUE'])): ?>
            <div class="image-wrapper">
                <picture>
                    <source srcset="<?=CFile::GetPath($props['CABINET_IMAGE']['VALUE'])?>" type="image/webp">
                    <img src="<?=CFile::GetPath($props['CABINET_IMAGE']['VALUE'])?>" alt="cabinet">
                </picture>
            </div>
            <?php endif; ?>
            <div class="furniture-cabinet__features">
                <p class="title"><?=$props['CABINET_TITLE']['VALUE']?></p>
                <p class="descr"><?=$props['CABINET_DESCR']['VALUE']?></p>
                <div class="furniture-cabinet__features-list">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <?php if (!empty($props['CABINET_FEATURE_ICON_'.$i]['VALUE'])): ?>
                        <div class="furniture-cabinet__feature__item">
                            <img src="<?=CFile::GetPath($props['CABINET_FEATURE_ICON_'.$i]['VALUE'])?>" alt="cabinet icon">
                            <p><?=$props['CABINET_FEATURE_TEXT_'.$i]['VALUE']?></p>
                        </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <button class="btn black" data-modal-load="/local/ajax/form/?WEB_FORM_ID=3&template_form=consultation-modal">Получить консультацию</button>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($props['CASES_SLIDER_IMAGES']['VALUE'])): ?>
<section class="furniture-cases">
    <div class="furniture-cases__inner container">
        <h2 class="furniture-cases__title">Примеры мебели</h2>
        <div class="furniture-cases-swiper">
            <div class="swiper-wrapper">
                <?php if (is_array($props['CASES_SLIDER_IMAGES']['VALUE'])): ?>
                    <?php foreach ($props['CASES_SLIDER_IMAGES']['VALUE'] as $imageId): ?>
                    <div class="swiper-slide">
                        <div class="image-wrapper">
                            <picture>
                                <source srcset="<?=CFile::GetPath($imageId)?>" type="image/webp">
                                <img src="<?=CFile::GetPath($imageId)?>" alt="case">
                            </picture>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="navigation">
                <button class="btn furniture-cases__prev" type="button">
                    <svg>
                        <use xlink:href="/local/layout/dist/assets/img/sprite.svg#arrow-swiper-left"></use>
                    </svg>
                </button>
                <button class="btn furniture-cases__next" type="button">
                    <svg>
                        <use xlink:href="/local/layout/dist/assets/img/sprite.svg#arrow-swiper-right"></use>
                    </svg>
                </button>
            </div>
        </div>
        <div class="furniture-cases__content">
            <?if ($props['CASES_TEXT']['VALUE']):?>
            <div class="furniture-cases__content-text">
                <p><?=$props['CASES_TEXT']['VALUE']['TEXT']?></p>
                <button class="btn black consultation__btn" type="button" data-modal-load="/local/ajax/form/?WEB_FORM_ID=3&template_form=consultation-modal">
                    <span class="btn__text">Получить консультацию</span>
                </button>
            </div>
            <?endif;?>
            <?php if (!empty($props['CASES_ADDITIONAL_IMAGE']['VALUE'])): ?>
            <div class="furniture-cases__content-image">
                <div class="image-wrapper">
                    <picture>
                        <source srcset="<?=CFile::GetPath($props['CASES_ADDITIONAL_IMAGE']['VALUE'])?>" type="image/webp">
                        <img src="<?=CFile::GetPath($props['CASES_ADDITIONAL_IMAGE']['VALUE'])?>" alt="case">
                    </picture>
                </div>
                <?if ($props['CASES_ADDITIONAL_TEXT']['VALUE']):?>
                <p><?=$props['CASES_ADDITIONAL_TEXT']['VALUE']['TEXT']?></p>
                <?endif;?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($props['SUPPLIERS_IMAGES']['VALUE'])): ?>
<section class="furniture-supliers">
    <div class="furniture-supliers__inner container">
        <h2 class="furniture-supliers__title">Поставщики</h2>
        <div class="furniture-supliers__list">
            <?php if (is_array($props['SUPPLIERS_IMAGES']['VALUE'])): ?>
                <?php foreach ($props['SUPPLIERS_IMAGES']['VALUE'] as $imageId): ?>
                <div class="image-wrapper">
                    <picture>
                        <source srcset="<?=CFile::GetPath($imageId)?>" type="image/webp">
                        <img src="<?=CFile::GetPath($imageId)?>" alt="supplier">
                    </picture>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>