<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
?>
<div class="modal modal--mobile-menu-modal" data-modal="mobile-menu-modal">
    <div class="modal-content">
		<?$APPLICATION->IncludeComponent(
			"bitrix:menu",
			"mob",
			array(
				"ALLOW_MULTI_SELECT" => "N",
				"DELAY" => "N",
				"MAX_LEVEL" => "2",
				"MENU_CACHE_GET_VARS" => array(
				),
				"MENU_CACHE_TIME" => "3600",
				"MENU_CACHE_TYPE" => "N",
				"MENU_CACHE_USE_GROUPS" => "N",
				"ROOT_MENU_TYPE" => "mob",
				"USE_EXT" => "Y",
				"COMPONENT_TEMPLATE" => "footer"
			),
			false
		);?>
    </div>
</div>