<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
$props = $arResult['PROPERTIES'];
?>

<?php if (!empty($props['HERO_SLIDER_IMAGES']['VALUE'])): ?>
<section class="hero">
    <div class="hero__swiper">
        <div class="swiper-wrapper">
            <?php if (is_array($props['HERO_SLIDER_IMAGES']['VALUE'])): ?>
                <?php foreach ($props['HERO_SLIDER_IMAGES']['VALUE'] as $imageId): 
                    $image = CFile::GetFileArray($imageId);
                    if (!$image) continue;
                    
                    $src = $image['SRC'];
                ?>
                <div class="swiper-slide">
                    <picture>
                        <source srcset="<?=$src?>" type="image/webp">
                        <img src="<?=$src?>" srcset="<?=$src?>" alt="hero slide">
                    </picture>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="hero__inner container">
        <h1 class="hero__title"><?=$props['HERO_TITLE']['VALUE'] ?: 'Оснащение'?></h1>
        
        <?php if (!empty($props['HERO_BULLET_TEXTS']['VALUE'])): ?>
        <div class="hero__bullets">
            <?php 
            $bulletTexts = is_array($props['HERO_BULLET_TEXTS']['VALUE']) 
                ? $props['HERO_BULLET_TEXTS']['VALUE'] 
                : [$props['HERO_BULLET_TEXTS']['VALUE']];
            
            foreach ($bulletTexts as $index => $text): 
                $activeClass = $index === 0 ? 'active' : '';
            ?>
            <button class="btn hero__bullet <?=$activeClass?>" type="button">
                <svg>
                    <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#divider"></use>
                </svg>
                <span class="btn__text"><?=$text?></span>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="hero__features">
            <button class="btn gradient consultation__btn" type="button" data-modal-load="/local/ajax/form/?WEB_FORM_ID=3&template_form=consultation-modal">
                <span class="btn__text">Получить консультацию</span>
            </button>
            <a href="/portfolio/" class="btn outline projects__btn" type="button">
                <span class="eye__icon">
                    <svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#eye"></use>
                    </svg>
                </span>
                <span class="btn__text">Смотреть проекты</span>
                <span class="arrow__icon">
                    <svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-down"></use>
                    </svg>
                </span>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>
<?/*
    <div class="about-alert">
        <div class="about-alert__inner container">
            <?if ($props['LOGO']['VALUE']):?>
            <div class="about-alert__image">
                <picture>
                    <source srcset="<?=CFile::GetFileArray($props['LOGO']['VALUE'])["SRC"];?>, <?=CFile::GetFileArray($props['LOGO']['VALUE'])["SRC"];?> 2x" type="image/webp">
                    <img src="<?=CFile::GetFileArray($props['LOGO']['VALUE'])["SRC"];?>" srcset="<?=CFile::GetFileArray($props['LOGO']['VALUE'])["SRC"];?>, <?=CFile::GetFileArray($props['LOGO']['VALUE'])["SRC"];?> 2x" alt="about">
                </picture>
            </div>
            <?endif;?>
            <?if ($props['ABOUT']['VALUE']):?>
            <div class="about-alert__text">
                <?=$props['ABOUT']['~VALUE']["TEXT"];?>
            </div>
            <?endif;?>
        </div>
    </div>
    */?>