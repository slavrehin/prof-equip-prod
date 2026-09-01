<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;
?>

<section class="another-objects">
    <div class="another-objects__inner container">
        <h3 class="another-objects__title">Другие объекты</h3>
        <div class="another-objects__swiper">
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
                $timestamp = MakeTimeStamp($item["ACTIVE_FROM"] ?: $item["DATE_CREATE"]);
                $date = FormatDate("d.m.Y", $timestamp);?>
                <a class="another-object-card swiper-slide" href="<?=$item["DETAIL_PAGE_URL"]?>" id="<?=$this->GetEditAreaId($item['ID']);?>">
                    <div class="image-wapper">
                        <picture>
                            <source srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" type="image/webp">
                            <img src="<?=$pictureSrc?>" 
                                 srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" 
                                 alt="<?=$item["NAME"]?>">
                        </picture>
                    </div>
                    <div class="another-object-card__content">
                        <p class="title"><?=$item["NAME"]?></p>
                        <p class="date"><?=$date?></p>
                    </div>
                </a>
                <? endforeach; ?>
            </div>
            <div class="navigation"><button class="btn another-objects__prev" type="button"><svg>
                        <use xlink:href="<?=LAYOUT_DIR;?>assets/img/sprite.svg#arrow-swiper-left"></use>
                    </svg></button><button class="btn another-objects__next" type="button"><svg>
                        <use xlink:href="<?=LAYOUT_DIR;?>assets/img/sprite.svg#arrow-swiper-right"></use>
                    </svg></button></div>
        </div>
    </div>
</section>
