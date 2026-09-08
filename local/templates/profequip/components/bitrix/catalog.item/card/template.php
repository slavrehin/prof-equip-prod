<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/** @var array $arParams */
/** @var array $arResult */
/** @var CBitrixComponentTemplate $this */

$item = $arResult['ITEM'];
// $arResult['PARAMS'] не существует (RESULT содержит только ITEM/AREA_ID/TYPE/...) -
// реальные PARAMS уже смержены в $arParams компонентом (см.
// CatalogItemComponent::onPrepareComponentParams: $params += $params['PARAMS']),
// поэтому просто читаем их напрямую, без переприсваивания $arParams.

$productId = $item['ID'];
$productLink = $item['DETAIL_PAGE_URL'];

$price = profequip_GetCatalogItemPrice($item);

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
        <p class="catalog-card__title" title="<?= htmlspecialcharsbx($item['NAME']) ?>"><?= $item['~NAME'] ?></p>
        <div class="catalog-card__footer">
            <div class="catalog-card__divider"></div>
            <div class="catalog-card__price-row">
                <?php if ($price !== null): ?>
                <span class="catalog-card__price"><?= profequip_FormatPriceRub($price) ?></span>
                <span class="catalog-card__price-note">с НДС</span>
                <?php endif; ?>
            </div>
            <?php if ($price !== null): ?>
            <button class="btn cost__btn cta-btn" data-modal-load="/local/ajax/form/?WEB_FORM_ID=1&template_form=order&name_product=<?=$item['NAME']?>&cta_label=<?=rawurlencode('ПОЛУЧИТЬ КП')?>">
                <svg class="cta-btn-icon" width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3 1.5h5.5L11 4v8a.5.5 0 0 1-.5.5h-7A.5.5 0 0 1 3 12V1.5Z" stroke="#fff" stroke-width="1" stroke-linejoin="round"/><path d="M8 1.5V4h3M5 7h4M5 9.2h4" stroke="#fff" stroke-width="1" stroke-linecap="round"/></svg>
                <span>ПОЛУЧИТЬ КП</span>
            </button>
            <?php else: ?>
            <button class="btn cost__btn cta-btn" data-modal-load="/local/ajax/form/?WEB_FORM_ID=1&template_form=order&name_product=<?=$item['NAME']?>&cta_label=<?=rawurlencode('Запросить')?>"><span>ЗАПРОСИТЬ СТОИМОСТЬ</span></button>
            <?php endif; ?>
        </div>
    </div>
</a>