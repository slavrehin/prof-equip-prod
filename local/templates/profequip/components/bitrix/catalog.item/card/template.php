<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/** @var array $arParams */
/** @var array $arResult */
/** @var CBitrixComponentTemplate $this */

$item = $arResult['ITEM'];
$arParams = $arResult['PARAMS'];

$productId = $item['ID'];
$productLink = $item['DETAIL_PAGE_URL'];

$price = $item['PRICES_DATA']['BASE_PRICE'] ?? null;
$currency = $item['PRICES_DATA']['CURRENCY'] ?? 'RUB';

$mainImageId = null;
if (!empty($item['PREVIEW_PICTURE'])) {
    $mainImageId = is_array($item['PREVIEW_PICTURE']) ? 
                   $item['PREVIEW_PICTURE']['ID'] : 
                   $item['PREVIEW_PICTURE'];
}


$resizedImage = CFile::ResizeImageGet(
    $mainImageId,
    ['width' => 600, 'height' => 600],
    BX_RESIZE_IMAGE_PROPORTIONAL,
    true
)['src'];
$resizedImageX2 = CFile::ResizeImageGet(
    $mainImageId,
    ['width' => 1200, 'height' => 1200],
    BX_RESIZE_IMAGE_PROPORTIONAL,
    true
)['src'];  



?>



<a class="catalog-card" href="<?= $productLink ?>" id="<?= $arResult['AREA_ID'] ?>" >
    <div class="image-wrapper">
        <picture>
            <source srcset="<?=$resizedImage;?>, <?=$resizedImageX2;?> 2x" type="image/webp">
            <img src="<?=$resizedImage;?>" srcset="<?=$resizedImage;?>, <?=$resizedImageX2;?> 2x" alt="catalog product">
        </picture>
    </div>
    <div class="catalog-card__content">
        <p class="catalog-card__title"><?= $item['~NAME'] ?></p>
        <button class="btn cost__btn" data-modal-load="/local/ajax/form/?WEB_FORM_ID=1&template_form=order&name_product=<?=$item['NAME']?>"><span>ЗАПРОСИТЬ СТОИМОСТЬ</span></button>
    </div>
</a>