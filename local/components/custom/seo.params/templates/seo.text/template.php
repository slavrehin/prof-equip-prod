<?

/** @var array $arResult */
if(!$arResult['PROPS']['TEXT_TITLE']&&!$arResult['~PREVIEW_TEXT']&&!$arResult['~DETAIL_TEXT'])return false;
$props = array_column($arResult['PROPS'], 'VALUE', 'CODE');

?>

<div class="section section-text-block">
    <div class="container">
        <div class="section-content">
            <div class="row">
                <div class="col text-block-col">
                    <div class="text-block-logo">
                        <img src="<?=FRONT_DIRECTORY?>images/text-block-logo.svg" width="102" height="102">
                    </div>
                    <?php if ($props['TEXT_TITLE']): ?>
                        <h1><?= $props['TEXT_TITLE'] ?></h1>
                    <?php endif ?>
                    <?= $arResult['~PREVIEW_TEXT'] ?>
                    <div class="expandable-wrapper">
                        <div class="expandable-content">
                            <?= $arResult['~DETAIL_TEXT'] ?>
                        </div>
                        <div class="expandable-trigger-wrapper expandable-trigger-wrapper-alt">
                            <div class="expandable-trigger expandable-trigger-alt"><svg width="25" height="25" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <line y1="12.5" x2="25" y2="12.5" stroke="#C09B4D"/>
                                    <line class="line-v" x1="12.5" y1="2.18552e-08" x2="12.5" y2="25" stroke="#C09B4D"/>
                                </svg>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
