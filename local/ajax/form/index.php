<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$response = new \Bitrix\Main\HttpResponse(\Bitrix\Main\Application::getInstance()->getContext());
$formTemplate = $request->get("template_form")?:"fb_form";
$formId = intval($request->get("WEB_FORM_ID"));
$nameProduct = $request->get("name_product")?:"";
?>

	<?$APPLICATION->IncludeComponent(
		"bitrix:form.result.new",
		$formTemplate,
		Array(
			"CACHE_TIME" => "3600",
			"CACHE_TYPE" => "A",
			"CHAIN_ITEM_LINK" => "",
			"CHAIN_ITEM_TEXT" => "",
			"EDIT_URL" => "result_edit.php",
			"IGNORE_CUSTOM_TEMPLATE" => "N",
			"LIST_URL" => "/local/ajax/form/",
			"SEF_MODE" => "N",
			"SUCCESS_URL" => "",
			"USE_EXTENDED_ERRORS" => "N",
			"VARIABLE_ALIASES" => array("RESULT_ID"=>"RESULT_ID","WEB_FORM_ID"=>"WEB_FORM_ID",),
			"WEB_FORM_ID" => $formId,
			"NAME_PRODUCT" => $nameProduct,
			"PREFIX"=>$request->get("is_modal")?"_modal":""
		)
		);
	?>
