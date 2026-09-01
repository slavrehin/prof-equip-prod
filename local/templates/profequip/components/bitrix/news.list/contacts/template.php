<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;
?>

<div class="contacts-content__text">
        <?foreach ($arResult["ITEMS"] as $item):?>
            <?$this->AddEditAction($item['ID'], $item['EDIT_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_EDIT"));
            $this->AddDeleteAction($item['ID'], $item['DELETE_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));?>
                        <div class="contacts__row" id="<?=$this->GetEditAreaId($item['ID']);?>">
                            <p class="title"><?= $item["~NAME"] ?></p>
                            <div class="contacts__items">
                                <?if ($item["PROPERTIES"]["ADDRESS"]["~VALUE"]):?>
                                <div class="contacts__item"><img src="<?=LAYOUT_DIR?>assets/img/contacts/1.png" alt="contacts icon">
                                    <div class="contacts__item__links">
                                        <p><?= $item["PROPERTIES"]["ADDRESS"]["~VALUE"]["TEXT"] ?></p>
                                    </div>
                                </div>
                                <?endif;?>
                                <?if ($item["PROPERTIES"]["PHONE"]["~VALUE"] || $item["PROPERTIES"]["EMAIL"]["~VALUE"]):?>
                                <div class="contacts__item">
                                    <img src="<?=LAYOUT_DIR?>assets/img/contacts/<?=empty($item["PROPERTIES"]["EMAIL"]["~VALUE"])?"3":"2";?>.png" alt="contacts icon">
                                    <div class="contacts__item__links">
                                        <?if ($item["PROPERTIES"]["PHONE"]["~VALUE"]):?>
                                            <a href="tel:<?=$item["PROPERTIES"]["PHONE"]["~VALUE"];?>"><?=$item["PROPERTIES"]["PHONE"]["~VALUE"];?> </a>
                                        <?endif;?>
                                        <?if ($item["PROPERTIES"]["EMAIL"]["~VALUE"]):?>
                                            <a href="mailto:<?=$item["PROPERTIES"]["EMAIL"]["~VALUE"];?>"><?=$item["PROPERTIES"]["EMAIL"]["~VALUE"];?></a>
                                        <?endif;?>
                                    </div>   
                                </div>
                                <?endif;?>
                            </div>
                        </div>
        <?endforeach;?>
</div>