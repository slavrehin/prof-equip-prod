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

    <div class="header__contacts">
        <?if ($arResult['PROPERTIES']['PHONE']['~VALUE']):?>
        <a href="tel:<?=$arResult['PROPERTIES']['PHONE']['~VALUE'];?>"><svg>
                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#phone"></use>
            </svg><?=$arResult['PROPERTIES']['PHONE']['~VALUE'];?> </a>
        <?endif;?>    
        <?if ($arResult['PROPERTIES']['EMAIL']['~VALUE']):?>
            <a href="mailto:<?=$arResult['PROPERTIES']['EMAIL']['~VALUE'];?>"><svg>
                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#mail"></use>
            </svg><?=$arResult['PROPERTIES']['EMAIL']['~VALUE'];?></a>
        <?endif;?> 
    </div>
