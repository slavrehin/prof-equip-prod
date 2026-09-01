<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

foreach ($arResult["arAnswers"] as $key=>$item){
	$arResult["QUESTION_NAME"][$key] = "form_".$item[0]["FIELD_TYPE"]."_".$item[0]["ID"];
}
foreach ($arResult["arQuestions"] as $key=>$item){
	$arResult["QUESTION_TITLE"][$key] = $item["TITLE"];
}