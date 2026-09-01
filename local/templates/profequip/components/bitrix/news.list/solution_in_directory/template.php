<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>

<?php if (!empty($arResult["ITEMS"])): ?>
<section class="portfolio">
    <div class="portfolio__inner container">
        <h2 class="portfolio__title">ГОТОВЫЕ РЕШЕНИЯ</h2>
        <div class="portfolio-swiper">
            <div class="swiper-wrapper">
                <?php foreach ($arResult["ITEMS"] as $arItem): 
                    $this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
                    $this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
                    
                    // Получаем изображение из PREVIEW_PICTURE
                    $pictureSrc = '';
                    $pictureSrc2x = '';
                    $pictureWebp = '';
                    $pictureWebp2x = '';
                    
                    if (!empty($arItem["PREVIEW_PICTURE"])) {
                        $pictureId = $arItem["PREVIEW_PICTURE"]["ID"];
                        
                        // Обычная версия (600x400)
                        $arImage = CFile::ResizeImageGet(
                            $pictureId,
                            array('width' => 600, 'height' => 400),
                            BX_RESIZE_IMAGE_PROPORTIONAL,
                            true
                        );
                        $pictureSrc = $arImage['src'];
                        
                        // Retina версия (1200x800)
                        $arImage2x = CFile::ResizeImageGet(
                            $pictureId,
                            array('width' => 1200, 'height' => 800),
                            BX_RESIZE_IMAGE_PROPORTIONAL,
                            true
                        );
                        $pictureSrc2x = $arImage2x['src'];
                    }
                    
                    // Ссылка на детальную страницу
                    $detailUrl = $arItem["DETAIL_PAGE_URL"];
                    
                    // Заголовок (название проекта)
                    $projectTitle = $arItem["NAME"];
                    
                    // Описание (PREVIEW_TEXT)
                    $projectDesc = $arItem["PREVIEW_TEXT"];
                    
                    $projectDesc = strip_tags($projectDesc);
                    $projectDesc = trim(preg_replace('/\s+/', ' ', $projectDesc));
                    if (mb_strlen($projectDesc) > 100) {
                        $projectDesc = mb_substr($projectDesc, 0, 100) . '…';
                    }
                ?>
                    <div class="swiper-slide" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
                        <a class="portfolio-card image-sm" href="<?=$detailUrl?>">
                            <div class="image-wrapper">
                                <picture>
                                    <source srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" type="image/webp">
                                    <img src="<?=$pictureSrc?>" 
                                         srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" 
                                         alt="<?=htmlspecialchars($projectTitle)?>"
                                         loading="lazy">
                                </picture>
                            </div>
                            <div class="portfolio-card__content">
                                <p class="title"><?=$projectTitle?></p>
                                <p class="descr"><?=htmlspecialchars($projectDesc)?></p>
                                <p class="link">
                                    Подробнее
                                    <svg>
                                        <use xlink:href="<?=LAYOUT_DIR;?>assets/img/sprite.svg#arrow-right-fill"></use>
                                    </svg>
                                </p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="navigation">
                <button class="btn portfolio__prev" type="button">
                    <svg>
                        <use xlink:href="<?=LAYOUT_DIR;?>assets/img/sprite.svg#arrow-swiper-left"></use>
                    </svg>
                </button>
                <button class="btn portfolio__next" type="button">
                    <svg>
                        <use xlink:href="<?=LAYOUT_DIR;?>assets/img/sprite.svg#arrow-swiper-right"></use>
                    </svg>
                </button>
            </div>

        </div>
    </div>
</section>

<?php endif; ?>