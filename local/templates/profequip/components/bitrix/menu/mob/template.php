<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult['MENU_TREE'])) return;
?>

<div class="menu__navigation accordion">
    <?php foreach ($arResult['MENU_TREE'] as $item): 
        if ($item['PERMISSION'] <= 'D') continue;
        
        $hasChildren = !empty($item['CHILDREN']);
        $linkClass = 'menu__link' . ($item['SELECTED'] ? ' selected' : '');
    ?>
        <?php if ($hasChildren): ?>
            <div class="accordion__item">
                <button class="btn accordion__title" type="button">
                    <span class="accordion__title__text"><?= $item['TEXT'] ?></span>
                    <span class="accordion__icon">
                        <svg>
                            <use xlink:href="<?= LAYOUT_DIR ?>assets/img/sprite.svg#accordion-arrow"></use>
                        </svg>
                    </span>
                </button>
                <div class="accordion__content">
                    <?php foreach ($item['CHILDREN'] as $child): 
                        if ($child['PERMISSION'] <= 'D') continue;
                        $childClass = 'footer__link' . ($child['SELECTED'] ? ' selected' : '');
                    ?>
                        <a class="<?= $childClass ?>" href="<?= $child['LINK'] ?>"><?= $child['TEXT'] ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <a class="<?= $linkClass ?>" href="<?= $item['LINK'] ?>"><?= $item['TEXT'] ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
