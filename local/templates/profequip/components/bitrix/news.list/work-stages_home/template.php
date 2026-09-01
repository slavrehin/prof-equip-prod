<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
?>

<?if (!empty($arResult["ITEMS"])):?>
<div class="work-stages">
    <div class="work-stages__inner container">
        <h2 class="work-stages__title">Этапы работы</h2>
        <div class="work-stages__list">
            <?foreach($arResult["ITEMS"] as $stageNumber => $arItem):?>
                <?
                $this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
                $this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
                ?>
                <div class="work-stage-card" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
                    <div class="work-stage-card_top">
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
                        <p class="number">0<?=$stageNumber+1?></p>
                    </div>
                    <div class="work-stage-card__content">
                        <p class="title"><?=$arItem["NAME"]?></p>
                        <p class="text"><?=$arItem["PREVIEW_TEXT"]?></p>
                    </div>
                </div>
            <?endforeach;?>
        </div>
    </div>
</div>
<?endif;?>