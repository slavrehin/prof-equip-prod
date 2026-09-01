<?php
// Получаем дочерние разделы текущего раздела
$currentSectionId = $arResult["SECTION"]["ID"]; // или $arParams["SECTION_ID"]
$childSections = array();

if ($currentSectionId) {
    foreach ($arResult["SECTIONS"] as $section) {
        if ($section["IBLOCK_SECTION_ID"] == $currentSectionId) {
            $childSections[] = $section;
        }
    }
}

?>
<div class="catalog__categories">
    <?php foreach ($childSections as $childSection): ?>
        <?php
        $imageSrc = $childSection["PICTURE"] 
            ? CFile::GetPath($childSection["PICTURE"]["ID"]) 
            :'';
        
        ?>
        <a class="category__link" href="<?= $childSection["SECTION_PAGE_URL"] ?>">
            <?= htmlspecialchars_decode($childSection["NAME"]) ?>
            <picture>
                <source srcset="<?= $imageSrc ?>" type="image/webp">
                <img src="<?= $imageSrc ?>" srcset="<?= $imageSrc ?>" alt="<?= $childSection["NAME"] ?>" loading="lazy">
            </picture>
        </a>
    <?php endforeach; ?>
</div>
