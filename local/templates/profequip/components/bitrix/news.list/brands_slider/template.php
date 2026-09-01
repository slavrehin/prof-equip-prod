<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>

<?php if (!empty($arResult["ITEMS"])): ?>
<section class="manufacturers">
    <div class="manufacturers__inner container">
        <h2 class="manufacturers__title">ПРОИЗВОДИТЕЛИ</h2>
        <div class="manufacturers-swiper">
            <div class="swiper-wrapper">
                <?php foreach ($arResult["ITEMS"] as $arItem): 
                    $this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
                    $this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
                    
                    // Получаем логотип из свойства LOGO
                    $logoSrc = '';
                    $logoSrc2x = '';
                    $logoWebp = '';
                    $logoWebp2x = '';
                    
                    if (!empty($arItem["PROPERTIES"]["LOGO"]["VALUE"])) {
                        $logoId = $arItem["PROPERTIES"]["LOGO"]["VALUE"];
                        
                        // Если свойство множественное, берем первый логотип
                        if (is_array($logoId)) {
                            $logoId = reset($logoId);
                        }
                        
                        // Для логотипов используем пропорциональный ресайз с белым фоном (200x100)
                        $arImage = CFile::ResizeImageGet(
                            $logoId,
                            array('width' => 200, 'height' => 100),
                            BX_RESIZE_IMAGE_PROPORTIONAL,
                            true
                        );
                        $logoSrc = $arImage['src'];
                        
                        // Retina версия (400x200)
                        $arImage2x = CFile::ResizeImageGet(
                            $logoId,
                            array('width' => 400, 'height' => 200),
                            BX_RESIZE_IMAGE_PROPORTIONAL,
                            true
                        );
                        $logoSrc2x = $arImage2x['src'];
                    }
                    
                    // Ссылка на страницу бренда
                    $brandUrl = $arItem["DETAIL_PAGE_URL"];
                    
                    // Если есть ссылка на внешний сайт
                    $externalUrl = '';
                    if (!empty($arItem["PROPERTIES"]["WEBSITE_URL"]["VALUE"])) {
                        $externalUrl = $arItem["PROPERTIES"]["WEBSITE_URL"]["VALUE"];
                    }
                    
                    // Выбираем ссылку (приоритет: внешний сайт > страница бренда)
                    $linkUrl = !empty($externalUrl) ? $externalUrl : $brandUrl;
                    
                    // Название бренда для alt
                    $brandName = htmlspecialchars($arItem["NAME"]);
                ?>
                    <div class="swiper-slide" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
                        <a class="client-card link-card" href="<?=$linkUrl?>" <?=!empty($externalUrl) ? 'target="_blank"' : ''?>>
                            <picture>
                                <source srcset="<?=$logoSrc?> 1x, <?=$logoSrc2x?> 2x" type="image/webp">
                                <img src="<?=$logoSrc?>" 
                                     srcset="<?=$logoSrc?> 1x, <?=$logoSrc2x?> 2x" 
                                     alt="<?=$brandName?>"
                                     title="<?=$brandName?>"
                                     loading="lazy">
                            </picture>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="navigation">
                <button class="btn manufacturers__prev" type="button">
                    <svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-left"></use>
                    </svg>
                </button>
                <button class="btn manufacturers__next" type="button">
                    <svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-swiper-right"></use>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</section>
<?endif;?>