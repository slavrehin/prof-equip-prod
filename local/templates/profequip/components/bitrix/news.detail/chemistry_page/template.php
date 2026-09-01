<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

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

?>


<section class="chemistry-info">
    <div class="chemistry-info__inner container">
        <div class="chemistry-info__container">
            <h1 class="chemistry-info__title"><?= !empty($arResult['PROPERTIES']['MAIN_TITLE']['VALUE']) 
                ? $arResult['PROPERTIES']['MAIN_TITLE']['VALUE'] 
                : $arResult['NAME'] ?></h1>
            <div class="chemistry-info__text-container">
                <div class="chemistry-info__text">
                <? if (!empty($arResult['PROPERTIES']['MAIN_TEXT_LEFT']['VALUE']['TEXT'])): ?>
                        <?= $arResult['PROPERTIES']['MAIN_TEXT_LEFT']['~VALUE']['TEXT'] ?>
                <? endif; ?>
                </div>
                <div class="chemistry-info__text">
                    <? if (!empty($arResult['PROPERTIES']['MAIN_TEXT_RIGHT']['VALUE']['TEXT'])): ?>
                            <?= $arResult['PROPERTIES']['MAIN_TEXT_RIGHT']['~VALUE']['TEXT'] ?>
                    <? endif; ?>
                </div>
            </div>
            <?if ($arResult['PROPERTIES']['STAT']['VALUE']):?>
            <div class="chemistry-info__stat">
                <?foreach ($arResult['PROPERTIES']['STAT']['VALUE'] as $key=>$item):?>
                    <div class="stat__item">
                        <p class="stat__number"><?=$item;?></p>
                        <p class="stat__descr"><?=$arResult['PROPERTIES']['STAT']['DESCRIPTION'][$key];?></p>
                    </div>
                <?endforeach;?>
            </div>
            <?endif;?>
        </div>
    </div>
</section>
<?if ($arResult['PROPERTIES']['LIST_GARANT']['VALUE']):?>
<section class="chemistry-features">
    <div class="chemistry-features__inner container">
        <h2 class="chemistry-features__title"><?= $arResult['PROPERTIES']['TITLE_GARANT']['~VALUE']?></h2>
        <div class="chemistry-features__list">
            <?foreach ($arResult['PROPERTIES']['LIST_GARANT']['VALUE'] as $key=>$item):?>
                <div class="chemistry-feature__item"><img src="<?=LAYOUT_DIR?>assets/img/chemistry-features/icon.png" alt="icon">
                    <p><?=$item;?></p>
                </div>
            <?endforeach;?>
        </div>
    </div>
</section>
<?endif;?>
