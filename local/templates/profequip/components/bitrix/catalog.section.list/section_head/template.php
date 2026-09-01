<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Page\Asset;

// Проверяем, есть ли разделы для вывода
if (!$arResult["SECTIONS"] || count($arResult["SECTIONS"]) <= 0) {
    return;
}
?>

<div class="header-catalog__inner container">
    <?php foreach ($arResult["SECTIONS"] as $arSection): ?>
        <?php
        $sectionImage = '';
        $sectionImageWebp = '';
        
        if ($arSection["PICTURE"]) {
            $sectionImage = CFile::GetPath($arSection["PICTURE"]["ID"]);
        }
        
        // Проверяем свойство UF_PROF (профессиональный раздел)
        $isProf = false;
        if (!empty($arSection["UF_PROF"]) && $arSection["UF_PROF"] == 1) {
            $isProf = true;
        }
        ?>
        
        <a class="header-catalog-card" href="<?= $arSection["SECTION_PAGE_URL"] ?>">
            <div class="image-wrapper">
                <picture>
                    <?php if ($sectionImage): ?>
                        <source srcset="<?= $sectionImage ?>" type="image/webp">
                    <?php endif; ?>
                    
                    <?php if ($sectionImage): ?>
                        <img src="<?= $sectionImage ?>" 
                             srcset="<?= $sectionImage ?>" 
                             alt="<?= $arSection["NAME"] ?>"
                             loading="lazy">
                    <?php endif; ?>
                </picture>
                
                    <div class="eye__icon">
                        <svg>
                            <use xlink:href="<?= LAYOUT_DIR ?>assets/img/sprite.svg#eye"></use>
                        </svg>
                    </div>
            </div>
            
            <div class="catalog-card__content">
                <?php if ($isProf): ?>
                    <span class="badge">professional</span>
                <?php endif; ?>
                
                <p class="title"><?= htmlspecialchars_decode($arSection["NAME"]) ?></p>
                <p class="link">Подробнее</p>
            </div>
        </a>
    <?php endforeach; ?>
</div>