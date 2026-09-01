<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>

<?php if (!empty($arResult['SECTIONS'])): ?>
<section class="catalog-links-list">
    <div class="catalog-links-list__inner container">
        <h2 class="catalog-links-list__title">КАТАЛОГ</h2>
        <div class="catalog-links-list__items">
            <?php foreach ($arResult['SECTIONS'] as $arSection): 
                // Получаем изображение раздела
                $pictureSrc = '';
                $pictureSrc2x = '';
                $pictureWebp = '';
                $pictureWebp2x = '';
                
                if (!empty($arSection['PICTURE'])) {
                    // Ресайз для обычной версии (400x300)
                    $arImage = CFile::ResizeImageGet(
                        $arSection['PICTURE'],
                        array('width' => 400, 'height' => 300),
                        BX_RESIZE_IMAGE_PROPORTIONAL,
                        true
                    );
                    $pictureSrc = $arImage['src'];
                    
                    // Retina версия (800x600)
                    $arImage2x = CFile::ResizeImageGet(
                        $arSection['PICTURE'],
                        array('width' => 800, 'height' => 600),
                        BX_RESIZE_IMAGE_PROPORTIONAL,
                        true
                    );
                    $pictureSrc2x = $arImage2x['src'];
                
                }
                
                // Ссылка на раздел
                $sectionUrl = $arSection['SECTION_PAGE_URL'];
            ?>
                <a class="catalog-link-card" href="<?=$sectionUrl?>">
                    <picture>
                        <img src="<?=$pictureSrc?>" 
                             srcset="<?=$pictureSrc?> 1x, <?=$pictureSrc2x?> 2x" 
                             alt="<?=htmlspecialchars($arSection['NAME'])?>"
                             loading="lazy">
                    </picture>
                    <div class="catalog-link-card__content">
                        <p class="title"><?=htmlspecialchars($arSection['NAME'])?></p>
                        <p class="link">Подробнее</p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>