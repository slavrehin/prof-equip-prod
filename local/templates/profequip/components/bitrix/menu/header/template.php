<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

/** @var array $arParams */
/** @var array $arResult */
/** @var CBitrixComponentTemplate $this */

// Получаем классы из параметров элемента меню (четвертый параметр в массиве пункта меню)
$directionBtnClass = '';
$servicesBtnClass = '';

foreach ($arResult as $item) {
    if (!empty($item['PARAMS']['CLASS'])) {
        if ($item['PARAMS']['CLASS'] == 'direction__btn') {
            $directionBtnClass = 'direction__btn';
        }
        if ($item['PARAMS']['CLASS'] == 'services__btn') {
            $servicesBtnClass = 'services__btn';
        }
    }
}
?>

<nav class="header__navigation">
    <?php foreach ($arResult as $item): ?>
        <?php
        // Определяем, есть ли специальный класс для этого пункта
        $btnClass = '';
        if (!empty($item['PARAMS']['CLASS'])) {
            $btnClass = $item['PARAMS']['CLASS'];
        }
        
        // Если это кнопка с выпадающим меню (Направления или Услуги)
        if ($btnClass == 'direction__btn' || $btnClass == 'services__btn'): ?>
            <button class="btn nav__link <?= $btnClass ?>" type="button">
                <?= $item['TEXT'] ?>
            </button>
        <?php else: ?>
            <a class="nav__link" href="<?= $item['LINK'] ?>">
                <?= $item['TEXT'] ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>