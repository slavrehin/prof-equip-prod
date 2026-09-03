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
	$prefix = ($index == 0) ? '' : '/ ';
	$isLast = ($index == $itemSize - 1);

	// Последний пункт цепочки — всегда текущая страница, поэтому он
	// некликабельный (без <a href>), даже если для него формально передан
	// LINK. Раньше здесь был <a href="..."> (пустой href на последнем шаге
	// или, для страниц умного фильтра, LINK самого раздела) — визуально не
	// отличался от остальных ссылок, хотя вести никуда не должен.
	if($isLast)
	{
		$strReturn .= '<span class="breadcrumb__link breadcrumb__link--current" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">'.$prefix.$title.'</span>';
	}
	elseif($arResult[$index]["LINK"] <> "")
	{
		$strReturn .= '<a class="breadcrumb__link" href="'.$arResult[$index]["LINK"].'" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">'.$prefix.$title.'</a>';
	}
	else
	{
		$strReturn .= '<span class="breadcrumb__link" itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">'.$prefix.$title.'</span>';
	}
}

$strReturn .= '</div></div>';

return $strReturn;

?>      