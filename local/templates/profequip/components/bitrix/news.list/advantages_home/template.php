<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
?>

<?if (!empty($arResult["ITEMS"])):?>
<section class="advantages">
    <div class="advantages__inner container">
        <h2 class="advantages__title">Преимущества</h2>
        <div class="advantages__list">
            <?foreach($arResult["ITEMS"] as $arItem):?>
                <?
                $this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
                $this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
                ?>
                <div class="advantage-card" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
                    <div class="advantage-card__content">
                        <p class="title"><?=$arItem["NAME"]?></p>
                        <p class="text"><?=$arItem["PREVIEW_TEXT"]?></p>
                    </div>
                    <?if ($arItem["PREVIEW_PICTURE"]):?>
                        <div class="image-wrapper">
                            <picture>
                                <?php
                                $picture = CFile::GetFileArray($arItem["PREVIEW_PICTURE"]["ID"]);
                                $src = $picture["SRC"];
                                ?>
                                <source srcset="<?=$src?>, <?=$src2x?> 2x" type="image/webp">
                                <img src="<?=$src?>" srcset="<?=$src?>, <?=$src2x?> 2x" alt="<?=$arItem["NAME"]?>">
                            </picture>
                        </div>
                    <?endif;?>
                </div>
            <?endforeach;?>
        </div>
    </div>
</section>
<?endif;?>