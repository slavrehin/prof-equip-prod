<? 
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
$this->setFrameMode(true);
?>

        <?=$arResult["~PREVIEW_TEXT"];?>
<?
$tabsItems = $arResult['PROPERTIES']['TABS']['~VALUE']; // HTML-тексты табов
$tabsTitles = $arResult['PROPERTIES']['TABS']['~DESCRIPTION']; // Заголовки табов

// Проверяем, что количество заголовков соответствует количеству текстов
if (count($tabsTitles) === count($tabsItems) && count($tabsItems) > 0):
?>



        <!-- DESKTOP версия (табы) -->
        <div class="text-block__tabs">
            <div class="tabs">
                <? foreach ($tabsTitles as $key => $title): ?>
                    <button class="btn tab <?=$key === 0 ? 'active' : ''?>" data-tab="<?=$key?>">
                        <?=htmlspecialchars($title)?>
                    </button>
                <? endforeach;?>
            </div>

            <? foreach ($tabsItems as $key => $item): ?>
                <div class="tab-content">
                    <?=$item["TEXT"]?>
                </div>
            <? endforeach;?>
        </div>

        <div class="text-block__accordion-wrapper">
            <div class="text-block__accordion accordion">
                <? foreach ($tabsItems as $key => $item): ?>
                    <div class="accordion__item">
                        <button class="btn accordion__title">
                            <span class="accordion__title__text">
                                <?=htmlspecialchars($tabsTitles[$key])?>
                            </span>
                        </button>
                        <div class="accordion__content">
                            <?=$item["TEXT"]?>
                        </div>
                    </div>
                <? endforeach;?>
            </div>
        </div>


<?endif;?>
