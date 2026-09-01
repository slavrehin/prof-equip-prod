<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>

<section class="chemistry-steps">
    <div class="chemistry-steps__inner container">
        <h2 class="chemistry-steps__title">ЭТАПЫ СОТРУДНИЧЕСТВА</h2>
        <div class="chemistry-steps__list">
        <?php
        // Разбиваем массив на две части по 5 элементов
        $firstChunk = array_slice($arResult["ITEMS"], 0, 5);
        $secondChunk = array_slice($arResult["ITEMS"], 5, 5);
        ?>

        <!-- Первая строка (первые 5 элементов) -->
        <div class="row">
            <? foreach ($firstChunk as $key=>$arItem): 
                $this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
                $this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
                
                $iconSrc = '';
                if (!empty($arItem["PROPERTIES"]["ICONS"]["VALUE"])) {
                    $iconId = $arItem["PROPERTIES"]["ICONS"]["VALUE"];
                    
                    // Для SVG используем оригинальный файл без ресайза
                    $arFile = CFile::GetFileArray($iconId);
                    if ($arFile) {
                        $fileExt = pathinfo($arFile['FILE_NAME'], PATHINFO_EXTENSION);
                        if (strtolower($fileExt) == 'svg') {
                            $iconSrc = $arFile['SRC'];
                        } else {
                            $arImage = CFile::ResizeImageGet(
                                $iconId,
                                array('width' => 100, 'height' => 100),
                                BX_RESIZE_IMAGE_PROPORTIONAL,
                                true
                            );
                            $iconSrc = $arImage['src'];
                        }
                    }
                }
                
                // Получаем название и текст
                $stepTitle = $arItem["NAME"];
                $stepText = $arItem["PREVIEW_TEXT"];
            ?>
            <div class="chemistry-step__item">
                <div class="image-wrapper">
                    <picture>
                        <source srcset="<?=$iconSrc?>, <?=$iconSrc?> 2x" type="image/webp">
                        <img src="<?=$iconSrc?>" srcset="<?=$iconSrc?>, <?=$iconSrc?>2x" alt="step">
                    </picture>
                </div>
                <div class="chemistry-step__content">
                    <p class="title"><?=($key+1);?>. <?=$stepTitle?></p>
                    <p class="descr"><?=$stepText?></p>
                </div>
            </div>
            <? endforeach; ?>
        </div>

        <!-- Вторая строка (следующие 5 элементов) -->
        <? if (!empty($secondChunk)): ?>
        <div class="row">
            <? foreach ($secondChunk as $key=>$arItem): 
                $this->AddEditAction($arItem['ID'], $arItem['EDIT_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_EDIT"));
                $this->AddDeleteAction($arItem['ID'], $arItem['DELETE_LINK'], CIBlock::GetArrayByID($arItem["IBLOCK_ID"], "ELEMENT_DELETE"), array("CONFIRM" => GetMessage('CT_BNL_ELEMENT_DELETE_CONFIRM')));
                
                $iconSrc = '';
                if (!empty($arItem["PROPERTIES"]["ICONS"]["VALUE"])) {
                    $iconId = $arItem["PROPERTIES"]["ICONS"]["VALUE"];
                    
                    // Для SVG используем оригинальный файл без ресайза
                    $arFile = CFile::GetFileArray($iconId);
                    if ($arFile) {
                        $fileExt = pathinfo($arFile['FILE_NAME'], PATHINFO_EXTENSION);
                        if (strtolower($fileExt) == 'svg') {
                            $iconSrc = $arFile['SRC'];
                        } else {
                            $arImage = CFile::ResizeImageGet(
                                $iconId,
                                array('width' => 100, 'height' => 100),
                                BX_RESIZE_IMAGE_PROPORTIONAL,
                                true
                            );
                            $iconSrc = $arImage['src'];
                        }
                    }
                }
                
                // Получаем название и текст
                $stepTitle = $arItem["NAME"];
                $stepText = $arItem["PREVIEW_TEXT"];
            ?>
            <div class="chemistry-step__item">
                <div class="image-wrapper">
                    <picture>
                        <source srcset="<?=$iconSrc?>, <?=$iconSrc?> 2x" type="image/webp">
                        <img src="<?=$iconSrc?>" srcset="<?=$iconSrc?>, <?=$iconSrc?>2x" alt="step">
                    </picture>
                </div>
                <div class="chemistry-step__content">
                    <p class="title"><?=($key+6);?>. <?=$stepTitle?></p>
                    <p class="descr"><?=$stepText?></p>
                </div>
            </div>
            <? endforeach; ?>
        </div>
        <? endif; ?>
        </div>
    </div>
</section>
