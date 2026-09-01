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
        if (!empty($arResult["DETAIL_PICTURE"])) {
            $picture = $arResult["DETAIL_PICTURE"]["ID"];
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
        <div class="text-block__image">
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
        <?=$arResult["~DETAIL_TEXT"];?>
        <?if ($arResult["PROPERTIES"]['GALLERY']['VALUE']):?>
        <div class="news-swiper">
            <div class="swiper-wrapper">
                <? foreach ($arResult["PROPERTIES"]['GALLERY']['VALUE'] as $index => $image): ?>
                    <a class="swiper-slide" href="<?=CFile::ResizeImageGet(
                                    $image,
                                    array('width' => 2000, 'height' => 2000),
                                    BX_RESIZE_IMAGE_PROPORTIONAL,
                                    true
                                )["src"]?>" data-fancybox="news">
                        <picture>
                            <source srcset="<?=CFile::ResizeImageGet(
                                    $image,
                                    array('width' => 600, 'height' => 600),
                                    BX_RESIZE_IMAGE_PROPORTIONAL,
                                    true
                                )["src"]?> 1x, <?=CFile::ResizeImageGet(
                                    $image,
                                    array('width' => 1200, 'height' => 1200),
                                    BX_RESIZE_IMAGE_PROPORTIONAL,
                                    true
                                )["src"]?> 2x" type="image/webp">
                            <img src="<?=CFile::ResizeImageGet(
                                    $image,
                                    array('width' => 600, 'height' => 600),
                                    BX_RESIZE_IMAGE_PROPORTIONAL,
                                    true
                                )["src"]?>" 
                                srcset="<?=CFile::ResizeImageGet(
                                    $image,
                                    array('width' => 600, 'height' => 600),
                                    BX_RESIZE_IMAGE_PROPORTIONAL,
                                    true
                                )["src"]?> 1x, <?=CFile::ResizeImageGet(
                                    $image,
                                    array('width' => 1200, 'height' => 1200),
                                    BX_RESIZE_IMAGE_PROPORTIONAL,
                                    true
                                )["src"]?> 2x" >
                        </picture>
                    </a>
                <? endforeach; ?>    
            </div>
        </div>
        <?endif;?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "NewsArticle",
      "headline": "<?=$arResult["NAME"];?>",
      "image": "https://<?=$_SERVER['SERVER_NAME'].$pictureSrc2x?>",
      "datePublished": "<?=$arResult["ACTIVE_FROM"];?>",
      "dateModified": "<?=$arResult["TIMESTAMP_X"];?>",
      "author": []
    }
    </script>