<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>

<h2 class="chemistry-form__title"><?=$arResult['PROPERTIES']['TITLE_FORM']['VALUE'];?></h2>
<?if ($arResult['PROPERTIES']['TEXT_FORM']['VALUE']):?>
    <p class="chemistry-form__descr"><?=$arResult['PROPERTIES']['TEXT_FORM']['~VALUE']["TEXT"];?></p>
<?endif;?>

