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
    <div class="footer__contacts">
        <?if ($arResult['PROPERTIES']['PHONE']['~VALUE']):?>
            <a class="contact__link" href="tel:<?=$arResult['PROPERTIES']['PHONE']['~VALUE'];?>"><?=$arResult['PROPERTIES']['PHONE']['~VALUE'];?></a>
        <?endif;?> 
        <?if ($arResult['PROPERTIES']['EMAIL']['~VALUE']):?>
            <a class="contact__link" href="mailto:<?=$arResult['PROPERTIES']['EMAIL']['~VALUE'];?>"><?=$arResult['PROPERTIES']['EMAIL']['~VALUE'];?></a>
        <?endif;?> 
        <?if ($arResult['PROPERTIES']['ADDRESS']['~VALUE']):?>
            <p class="address"><?=$arResult['PROPERTIES']['ADDRESS']['~VALUE']["TEXT"];?></p>
        <?endif;?> 
        <?if ($arResult['PROPERTIES']['TG']['~VALUE']):?>
            <div class="social">
                <p class="social__title">Найдите нас:</p><a class="social__link" href="<?=$arResult['PROPERTIES']['TG']['~VALUE'];?>" target="_blank"><svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#tg"></use>
                    </svg></a>
            </div>
        <?endif;?> 
    </div>

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "url": "https://<?=$_SERVER['SERVER_NAME'];?>",
      "logo": "https://<?=$_SERVER['SERVER_NAME'];?>/upload/logo.png",
      "name": "ПРОФЭКВИП",
      "email": "<?=$arResult['PROPERTIES']['EMAIL']['~VALUE'];?>",
      "telephone": "<?=$arResult['PROPERTIES']['PHONE']['~VALUE'];?>",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "<?=$arResult['PROPERTIES']['ADDRESS']['~VALUE']["TEXT"];?>"
      }
    }
    </script>