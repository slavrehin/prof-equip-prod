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
?>


        <div class="about-text__content">
            <?=$arResult["~PREVIEW_TEXT"];?>
        </div>
    </div>
</section>
<?if ($arResult['PROPERTIES']['TEXT_BLOCKS']['VALUE']):?>
<section class="about-info">
    <div class="about-info__inner container">
        <?foreach ($arResult['PROPERTIES']['TEXT_BLOCKS']['~VALUE'] as $key=>$item):?>
        <div class="about-info__item">
            <p class="info__title"><?=$arResult['PROPERTIES']['TEXT_BLOCKS']['~DESCRIPTION'][$key];?></p>
            <?=$item["TEXT"]?>
        </div>
        <?endforeach;?>
    </div>
</section>
<?endif;?>