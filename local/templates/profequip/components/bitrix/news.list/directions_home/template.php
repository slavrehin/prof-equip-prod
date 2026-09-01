<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
?>

<?if (!empty($arResult["ITEMS"])):?>
<section class="areas-activity">
    <div class="areas-activity__inner container">
        <h2 class="areas-activity__title">Направления деятельности</h2>
        <div class="areas-activity__list">
            <?foreach($arResult["ITEMS"] as $arItem):?>
                <?
                $this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
                $this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
                
                // Получаем значения свойств
                $link = $arItem['PROPERTIES']['LINK']['VALUE'] ?: '#';
                $linkText = $arItem['PROPERTIES']['LINK_TEXT']['VALUE'] ?: 'Подробнее об услуге';
                ?>
                <div class="areas-activity-card" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
                    <?if ($arItem["PREVIEW_PICTURE"]):?>
                        <div class="image-wrapper">
                            <picture>
                                <?php
                                $picture = CFile::GetFileArray($arItem["PREVIEW_PICTURE"]["ID"]);
                                $src = $picture["SRC"];
                                ?>
                                <source srcset="<?=$src?>, <?=$src?> 2x" type="image/webp">
                                <img src="<?=$src?>" srcset="<?=$src?>, <?=$src?> 2x" alt="<?=$arItem["NAME"]?>">
                            </picture>
                        </div>
                    <?endif;?>
                    <div class="areas-activity-card__content">
                        <a href="<?=$link?>" class="title"><?=$arItem["NAME"]?></a>
                        <p class="text"><?=$arItem["PREVIEW_TEXT"]?></p>
                        <a class="btn link__btn" href="<?=$link?>">
                            <span class="btn__text"><?=$linkText?></span>
                            <span class="arrow__icon">
                                <svg>
                                    <use xlink:href="<?=LAYOUT_DIR;?>assets/img/sprite.svg#arrow-top"></use>
                                </svg>
                            </span>
                        </a>
                    </div>
                </div>
            <?endforeach;?>
        </div>
    </div>
</section>
<?endif;?>