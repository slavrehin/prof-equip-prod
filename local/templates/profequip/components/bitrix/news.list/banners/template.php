<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;
        function getResizedImageBanners($id, $width, $height = 1000) {
            if (empty($id)) return null;
            return CFile::ResizeImageGet(
                $id,
                array('width' => $width, 'height' => $height),
                BX_RESIZE_IMAGE_PROPORTIONAL,
                true
            );
        }
?>

<div class="banner-template">
    <div class="swiper-wrapper">
        <? foreach ($arResult["ITEMS"] as $item): ?>
        <?
        // Получаем основное изображение
        $fileId = $item["PROPERTIES"]["IMG_DESC"]["VALUE"];
        $fileData = null;
        $fileType = '';
        $filePath = '';
        
        if (!empty($fileId)) {
            $fileData = CFile::GetFileArray($fileId);
            if ($fileData) {
                $filePath = $fileData['SRC'];
                $fileType = $fileData['CONTENT_TYPE'];
            }
        }
        
        // Получаем мобильное изображение из свойства IMG_MOB
        $mobFileId = $item["PROPERTIES"]["IMG_MOB"]["VALUE"];
        $mobFileData = null;
        $mobFilePath = '';
        
        if (!empty($mobFileId)) {
            $mobFileData = CFile::GetFileArray($mobFileId);
            if ($mobFileData) {
                $mobFilePath = $mobFileData['SRC'];
            }
        }
        
        // Получаем изображение для превью (на случай если видео не загрузится)
        $pictureId = 0;
        if (!empty($item["PREVIEW_PICTURE"]["ID"])) {
            $pictureId = $item["PREVIEW_PICTURE"]["ID"];
        } elseif (!empty($item["DETAIL_PICTURE"]["ID"])) {
            $pictureId = $item["DETAIL_PICTURE"]["ID"];
        }
        

        
        // Основное изображение (десктоп)
        $desktopImg = null;
        $desktopImg2x = null;
        $desktopImgWebp = null;
        $desktopImgWebp2x = null;
        
        // Мобильное изображение
        $mobImg = null;
        $mobImg2x = null;
        $mobImgWebp = null;
        $mobImgWebp2x = null;
        
        // Получаем изображения из IMG_DESC или из PREVIEW/DETAIL
        if ($fileData && strpos($fileType, 'image/') === 0) {
            // Используем IMG_DESC
            $desktopImg = getResizedImageBanners($fileId, 1920, 1080);
            $desktopImg2x = getResizedImageBanners($fileId, 3840, 2160);
            
            // WebP версии (если нужно конвертировать, но лучше загружать готовые)
            // Для WebP можно использовать CFile::ResizeImageGet с параметрами
            $desktopImgWebp = getResizedImageBanners($fileId, 1920, 1080);
            $desktopImgWebp2x = getResizedImageBanners($fileId, 3840, 2160);
        } elseif ($pictureId > 0) {
            // Используем PREVIEW/DETAIL
            $desktopImg = getResizedImageBanners($pictureId, 1920, 1080);
            $desktopImg2x = getResizedImageBanners($pictureId, 3840, 2160);
            $desktopImgWebp = getResizedImageBanners($pictureId, 1920, 1080);
            $desktopImgWebp2x = getResizedImageBanners($pictureId, 3840, 2160);
        }
        
        // Получаем мобильные изображения
        if (!empty($mobFileData)) {
            $mobImg = getResizedImageBanners($mobFileId, 767, 800);
            $mobImg2x = getResizedImageBanners($mobFileId, 1534, 1600);
            $mobImgWebp = getResizedImageBanners($mobFileId, 767, 800);
            $mobImgWebp2x = getResizedImageBanners($mobFileId, 1534, 1600);
        } else {
            // Если мобильное не загружено - используем десктопное
            if ($desktopImg) {
                $mobImg = getResizedImageBanners($fileId ?: $pictureId, 767, 800);
                $mobImg2x = getResizedImageBanners($fileId ?: $pictureId, 1534, 1600);
                $mobImgWebp = getResizedImageBanners($fileId ?: $pictureId, 767, 800);
                $mobImgWebp2x = getResizedImageBanners($fileId ?: $pictureId, 1534, 1600);
            }
        }
        
        $itemName = htmlspecialchars($item["NAME"]);
        $linkUrl = $item["PROPERTIES"]["LINK"]["VALUE"];
        
        // Определяем, является ли файл видео
        $isVideo = $fileData && strpos($fileType, 'video/') === 0;
        
        // Определяем расширение файла для fallback
        $fileExt = '';
        if ($desktopImg && $desktopImg['src']) {
            $fileExt = pathinfo($desktopImg['src'], PATHINFO_EXTENSION);
        }
        ?>
        <div class="swiper-slide">
            <a href="<?= $linkUrl ?>">
                <? if ($isVideo): ?>
                    <!-- Видео -->
                    <video src="<?= $filePath ?>" muted preload="auto" playsinline poster="<?= $desktopImg ? $desktopImg['src'] : '' ?>"></video>
                <? elseif ($desktopImg): ?>
                    <picture>
                        <? if ($mobImg): ?>
                            <? if ($mobImgWebp): ?>
                                <source media="(max-width: 767px)" srcset="<?= $mobImgWebp['src'] ?><?= $mobImgWebp2x ? ', ' . $mobImgWebp2x['src'] . ' 2x' : '' ?>" type="image/webp">
                            <? endif; ?>
                            <source media="(max-width: 767px)" srcset="<?= $mobImg['src'] ?><?= $mobImg2x ? ', ' . $mobImg2x['src'] . ' 2x' : '' ?>">
                        <? endif; ?>
                        
                        <? if ($desktopImgWebp): ?>
                            <source srcset="<?= $desktopImgWebp['src'] ?><?= $desktopImgWebp2x ? ', ' . $desktopImgWebp2x['src'] . ' 2x' : '' ?>" type="image/webp">
                        <? endif; ?>
                        
                        <img 
                            src="<?= $desktopImg['src'] ?>" 
                            srcset="<?= $desktopImg['src'] ?><?= $desktopImg2x ? ', ' . $desktopImg2x['src'] . ' 2x' : '' ?>" 
                            alt="<?= $itemName ?>"
                            loading="lazy"
                        >
                    </picture>
                <? endif; ?>
                <?/*<button class="btn slide__btn">Рассчитать проект</button>*/?>
            </a>
        </div>
        <? endforeach; ?>
    </div>
    <div class="banner-pagination pagination"></div>
</div>