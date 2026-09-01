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




<section class="textile-info">
    <div class="textile-info__inner container">
        <h1 class="textile-info__title">
            <?= !empty($arResult['PROPERTIES']['MAIN_TITLE']['VALUE']) 
                ? $arResult['PROPERTIES']['MAIN_TITLE']['VALUE'] 
                : $arResult['NAME'] ?>
        </h1>
        
        <? if (!empty($arResult['PROPERTIES']['MAIN_TEXT_LEFT']['VALUE']['TEXT']) || 
                  !empty($arResult['PROPERTIES']['MAIN_TEXT_RIGHT']['VALUE']['TEXT'])): ?>
            <div class="textile-info__text-container">
                <? if (!empty($arResult['PROPERTIES']['MAIN_TEXT_LEFT']['VALUE']['TEXT'])): ?>
                    <div class="textile-info__text">
                        <?= $arResult['PROPERTIES']['MAIN_TEXT_LEFT']['~VALUE']['TEXT'] ?>
                    </div>
                <? endif; ?>
                
                <? if (!empty($arResult['PROPERTIES']['MAIN_TEXT_RIGHT']['VALUE']['TEXT'])): ?>
                    <div class="textile-info__text">
                        <?= $arResult['PROPERTIES']['MAIN_TEXT_RIGHT']['~VALUE']['TEXT'] ?>
                    </div>
                <? endif; ?>
            </div>
        <? endif; ?>
        
        <? if (!empty($arResult['PROPERTIES']['MAIN_BUTTON_LINK']['VALUE'])): ?>
            <a class="btn cost__btn" href="<?= $arResult['PROPERTIES']['MAIN_BUTTON_LINK']['VALUE'] ?>">
                <span><?= !empty($arResult['PROPERTIES']['MAIN_BUTTON_TEXT']['VALUE']) 
                    ? $arResult['PROPERTIES']['MAIN_BUTTON_TEXT']['VALUE'] 
                    : 'В КАТАЛОГ' ?></span>
            </a>
        <? endif; ?>
    </div>


    <div class="textile-info__items">
        <!-- Item 1 -->
        <? if (!empty($arResult['PROPERTIES']['ITEM_1_TITLE']['VALUE'])): ?>
        <div class="textile-info__item">
            <div class="textile-info__item__inner container">
                <div class="image-wrapper-container">
                    <? if (!empty($arResult['PROPERTIES']['ITEM_1_IMAGE']['VALUE'])): 
                        $image = CFile::GetFileArray($arResult['PROPERTIES']['ITEM_1_IMAGE']['VALUE']);
                    ?>
                        <div class="image-wrapper">
                            <picture>
                                <source srcset="<?= $image['SRC'] ?>?webp, <?= $image['SRC'] ?>?webp 2x" type="image/webp">
                                <img src="<?= $image['SRC'] ?>" 
                                     srcset="<?= $image['SRC'] ?>, <?= $image['SRC'] ?> 2x" 
                                     alt="<?= !empty($arResult['PROPERTIES']['ITEM_1_TITLE']['VALUE']) 
                                        ? $arResult['PROPERTIES']['ITEM_1_TITLE']['VALUE'] 
                                        : 'textile' ?>">
                            </picture>
                        </div>
                    <? endif; ?>
                    
                    <? if (!empty($arResult['PROPERTIES']['ITEM_1_BUTTON_LINK']['VALUE'])): ?>
                        <a class="btn cost__btn" href="<?= $arResult['PROPERTIES']['ITEM_1_BUTTON_LINK']['VALUE'] ?>">
                            <span>В КАТАЛОГ</span>
                        </a>
                    <? endif; ?>
                    
                    <? if (!empty($arResult['PROPERTIES']['ITEM_1_IMAGE_TITLE']['VALUE'])): ?>
                        <p class="image-title"><?= $arResult['PROPERTIES']['ITEM_1_IMAGE_TITLE']['~VALUE'] ?></p>
                    <? endif; ?>
                </div>
                
                <div class="textile-info__item-text">
                    <p class="title"><?= $arResult['PROPERTIES']['ITEM_1_TITLE']['~VALUE'] ?></p>
                    
                    <? if (!empty($arResult['PROPERTIES']['ITEM_1_TEXT']['VALUE']['TEXT'])): ?>
                        <?= $arResult['PROPERTIES']['ITEM_1_TEXT']['~VALUE']['TEXT'] ?>
                    <? endif; ?>
                    
                    <? if (!empty($arResult['PROPERTIES']['ITEM_1_BUTTON_LINK']['VALUE']) && 
                              empty($arResult['PROPERTIES']['ITEM_1_BUTTON_POSITION']['VALUE'])): ?>
                        <a class="btn cost__btn" href="<?= $arResult['PROPERTIES']['ITEM_1_BUTTON_LINK']['VALUE'] ?>">
                            <span>В КАТАЛОГ</span>
                        </a>
                    <? endif; ?>
                </div>
            </div>
        </div>
        <? endif; ?>
        
        <!-- Item 2 -->
        <? if (!empty($arResult['PROPERTIES']['ITEM_2_TITLE']['VALUE'])): ?>
        <div class="textile-info__item">
            <div class="textile-info__item__inner container">
                <div class="image-wrapper-container">
                    <? if (!empty($arResult['PROPERTIES']['ITEM_2_IMAGE']['VALUE'])): 
                        $image = CFile::GetFileArray($arResult['PROPERTIES']['ITEM_2_IMAGE']['VALUE']);
                    ?>
                        <div class="image-wrapper">
                            <picture>
                                <source srcset="<?= $image['SRC'] ?>?webp, <?= $image['SRC'] ?>?webp 2x" type="image/webp">
                                <img src="<?= $image['SRC'] ?>" 
                                     srcset="<?= $image['SRC'] ?>, <?= $image['SRC'] ?> 2x" 
                                     alt="<?= $arResult['PROPERTIES']['ITEM_2_TITLE']['VALUE'] ?>">
                            </picture>
                        </div>
                    <? endif; ?>
                    
                    <? if (!empty($arResult['PROPERTIES']['ITEM_2_IMAGE_TITLE']['VALUE'])): ?>
                        <p class="image-title"><?= $arResult['PROPERTIES']['ITEM_2_IMAGE_TITLE']['VALUE'] ?></p>
                    <? endif; ?>
                </div>
                
                <div class="textile-info__item-text">
                    <p class="title"><?= $arResult['PROPERTIES']['ITEM_2_TITLE']['VALUE'] ?></p>
                    
                    <? if (!empty($arResult['PROPERTIES']['ITEM_2_TEXT']['VALUE']['TEXT'])): ?>
                        <?= $arResult['PROPERTIES']['ITEM_2_TEXT']['~VALUE']['TEXT'] ?>
                    <? endif; ?>
                    
                    <? if (!empty($arResult['PROPERTIES']['ITEM_2_BUTTON_LINK']['VALUE'])): ?>
                        <a class="btn cost__btn" href="<?= $arResult['PROPERTIES']['ITEM_2_BUTTON_LINK']['VALUE'] ?>">
                            <span>В КАТАЛОГ</span>
                        </a>
                    <? endif; ?>
                </div>
            </div>
        </div>
        <? endif; ?>
        
        <!-- Item 3 -->
        <? if (!empty($arResult['PROPERTIES']['ITEM_3_TITLE']['VALUE'])): ?>
        <div class="textile-info__item">
            <div class="textile-info__item__inner container">
                <div class="image-wrapper-container">
                    <? if (!empty($arResult['PROPERTIES']['ITEM_3_IMAGE']['VALUE'])): 
                        $image = CFile::GetFileArray($arResult['PROPERTIES']['ITEM_3_IMAGE']['VALUE']);
                    ?>
                        <div class="image-wrapper">
                            <picture>
                                <source srcset="<?= $image['SRC'] ?>?webp, <?= $image['SRC'] ?>?webp 2x" type="image/webp">
                                <img src="<?= $image['SRC'] ?>" 
                                     srcset="<?= $image['SRC'] ?>, <?= $image['SRC'] ?> 2x" 
                                     alt="<?= $arResult['PROPERTIES']['ITEM_3_TITLE']['VALUE'] ?>">
                            </picture>
                        </div>
                    <? endif; ?>
                    
                    <? if (!empty($arResult['PROPERTIES']['ITEM_3_IMAGE_TITLE']['VALUE'])): ?>
                        <p class="image-title"><?= $arResult['PROPERTIES']['ITEM_3_IMAGE_TITLE']['VALUE'] ?></p>
                    <? endif; ?>
                </div>
                
                <div class="textile-info__item-text">
                    <p class="title"><?= $arResult['PROPERTIES']['ITEM_3_TITLE']['VALUE'] ?></p>
                    
                    <? if (!empty($arResult['PROPERTIES']['ITEM_3_TEXT']['VALUE']['TEXT'])): ?>
                        <?= $arResult['PROPERTIES']['ITEM_3_TEXT']['~VALUE']['TEXT'] ?>
                    <? endif; ?>
                    
                    <? if (!empty($arResult['PROPERTIES']['ITEM_3_BUTTON_LINK']['VALUE'])): ?>
                        <a class="btn cost__btn" href="<?= $arResult['PROPERTIES']['ITEM_3_BUTTON_LINK']['VALUE'] ?>">
                            <span>В КАТАЛОГ</span>
                        </a>
                    <? endif; ?>
                </div>
            </div>
        </div>
        <? endif; ?>
    </div>
</section>
