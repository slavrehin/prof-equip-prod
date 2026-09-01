<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
?>

<?if (!empty($arResult["ITEMS"])):?>
    <?foreach($arResult["ITEMS"] as $arItem):?>
        <?
        $this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
        $this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
        
        $link = $arItem['PROPERTIES']['LINK']['VALUE'] ?: $arItem['DETAIL_PAGE_URL'];
        ?>
        <a class="header-catalog-card" href="<?=$link?>" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
                    <div class="image-wrapper">
                        <picture>
                            <?php
                            $picture = CFile::GetFileArray($arItem["PREVIEW_PICTURE"]["ID"]);
                            $src = $picture["SRC"];
                            ?>
                            <source srcset="<?=$src?>, <?=$src?> 2x" type="image/webp">
                            <img src="<?=$src?>" srcset="<?=$src?>, <?=$src?> 2x" alt="<?=$arItem["NAME"]?>">
                        </picture>
                        <div class="eye__icon"><svg>
                                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#eye"></use>
                            </svg></div>
                    </div>
                    <div class="catalog-card__content">
                        <p class="title"><?=$arItem["NAME"]?></p>
                        <p class="link">Подробнее</p>
                    </div>
                </a>
    <?endforeach;?>
<?endif;?>