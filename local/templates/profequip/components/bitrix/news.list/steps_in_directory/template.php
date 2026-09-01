<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>

<?php if (!empty($arResult["ITEMS"])): ?>
<section class="steps">
    <div class="steps__inner container">
        <h2 class="steps__title">ЭТАПЫ КОМПЛЕКСНОГО ОСНАЩЕНИЯ</h2>
        <div class="steps__list">
            <?php foreach ($arResult["ITEMS"] as $arItem): 
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
                <div class="steps-item" id="<?=$this->GetEditAreaId($arItem['ID']);?>">
                    <div class="steps__icon">
                        <?php if (!empty($iconSrc)): ?>
                            <img src="<?=$iconSrc?>" alt="<?=htmlspecialchars($stepTitle)?>">
                        <?php endif; ?>
                    </div>
                    <div class="steps__content">
                        <p class="title"><?=$stepTitle?></p>
                        <p class="text"><?=$stepText?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>