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

$strReturn .= '<div class="breadcrumbs" itemscope itemtype="https://schema.org/BreadcrumbList"><div class="breadcrumbs__inner container">';

$itemSize = count($arResult);
for($index = 0; $index < $itemSize; $index++)
{
	$title = htmlspecialcharsex($arResult[$index]["TITLE"]);
	$prefix = ($index == 0) ? '' : '/ ';
	$isLast = ($index == $itemSize - 1);
	$link = $arResult[$index]["LINK"];

	// Каждый ListItem обязан иметь position и name (и item — для всех,
	// кроме последнего). Разделитель "/ " вынесен за пределы itemprop="name",
	// чтобы не попадал в название пункта.
	$strReturn .= '<span itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';

	// Последний пункт цепочки — всегда текущая страница, поэтому он
	// некликабельный (без <a href>), даже если для него формально передан
	// LINK. item для последнего пункта по правилам Google необязателен.
	if($isLast)
	{
		$strReturn .= '<span class="breadcrumb__link breadcrumb__link--current">'.$prefix.'<span itemprop="name">'.$title.'</span></span>';
	}
	elseif($link <> "")
	{
		$strReturn .= '<a class="breadcrumb__link" href="'.$link.'" itemprop="item">'.$prefix.'<span itemprop="name">'.$title.'</span></a>';
	}
	else
	{
		$strReturn .= '<span class="breadcrumb__link">'.$prefix.'<span itemprop="name">'.$title.'</span></span>';
	}

	$strReturn .= '<meta itemprop="position" content="'.($index + 1).'"></span>';
}

$strReturn .= '</div></div>';

return $strReturn;

?>      