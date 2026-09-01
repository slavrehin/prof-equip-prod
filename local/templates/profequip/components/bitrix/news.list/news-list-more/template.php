<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;
?>

<section class="related-articles">
    <div class="related-articles__inner container">
        <h3 class="related-articles__title">Похожие статьи</h3>
        <div class="related-articles__list">
            <?php foreach ($arResult["ITEMS"] as $item): ?>
                <?
                $this->AddEditAction($item['ID'], $item['EDIT_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_EDIT"));
                $this->AddDeleteAction($item['ID'], $item['DELETE_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
                
                $picture = $item["PREVIEW_PICTURE"];
                if (!$picture && !empty($item["DETAIL_PICTURE"])) {
                    $picture = $item["DETAIL_PICTURE"];
                }
                
                // Ресайз изображения (300x300 для похожих статей)
                if ($picture) {
                    $resizedPicture = CFile::ResizeImageGet(
                        $picture,
                        array("width" => 300, "height" => 300),
                        BX_RESIZE_IMAGE_PROPORTIONAL,
                        true
                    );
                    
                    $resizedPicture2x = CFile::ResizeImageGet(
                        $picture,
                        array("width" => 600, "height" => 600),
                        BX_RESIZE_IMAGE_PROPORTIONAL,
                        true
                    );
                    
                    $pictureSrc = $resizedPicture["src"];
                    $pictureSrc2x = $resizedPicture2x["src"];
                }
                $timestamp = MakeTimeStamp($item["ACTIVE_FROM"] ?: $item["DATE_CREATE"]);
                $date = FormatDate("d.m.Y", $timestamp);
                ?>
                <a class="related-article-item" href="<?=$item["DETAIL_PAGE_URL"]?>" id="<?=$this->GetEditAreaId($item['ID']);?>">
                    <div class="image-wrapper">
                        <picture>
                            <source srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" type="image/webp">
                            <img src="<?=$pictureSrc?>" 
                                 srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" 
                                 alt="<?=$item["NAME"]?>"
                                 width="300" 
                                 height="300">
                        </picture>
                    </div>
                    <div class="related-article__content">
                        <p class="title"><?=$item["NAME"]?></p>
                        <p class="date"><?=$date?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>