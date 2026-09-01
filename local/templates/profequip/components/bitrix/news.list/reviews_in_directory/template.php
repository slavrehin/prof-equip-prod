<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>

<?php if (!empty($arResult["ITEMS"])): ?>
<section class="reviews">
    <div class="reviews__inner container">
        <h2 class="reviews__title">ОТЗЫВЫ</h2>
        <div class="reviews-swiper">
            <div class="swiper-wrapper">
                <?php foreach ($arResult["ITEMS"] as $arItem): 
                    $this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
                    $this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
                    
                    // Получаем изображение из PREVIEW_PICTURE
                    $pictureSrc = '';
                    if (!empty($arItem["PREVIEW_PICTURE"])) {
                        $pictureId = $arItem["PREVIEW_PICTURE"]["ID"];
                        $arImage = CFile::ResizeImageGet(
                            $pictureId,
                            array('width' => 150, 'height' => 150),
                            BX_RESIZE_IMAGE_PROPORTIONAL ,
                            true
                        );
                        $pictureSrc = $arImage['src'];
                    }

                    $author = $arItem["NAME"];
                    
                ?>
                    <div class="swiper-slide" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
                        <div class="reviews-item">
                            <?php if (!empty($pictureSrc)): ?>
                            <div class="image-wrapper">
                                <img src="<?=$pictureSrc?>" alt="<?=htmlspecialchars($author)?>">
                            </div>
                            <?php endif; ?>
                            
                            <div class="review__text">
                                <p class="text"><?=htmlspecialchars($arItem["PREVIEW_TEXT"])?></p>
                                <p class="author"><?=htmlspecialchars($author)?></p>
                                
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="navigation">
                <button class="btn reviews__prev" type="button">
                    <svg><use xlink:href="<?=LAYOUT_DIR;?>assets/assets/img/sprite.svg#arrow-swiper-left"></use></svg>
                </button>
                <button class="btn reviews__next" type="button">
                    <svg><use xlink:href="<?=LAYOUT_DIR;?>assets/assets/img/sprite.svg#arrow-swiper-right"></use></svg>
                </button>
            </div>
            <div class="pagination"></div>
        </div>
    </div>
</section>

<?php endif; ?>