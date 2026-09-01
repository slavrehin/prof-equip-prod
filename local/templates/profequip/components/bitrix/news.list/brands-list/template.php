<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/** @var array $arParams */
/** @var array $arResult */
/** @var CBitrixComponentTemplate $this */

$this->setFrameMode(true);
?>

<div class="brands-content__list">
    <?php if (!empty($arResult['SECTIONS'])): ?>
        <?php foreach ($arResult['SECTIONS'] as $section): ?>
            <div class="brand-row">
                <p class="brand-row__title"><?= htmlspecialcharsbx($section['NAME']) ?></p>
                <div class="brand-row__list">
                    <?php foreach ($section['ITEMS'] as $item): 
                        // Получаем изображение
                        $picture = $item['PROPERTIES']['LOGO']['VALUE'];
                        $pictureSrc = '';
                        $pictureSrc2x = '';
                        
                        if ($picture) {
                            $pictureSrc = CFile::GetPath($picture);
                            
                            // Для retina (2x)
                            $arFileTmp = CFile::ResizeImageGet(
                                $picture,
                                ['width' => 200, 'height' => 100],
                                BX_RESIZE_IMAGE_PROPORTIONAL,
                                true
                            );
                            $pictureSrc2x = $arFileTmp['src'];
                        }
                    ?>
                        <a class="brand-card" href="<?= $item['DETAIL_PAGE_URL'] ?: '#' ?>">
                            <picture>
                                <img src="<?= $pictureSrc ?>" 
                                     srcset="<?= $pictureSrc ?><?= $pictureSrc2x ? ', ' . $pictureSrc2x . ' 2x' : '' ?>"
                                     alt="<?= htmlspecialcharsbx($item['NAME']) ?>">
                            </picture>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>Бренды не найдены</p>
    <?php endif; ?>
</div>