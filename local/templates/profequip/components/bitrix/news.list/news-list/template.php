<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;
?>
<div class="news-list__items">
    <?foreach ($arResult["ITEMS"] as $item):?>
        <?
        $this->AddEditAction($item['ID'], $item['EDIT_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_EDIT"));
        $this->AddDeleteAction($item['ID'], $item['DELETE_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
        
        $timestamp = MakeTimeStamp($item["ACTIVE_FROM"] ?: $item["DATE_CREATE"]);
        $month = FormatDate("M", $timestamp);
        $day = FormatDate("d", $timestamp);
        
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
        <a class="news-item" href="<?=$item["DETAIL_PAGE_URL"]?>" id="<?=$this->GetEditAreaId($item['ID']);?>">
            <div class="image-wrapper">
                <picture>
                    <?if ($picture):?>
                        <source srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" type="image/webp">
                        <img src="<?=$pictureSrc?>" srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" 
                             alt="<?=$item["NAME"]?>"
                             width="<?=$width?>" 
                             height="<?=$height?>">
                    <?endif;?>
                </picture>
                <?if ($timestamp):?>
                <div class="news__date">
                    <p class="month"><?=$month?></p>
                    <p class="date"><?=$day?></p>
                </div>
                <?endif;?>
            </div>
            <div class="news-item__content">
                <p class="news-item__title"><?=$item["NAME"]?></p>
            </div>
        </a>
    <?endforeach;?>
</div>