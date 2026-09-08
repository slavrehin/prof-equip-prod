<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$response = new \Bitrix\Main\HttpResponse(\Bitrix\Main\Application::getInstance()->getContext());
$formTemplate = $request->get("template_form")?:"fb_form";
$formId = intval($request->get("WEB_FORM_ID"));
$nameProduct = $request->get("name_product")?:"";
// Подпись кнопки, открывшей модалку ("Запросить" / "Получить КП") - используется
// в тексте согласия на обработку персональных данных и на кнопке отправки формы,
// чтобы они совпадали с тем, что клиент реально нажал на странице (см.
// local/templates/profequip/components/bitrix/catalog.item/card/template.php
// и .../catalog/catalog/bitrix/catalog.element/.default/template.php).
$ctaLabel = $request->get("cta_label")?:"Запросить";
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
			"CTA_LABEL" => $ctaLabel,
			"PREFIX"=>$request->get("is_modal")?"_modal":""
		)
		);
	?>
