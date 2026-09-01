<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
$this->setFrameMode(true);
?>

<?php if (!empty($arResult["NAVIGATION"]["PREV"]) || !empty($arResult["NAVIGATION"]["NEXT"])): ?>
<div class="navigation-links">
    <div class="navigation-links__inner container">
        
        <?php if (!empty($arResult["NAVIGATION"]["PREV"])): 
            $prev = $arResult["NAVIGATION"]["PREV"];
        ?>
        <a class="navigation__link" href="<?=$prev["DETAIL_PAGE_URL"]?>">
            <svg>
                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-left"></use>
            </svg>
            <div class="navigation__link-text">
                <p class="hint">Предыдущая</p>
                <p class="title"><?=$prev["NAME"]?></p>
            </div>
        </a>
        <?php endif; ?>
        
        <?php if (!empty($arResult["NAVIGATION"]["NEXT"])): 
            $next = $arResult["NAVIGATION"]["NEXT"];
        ?>
        <a class="navigation__link" href="<?=$next["DETAIL_PAGE_URL"]?>">
            <div class="navigation__link-text">
                <p class="hint">Следующая</p>
                <p class="title"><?=$next["NAME"]?></p>
            </div>
            <svg>
                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-right"></use>
            </svg>
        </a>
        <?php endif; ?>
        
    </div>
</div>
<?php endif; ?>