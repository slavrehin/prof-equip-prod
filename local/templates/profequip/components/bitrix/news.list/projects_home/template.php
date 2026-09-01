<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;
?>

<div class="projects__list">
                <? foreach ($arResult["ITEMS"] as $item): ?>
                <?
                // Формируем data-filter атрибут с ID типов
                $filterData = "0";
                $typeValue = $item["PROPERTIES"]["TYPE"]["VALUE"];
                
                if (!empty($typeValue)) {
                    if (is_array($typeValue)) {
                        $filterData .= " " . implode(" ", $typeValue);
                    } else {
                        $filterData .= " " . $typeValue;
                    }
                }
                
                $detailUrl = $item["DETAIL_PAGE_URL"];

                $pictureId = 0;
                if (!empty($item["PREVIEW_PICTURE"]["ID"])) {
                    $pictureId = $item["PREVIEW_PICTURE"]["ID"];
                } elseif (!empty($item["DETAIL_PICTURE"]["ID"])) {
                    $pictureId = $item["DETAIL_PICTURE"]["ID"];
                }
                
                if ($pictureId > 0) {
                    $arImage = CFile::ResizeImageGet(
                        $pictureId,
                        array('width' => 1000, 'height' => 1000),
                        BX_RESIZE_IMAGE_PROPORTIONAL,
                        true
                    );
                    $imgSrc = $arImage['src'];
                    
                    $arImage2x = CFile::ResizeImageGet(
                        $pictureId,
                        array('width' => 2000, 'height' => 2000),
                        BX_RESIZE_IMAGE_PROPORTIONAL,
                        true
                    );
                    $imgSrc2x = $arImage2x['src'];

                    
                }
                
                $itemName = htmlspecialchars($item["NAME"]);
                ?>
        <div class="project-card">
            <div class="image-wrapper">
                <picture>
                    <source srcset="<?=$imgSrc?> 1x, <?=$imgSrc2x?> 2x" type="image/webp">
                    <img src="<?=$imgSrc?>" 
                            srcset="<?=$imgSrc?> 1x, <?=$imgSrc2x?> 2x" 
                            loading="lazy">
                </picture>
            </div>
            <div class="project-card__content">
                <? if ($item["PROPERTIES"]["CITY"]["VALUE"]):?>
                <div class="address"><svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#pin"></use>
                    </svg>
                    <p><?=$item["PROPERTIES"]["CITY"]["VALUE"];?></p>
                </div>
                <?endif;?>
                <a  href="<?=$detailUrl?>" class="title"><?=$itemName?></a>
                <p class="text"><?$item["PREVIEW_TEXT"]?></p>
                <a class="btn link__btn" href="<?=$detailUrl?>" ><span class="eye__icon"><svg>
                            <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#eye"></use>
                        </svg></span><span class="btn__text">Смотреть проект</span></a>
            </div>
        </div>
    <? endforeach; ?>
</div>
