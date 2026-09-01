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
        $picture = $arResult['PROPERTIES']['IMG_BLOCK']['VALUE'];

        if ($picture) {

            $width = 1000;
            $height = 1000;
            $width2x = 2000;
            $height2x = 2000;

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

<section class="direction-about">
    <div class="direction-about__inner container">
        <div class="direction-about__text">
            <?if ($arResult['PROPERTIES']['DESCRIPTION']['~VALUE']):?>
                <?=$arResult['PROPERTIES']['DESCRIPTION']['~VALUE']["TEXT"];?>
            <?endif;?>
            <?if ($arResult['PROPERTIES']['LINK_CATALOG']['~VALUE']):?>
                <a class="btn gradient-hover" href="<?=$arResult['PROPERTIES']['LINK_CATALOG']['~VALUE'];?>"><span class="btn__text">КАТАЛОГ</span></a>
            <?endif;?>
        </div>
        <?if ($picture):?>
        <div class="direction-about__image">
            <div class="image-wrapper">
                <picture>
                    <source srcset="<?=$pictureSrc?>, <?=$pictureSrc2x?> 2x" type="image/webp">
                    <img src="<?=$pictureSrc?>" srcset="<?=$pictureSrc?>, <?=$pictureSrc2x?> 2x" alt="direction about">
                </picture>
            </div>
        </div>
        <?endif;?>
    </div>
</section>

