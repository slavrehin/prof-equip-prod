<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;
?>
<section class="portfolio">
    <div class="portfolio__inner container">
        <h2 class="portfolio__title">ПОРТФОЛИО</h2>
        <div class="portfolio-swiper">
            <div class="swiper-wrapper">
            <? foreach ($arResult["ITEMS"] as $item): ?>
                <?
                $this->AddEditAction($item['ID'], $item['EDIT_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_EDIT"));
                $this->AddDeleteAction($item['ID'], $item['DELETE_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
                
                $picture = $item["PREVIEW_PICTURE"];
                if (!$picture && !empty($item["DETAIL_PICTURE"])) {
                    $picture = $item["DETAIL_PICTURE"];
                }
                
                if ($picture) {
                    $resizedPicture = CFile::ResizeImageGet(
                        $picture,
                        array("width" => 500, "height" => 500),
                        BX_RESIZE_IMAGE_PROPORTIONAL,
                        true
                    );
                    
                    $resizedPicture2x = CFile::ResizeImageGet(
                        $picture,
                        array("width" => 1000, "height" => 1000),
                        BX_RESIZE_IMAGE_PROPORTIONAL,
                        true
                    );
                    
                    $pictureSrc = $resizedPicture["src"];
                    $pictureSrc2x = $resizedPicture2x["src"];
                }
?>
                <div class="swiper-slide"><a class="portfolio-card" href="<?=$item["DETAIL_PAGE_URL"]?>" id="<?=$this->GetEditAreaId($item['ID']);?>">
                        <div class="image-wrapper">
                        <picture>
                            <source srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" type="image/webp">
                            <img src="<?=$pictureSrc?>" 
                                 srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" 
                                 alt="<?=$item["NAME"]?>">
                        </picture>
                        </div>
                        <div class="portfolio-card__content">
                            <p class="title"><?=$item["NAME"]?></p>
                            <p class="link">Подробнее<svg>
                                    <use xlink:href="<?=LAYOUT_DIR;?>assets/img/sprite.svg#arrow-right-fill"></use>
                                </svg></p>
                        </div>
                    </a></div>
                <? endforeach; ?>
            </div>
            <div class="navigation"><button class="btn portfolio__prev" type="button"><svg>
                        <use xlink:href="<?=LAYOUT_DIR;?>assets/img/sprite.svg#arrow-swiper-left"></use>
                    </svg></button><button class="btn portfolio__next" type="button"><svg>
                        <use xlink:href="<?=LAYOUT_DIR;?>assets/img/sprite.svg#arrow-swiper-right"></use>
                    </svg></button></div>
        </div>
    </div>
</section>