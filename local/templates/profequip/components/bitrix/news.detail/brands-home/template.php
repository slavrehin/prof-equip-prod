<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
?>

<?
// Получаем все свойства элемента
$props = $arResult['PROPERTIES'];
?>
<?if (!empty($props['BRANDS']['VALUE'])): ?>
<section class="brands">
    <div class="brands__inner container">
        <div class="brands__title-wrapper">
            <h2 class="brands__title">Бренды</h2><a href="/brends/" class="btn link__btn" type="button"><span class="eye__icon"><svg>
                        <use xlink:href="<?=LAYOUT_DIR?>assets/img/sprite.svg#eye"></use>
                    </svg></span><span class="btn__text">Все бренды</span></a>
        </div>
        <div class="brands__list">
            <?foreach ($props['BRANDS']['VALUE'] as $imageId): ?>
                <div class="client-card">
                    <picture>
                        <source srcset="<?=CFile::GetPath($imageId)?>" type="image/webp">
                        <img src="<?=CFile::GetPath($imageId)?>" alt="supplier">
                    </picture>
                </div>
            <?endforeach; ?>
        </div>
    </div>
</section>
<?endif;?>