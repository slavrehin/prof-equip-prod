<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

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
<div class="count-search"><?= $arResult['NAV_RESULT']->NavRecordCount ?></div>
<div class="page-item-list-wrap">
<?php if (!empty($arResult['ITEMS'])): ?>
                <?php foreach ($arResult['ITEMS'] as $item): ?>

<a href="<?=$item['DETAIL_PAGE_URL'];?>" class="search-item">
            <div class="image-wrapper">
                <?if ($item['PREVIEW_PICTURE']["ID"]):?>
				<img src="<?=CFile::ResizeImageGet(
                        $item['PREVIEW_PICTURE']["ID"],
                        array("width" => 200, "height" => 200),
                        BX_RESIZE_IMAGE_PROPORTIONAL,
                        true,
                        array()
                    )["src"];?>" srcset="<?=CFile::ResizeImageGet(
                        $item['PREVIEW_PICTURE']["ID"],
                        array("width" => 200, "height" => 200),
                        BX_RESIZE_IMAGE_PROPORTIONAL,
                        true,
                        array()
                    )["src"];?> 1x, <?=CFile::ResizeImageGet(
                        $item['PREVIEW_PICTURE']["ID"],
                        array("width" => 400, "height" => 400),
                        BX_RESIZE_IMAGE_PROPORTIONAL,
                        true,
                        array()
                    )["src"];?> 2x" alt="product">
                    <?else:?>
                        
                    <?endif;?>    
            </div>
            <div class="search__content">
                <div class="search__info">
                    <p class="search__price"><?=$item["PRICES_DATA"]["RRC_PRICE"];?> ₽</p>
                    <p class="search__title"><?=$item['NAME'];?></p>
                </div>
               
            </div>  
        </a>
        
                <?php endforeach; ?>
<?php endif; ?>
</div>

