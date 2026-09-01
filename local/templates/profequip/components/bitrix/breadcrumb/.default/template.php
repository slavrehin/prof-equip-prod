<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

/**
 * @global CMain $APPLICATION
 */

global $APPLICATION;

//delayed function must return a string
if(empty($arResult))
	return "";

$strReturn = '';

//we can't use $APPLICATION->SetAdditionalCSS() here because we are inside the buffered function GetNavChain()

$strReturn .= '<div class="breadcrumbs" itemscope itemtype="http://schema.org/BreadcrumbList"><div class="breadcrumbs__inner container">';

$itemSize = count($arResult);
for($index = 0; $index < $itemSize; $index++)
{
	$title = htmlspecialcharsex($arResult[$index]["TITLE"]);
	$arrow = '';

	if($arResult[$index]["LINK"] <> "" && $index != $itemSize-1)
	{
		if($index==0) {
			$strReturn .= '<a class="breadcrumb__link" href="'.$arResult[$index]["LINK"].'" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">'.$title.'</a>';
		} else {
			$strReturn .= '<a class="breadcrumb__link" href="'.$arResult[$index]["LINK"].'" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">/ '.$title.'</a>';
		}
	}
	else
	{
		$strReturn .= '<a class="breadcrumb__link" href="'.$arResult[$index]["LINK"].'" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">/ '.$title.'</a>';
	}
}

$strReturn .= '</div></div>';

return $strReturn;

?>      