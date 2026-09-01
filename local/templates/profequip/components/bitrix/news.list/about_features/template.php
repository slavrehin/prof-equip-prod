<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;
?>

<section class="about-features">
    <div class="about-features__inner container">
        <?foreach ($arResult["ITEMS"] as $item):?>
            <?$this->AddEditAction($item['ID'], $item['EDIT_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_EDIT"));
            $this->AddDeleteAction($item['ID'], $item['DELETE_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));?>
            <div class="about-feature__item" id="<?=$this->GetEditAreaId($item['ID']);?>">
                <div class="icon"><img src="<?=CFile::GetPath($item['PROPERTIES']['IMG_BLOCK']['VALUE'])?>" alt="feature"></div>
                <p class="feature__number"><?=$item['PROPERTIES']['NUM']['~VALUE']?></p>
                <p class="feature__title"><?=$item['PROPERTIES']['DESCR']['~VALUE']?></p>
            </div>
        <?endforeach;?>
    </div>
</section>