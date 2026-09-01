<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;
?>
<div class="articles-content__list">
    <?foreach ($arResult["ITEMS"] as $item):?>
        <?
        $this->AddEditAction($item['ID'], $item['EDIT_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_EDIT"));
        $this->AddDeleteAction($item['ID'], $item['DELETE_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
        

        // Получаем картинку
        $picture = $item["PREVIEW_PICTURE"];
        if (!$picture && !empty($item["DETAIL_PICTURE"])) {
            $picture = $item["DETAIL_PICTURE"];
        }
        
        // Ресайз изображений
        if ($picture) {

            $width = 500;
            $height = 500;
            $width2x = 1000;
            $height2x = 1000;

            $resizedPicture = CFile::ResizeImageGet(
                $picture,
                array("width" => $width, "height" => $height),
                BX_RESIZE_IMAGE_PROPORTIONAL,
                true
            );
            
            // Ресайз для retina (2x)
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
        <div class="article-card" id="<?=$this->GetEditAreaId($item['ID']);?>"><a class="image-wrapper" href="<?=$item["DETAIL_PAGE_URL"]?>">
                <picture>
                    <?if ($picture):?>
                        <source srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" type="image/webp">
                        <img src="<?=$pictureSrc?>" srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" 
                             alt="<?=$item["NAME"]?>"
                             width="<?=$width?>" 
                             height="<?=$height?>">
                    <?endif;?>
                </picture>
            </a>
            <div class="article-card__content">
                <p class="title"><a href="<?=$item["DETAIL_PAGE_URL"]?>"><?=$item["NAME"]?></a></p><a class="btn cost__btn" href="<?=$item["DETAIL_PAGE_URL"]?>"><span>Подробнее</span></a>
            </div>
        </div>
    <?endforeach;?>
</div>