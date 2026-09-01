<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$this->setFrameMode(true);

if (empty($arResult["ITEMS"])) return;
?>

<section class="about-links">
    <div class="about-links__inner container">
        <h2>Услуги</h2>
        <ul>
        <?foreach ($arResult["ITEMS"] as $item):?>
            <li><a href="<?$item["DETAIL_PAGE_URL"]?>"><?=$item["NAME"];?></a></li>
        <?endforeach;?>
        </ul>
    </div>
</section>
