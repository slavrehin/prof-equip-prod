<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Политика ООО Профитекс-Люкс в отношении обработки персональных данных. Юридический и фактический адрес 127106, г. Москва, ул. Гостиничная, д. 3.");
$APPLICATION->SetPageProperty("title", "Политика конфиденциальности в отношении персональных данных.");
$APPLICATION->SetTitle("Политика конфиденциальности");
?>
        <section class="policy-content">
            <div class="policy-content__inner container">
                <?$APPLICATION->IncludeFile('/include/privacy.php', false, ['MODE' => 'html', 'NAME' => 'Текст']);?>
            </div>
        </section>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>