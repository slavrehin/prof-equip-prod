<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;
?>

<section class="news">
    <div class="news__inner container">
        <div class="news__title-wrapper">
            <h2 class="news__title">Новости</h2><a href="/novosti/" class="btn link__btn" type="button"><span class="btn__text">Все новости</span><span class="arrow__icon"><svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-top"></use>
                    </svg></span></a>
        </div>
        <div class="news__list">
            <? foreach ($arResult["ITEMS"] as $item): ?>
                <?
                    $timestamp = MakeTimeStamp($item["ACTIVE_FROM"] ?: $item["DATE_CREATE"]);
                    $date = FormatDate("d.m.Y", $timestamp);
                    $detailUrl = $item["DETAIL_PAGE_URL"];
                    $pictureId = 0;
                    if (!empty($item["PREVIEW_PICTURE"]["ID"])) {
                        $pictureId = $item["PREVIEW_PICTURE"]["ID"];
                    } elseif (!empty($item["DETAIL_PICTURE"]["ID"])) {
                        $pictureId = $item["DETAIL_PICTURE"]["ID"];
                    }
                    
                    if ($pictureId > 0) {
                        $arImage = CFile::ResizeImageGet(
                            $pictureId,
                            array('width' => 500, 'height' => 500),
                            BX_RESIZE_IMAGE_PROPORTIONAL,
                            true
                        );
                        $imgSrc = $arImage['src'];
                        
                        $arImage2x = CFile::ResizeImageGet(
                            $pictureId,
                            array('width' => 1000, 'height' => 1000),
                            BX_RESIZE_IMAGE_PROPORTIONAL,
                            true
                        );
                        $imgSrc2x = $arImage2x['src'];

                        
                    }
                    
                    $itemName = htmlspecialchars($item["NAME"]);
                    ?>
            <div class="news-card">
                <div class="image-wrapper">
                    <picture>
                        <source srcset="<?=$imgSrc?> 1x, <?=$imgSrc2x?> 2x" type="image/webp">
                        <img src="<?=$imgSrc?>" 
                                srcset="<?=$imgSrc?> 1x, <?=$imgSrc2x?> 2x" 
                                loading="lazy">
                    </picture>
                </div>
                <div class="news-card__content">
                    <p class="date"><?=$date;?></p>
                    <a href="<?=$detailUrl;?>" class="title"><?=$itemName?></a>
                    <a href="<?=$detailUrl;?>" class="btn link__btn" type="button"><span class="btn__text">Подробнее</span><span class="arrow__icon"><svg>
                                <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-top"></use>
                            </svg></span></a>
                </div>
            </div>    
        <? endforeach; ?>

        </div><a class="btn link__btn fill black btn_mob" href="/novosti/"><span class="btn__text">Читать все новости</span><span class="arrow__icon"><svg>
                    <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#arrow-top"></use>
                </svg></span></a>
    </div>
</section>