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

        // Получаем картинку
        $picture = $arResult['PROPERTIES']['LOGO']['VALUE'];
        if (!$picture) {
            $picture = $arResult["PREVIEW_PICTURE"];
        }
        
        // Ресайз изображений
        if ($picture) {

            $width = 2000;
            $height = 1000;
            $width2x = 2000;
            $height2x = 1000;

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

<div class="brand-about">
    <div class="image-wrapper">
                    <?if ($picture):?>
                        <picture>
                            <source srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" type="image/webp">
                            <img src="<?=$pictureSrc?>" srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" 
                                alt="<?=$item["NAME"]?>"
                                width="<?=$width?>" 
                                height="<?=$height?>">
                        </picture>
                    <?endif;?>
    </div>
    <div class="brand-about__text">
        <?=galleryParts($arResult["~PREVIEW_TEXT"], $arResult['PROPERTIES']);?>
    </div>
</div>