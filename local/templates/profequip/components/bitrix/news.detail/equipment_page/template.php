<? 
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
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
        $picture = $arResult['PROPERTIES']['IMG_BLOCK']['VALUE'];

        if ($picture) {

            $width = 1000;
            $height = 1000;
            $width2x = 2000;
            $height2x = 2000;

            $resizedPicture = CFile::ResizeImageGet(
                $picture,
                array("width" => $width, "height" => $height),
                BX_RESIZE_IMAGE_PROPORTIONAL,
                true
            );
            
            $resizedPicture2x = CFile::ResizeImageGet(
                $picture,
                array("width" => $width2x, "height" => $height2x),
                BX_RESIZE_IMAGE_PROPORTIONAL,
                true
            );

            $pictureSrc = $resizedPicture["src"];
            $pictureSrc2x = $resizedPicture2x["src"];
    }
?>
        <div class="equipment-about">
            <div class="equipment-about__inner container">
                <? if (!empty($arResult['PROPERTIES']['ITEM_1_TITLE']['VALUE'])): ?>
                    <div class="equipment-about__item text-left">
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
                        <div class="equipment-about__text">
                            <h3 class="equipment__title"><?= $arResult['PROPERTIES']['ITEM_1_TITLE']['~VALUE'] ?></h3>
                            <p class="equipment__descr">
                                <? if (!empty($arResult['PROPERTIES']['ITEM_1_TEXT']['VALUE']['TEXT'])): ?>
                                    <?= $arResult['PROPERTIES']['ITEM_1_TEXT']['~VALUE']['TEXT'] ?>
                                <? endif; ?>
                            </p>
                                <? if (!empty($arResult['PROPERTIES']['ITEM_1_BUTTON_LINK']['VALUE'])): ?>
                                    <a class="btn cost__btn" href="<?= $arResult['PROPERTIES']['ITEM_1_BUTTON_LINK']['VALUE'] ?>">
                                        <span>Подробнее</span>
                                    </a>
                                <? endif; ?>
                        </div>
                    </div>
                <? endif; ?>
                <? if (!empty($arResult['PROPERTIES']['ITEM_2_TITLE']['VALUE'])): ?>
                    <div class="equipment-about__item text-right">
                        <? if (!empty($arResult['PROPERTIES']['ITEM_2_IMAGE']['VALUE'])): 
                            $image = CFile::GetFileArray($arResult['PROPERTIES']['ITEM_2_IMAGE']['VALUE']);
                        ?>
                            <div class="image-wrapper">
                                <picture>
                                    <source srcset="<?= $image['SRC'] ?>?webp, <?= $image['SRC'] ?>?webp 2x" type="image/webp">
                                    <img src="<?= $image['SRC'] ?>" 
                                        srcset="<?= $image['SRC'] ?>, <?= $image['SRC'] ?> 2x" 
                                        alt="<?= !empty($arResult['PROPERTIES']['ITEM_2_TITLE']['VALUE']) 
                                            ? $arResult['PROPERTIES']['ITEM_2_TITLE']['VALUE'] 
                                            : 'textile' ?>">
                                </picture>
                            </div>
                        <? endif; ?>
                        <div class="equipment-about__text">
                            <h3 class="equipment__title"><?= $arResult['PROPERTIES']['ITEM_2_TITLE']['~VALUE'] ?></h3>
                            <p class="equipment__descr">
                                <? if (!empty($arResult['PROPERTIES']['ITEM_2_TEXT']['VALUE']['TEXT'])): ?>
                                    <?= $arResult['PROPERTIES']['ITEM_2_TEXT']['~VALUE']['TEXT'] ?>
                                <? endif; ?>
                            </p>
                                <? if (!empty($arResult['PROPERTIES']['ITEM_2_BUTTON_LINK']['VALUE'])): ?>
                                    <a class="btn cost__btn" href="<?= $arResult['PROPERTIES']['ITEM_2_BUTTON_LINK']['VALUE'] ?>">
                                        <span>Подробнее</span>
                                    </a>
                                <? endif; ?>
                        </div>
                    </div>
                <? endif; ?>
                <? if (!empty($arResult['PROPERTIES']['ITEM_3_TITLE']['VALUE'])): ?>
                    <div class="equipment-about__item text-left">
                        <? if (!empty($arResult['PROPERTIES']['ITEM_3_IMAGE']['VALUE'])): 
                            $image = CFile::GetFileArray($arResult['PROPERTIES']['ITEM_3_IMAGE']['VALUE']);
                        ?>
                            <div class="image-wrapper">
                                <picture>
                                    <source srcset="<?= $image['SRC'] ?>?webp, <?= $image['SRC'] ?>?webp 2x" type="image/webp">
                                    <img src="<?= $image['SRC'] ?>" 
                                        srcset="<?= $image['SRC'] ?>, <?= $image['SRC'] ?> 2x" 
                                        alt="<?= !empty($arResult['PROPERTIES']['ITEM_3_TITLE']['VALUE']) 
                                            ? $arResult['PROPERTIES']['ITEM_3_TITLE']['VALUE'] 
                                            : 'textile' ?>">
                                </picture>
                            </div>
                        <? endif; ?>
                        <div class="equipment-about__text">
                            <h3 class="equipment__title"><?= $arResult['PROPERTIES']['ITEM_3_TITLE']['~VALUE'] ?></h3>
                            <p class="equipment__descr">
                                <? if (!empty($arResult['PROPERTIES']['ITEM_3_TEXT']['VALUE']['TEXT'])): ?>
                                    <?= $arResult['PROPERTIES']['ITEM_3_TEXT']['~VALUE']['TEXT'] ?>
                                <? endif; ?>
                            </p>
                                <? if (!empty($arResult['PROPERTIES']['ITEM_3_BUTTON_LINK']['VALUE'])): ?>
                                    <a class="btn cost__btn" href="<?= $arResult['PROPERTIES']['ITEM_3_BUTTON_LINK']['VALUE'] ?>">
                                        <span>Подробнее</span>
                                    </a>
                                <? endif; ?>
                        </div>
                    </div>
                <? endif; ?>
            </div>
        </div>
