<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/** @var array $arParams */
/** @var array $arResult */
?>

<?
// Получаем все свойства элемента
$props = $arResult['PROPERTIES'];
?>
<?if (!empty($props['CLIENTS']['VALUE'])): ?>
<section class="clients">
    <div class="clients__inner container">
        <h2 class="clients__title">Наши клиенты</h2>
        <div class="clients__list">
            <?foreach ($props['CLIENTS']['VALUE'] as $imageId): ?>
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
<?endif; ?>