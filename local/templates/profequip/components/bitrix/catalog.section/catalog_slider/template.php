<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

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

$previewProperties = isset($arParams['PROPERTYS_PREVIEW']) && is_array($arParams['PROPERTYS_PREVIEW']) 
    ? $arParams['PROPERTYS_PREVIEW'] 
    : [];

?>
<?php if (!empty($arResult['ITEMS'])): ?>
        <section class="chemistry-catalog">
            <div class="chemistry-catalog__inner container">
                <h2 class="chemistry-catalog__title">КАТАЛОГ</h2>
                <div class="chemistry-catalog-swiper">
                    <div class="swiper-wrapper">  
                        <?php foreach ($arResult['ITEMS'] as $item): ?>
                            <?php
                            // Создаем уникальный ID для области редактирования
                            $uniqueId = $item['ID'] . '_' . md5($this->randString() . $component->getAction());
                            $areaId = $this->GetEditAreaId($uniqueId);
                            
                            // Добавляем действия редактирования
                            $this->AddEditAction($uniqueId, $item['EDIT_LINK'], $elementEdit);
                            $this->AddDeleteAction($uniqueId, $item['DELETE_LINK'], $elementDelete, $elementDeleteParams);
                            
                            // Подготавливаем параметры для catalog.item
                            $itemParams = [
                                'SIZE_PROPERTY_CODE' => $sizePropertyCode,
                                'COLOR_PROPERTY_CODE' => $colorPropertyCode,
                                'LAYOUT_PATH' => LAYOUT_PATH,
                            ];
                            
                            ?>
                            <div class="swiper-slide">
                            <?
                            $productId = $item['ID'];
                            $productLink = $item['DETAIL_PAGE_URL'];

                            $price = $item['PRICES_DATA']['BASE_PRICE'] ?? null;
                            $currency = $item['PRICES_DATA']['CURRENCY'] ?? 'RUB';

                            $mainImageId = null;
                            if (!empty($item['PREVIEW_PICTURE'])) {
                                $mainImageId = is_array($item['PREVIEW_PICTURE']) ? 
                                            $item['PREVIEW_PICTURE']['ID'] : 
                                            $item['PREVIEW_PICTURE'];
                            }


                            $resizedImage = CFile::ResizeImageGet(
                                $mainImageId,
                                ['width' => 600, 'height' => 600],
                                BX_RESIZE_IMAGE_PROPORTIONAL,
                                true
                            )['src'];
                            $resizedImageX2 = CFile::ResizeImageGet(
                                $mainImageId,
                                ['width' => 1200, 'height' => 1200],
                                BX_RESIZE_IMAGE_PROPORTIONAL,
                                true
                            )['src'];  
                            ?>

                                <div class="image-wrapper">
                                    <picture>
                                        <source srcset="<?=$resizedImage;?>, <?=$resizedImageX2;?> 2x" type="image/webp">
                                        <img src="<?=$resizedImage;?>" srcset="<?=$resizedImage;?>, <?=$resizedImageX2;?> 2x" alt="catalog product">
                                    </picture>
                                </div>
                                <div class="catalog-card__content" id="<?= $arResult['AREA_ID'] ?>" >
                                    <p class="title"><?= htmlspecialcharsbx($item['NAME']) ?></p><button class="btn cost__btn"><span>ЗАПРОСИТЬ СТОИМОСТЬ</span></button>
                                    <a class="btn cost__btn about__btn" href="<?= $productLink ?>"><span>Подробнее</span></a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="navigation"><button class="btn chemistry__prev" type="button"><svg>
                                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-left"></use>
                            </svg></button><button class="btn chemistry__next" type="button"><svg>
                                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-right"></use>
                            </svg></button></div>
                </div>
            </div>
        </section>
<?php endif; ?>


