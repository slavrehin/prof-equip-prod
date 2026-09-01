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
        $picture = $arResult["PREVIEW_PICTURE"];
        if (!$picture && !empty($arResult["DETAIL_PICTURE"])) {
            $picture = $arResult["DETAIL_PICTURE"];
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

                <div class="image-text-block__content">
                    <div class="image-wrapper-container">
                        <div class="image-wrapper">
                            <picture>
                                <source srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" type="image/webp">
                                <img src="<?=$pictureSrc?>" srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" 
                                    alt="<?=$item["NAME"]?>"
                                    width="<?=$width?>" 
                                    height="<?=$height?>">
                            </picture>
                        </div>
                    </div>
                    <div class="text">
                       <?=$arResult["~PREVIEW_TEXT"];?>
                    </div>
                </div>
                 <?=$arResult["~DETAIL_TEXT"];?>
            </div>
        </section>



