<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;
?>
<div class="footer__news-list">
    <?foreach ($arResult["ITEMS"] as $item):?>
        <?
        $this->AddEditAction($item['ID'], $item['EDIT_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_EDIT"));
        $this->AddDeleteAction($item['ID'], $item['DELETE_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
        
        $timestamp = MakeTimeStamp($item["ACTIVE_FROM"] ?: $item["DATE_CREATE"]);
        $date = FormatDate("d.m.Y", $timestamp);
        
        // Получаем картинку
        $picture = $item["PREVIEW_PICTURE"];
        if (!$picture && !empty($item["DETAIL_PICTURE"])) {
            $picture = $item["DETAIL_PICTURE"];
        }
        
        // Ресайз изображений
        if ($picture) {

            $width = 100;
            $height = 100;
            $width2x = 200;
            $height2x = 200;

            $resizedPicture = CFile::ResizeImageGet(
                $picture,
                array("width" => $width, "height" => $height),
                BX_RESIZE_IMAGE_EXACT,
                true
            );
            
            // Ресайз для retina (2x)
            $resizedPicture2x = CFile::ResizeImageGet(
                $picture,
                array("width" => $width2x, "height" => $height2x),
                BX_RESIZE_IMAGE_EXACT,
                true
            );
            
            
            $pictureSrc = $resizedPicture["src"];
            $pictureSrc2x = $resizedPicture2x["src"];
        }
        ?>
            <a class="footer-news" href="<?=$item["DETAIL_PAGE_URL"]?>" id="<?=$this->GetEditAreaId($item['ID']);?>">
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
                </div>
                <div class="footer-news__content">
                    <p class="title"><?=$item["NAME"]?></p>
                    <p class="date"><?=$date?></p>
                </div>
            </a>
    <?endforeach;?>
</div>