<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;
?>

<div class="faq" itemscope="" itemtype="https://schema.org/FAQPage">
    <div class="faq__inner container">
        <h2 class="faq__title">Часто задаваемые вопросы</h2>
        <div class="faq__list accordion">
            <?foreach ($arResult["ITEMS"] as $key=>$item):?>
                <?$this->AddEditAction($item['ID'], $item['EDIT_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_EDIT"));
                $this->AddDeleteAction($item['ID'], $item['DELETE_LINK'], CIBlock::GetArrayByID($item["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));?>
            <div class="faq__item accordion__item <?if ($key==0):?>active<?endif;?>" itemscope="" itemprop="mainEntity" itemtype="https://schema.org/Question"><button class="btn accordion__title"><span class="filter__title" itemprop="name"><?= $item['~NAME'];?></span><span class="filter__icon"><svg>
                            <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#accordion-arrow2"></use>
                        </svg></span></button>
                <div class="accordion__content" itemscope="" itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
                    <div itemprop="text">
                        <?= $item['~DETAIL_TEXT'];?>
                    </div>
                </div>
            </div>
            <?endforeach;?>
        </div>
    </div>
</div>