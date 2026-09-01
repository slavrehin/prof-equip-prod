<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$ClientID = 'navigation_'.$arResult['NavNum'];

$this->setFrameMode(true);

if(!$arResult["NavShowAlways"])
{
	if ($arResult["NavRecordCount"] == 0 || ($arResult["NavPageCount"] == 1 && $arResult["NavShowAll"] == false))
		return;
}

$strNavQueryString = ($arResult["NavQueryString"] != "" ? $arResult["NavQueryString"]."&amp;" : "");
$strNavQueryStringFull = ($arResult["NavQueryString"] != "" ? "?".$arResult["NavQueryString"] : "");

$spritePath = LAYOUT_DIR;
?>

<div class="catalog__pagination">
<?
// Прямая нумерация страниц
$arResult["nStartPage"] = 1;
$arResult["nEndPage"] = $arResult["NavPageCount"];

$bFirst = true;
$bPoints = false;
$pageNumber = $arResult["nStartPage"];

do
{
	// Показываем первые 2 страницы, последние 2 страницы и по 2 страницы вокруг текущей
	if ($pageNumber <= 2 || 
		$arResult["NavPageCount"] - $pageNumber <= 1 || 
		abs($pageNumber - $arResult["NavPageNomer"]) <= 2)
	{
		// Формируем ссылку
		if ($pageNumber == 1 && $arResult["bSavePage"] == false)
		{
			$pageUrl = $arResult["sUrlPath"] . $strNavQueryStringFull;
		}
		else
		{
			$pageUrl = $arResult["sUrlPath"] . '?' . $strNavQueryString . 'PAGEN_' . $arResult["NavNum"] . '=' . $pageNumber;
		}
		
		// Выводим ссылку на страницу
		if ($pageNumber == $arResult["NavPageNomer"]):
?>
			<span class="pagination__link active"><?= $pageNumber ?></span>
<?
		else:
?>
			<a class="pagination__link" href="<?= $pageUrl ?>"><?= $pageNumber ?></a>
<?
		endif;
		
		$bFirst = false;
		$bPoints = true;
	}
	else
	{
		// Выводим троеточие, если оно еще не выведено
		if ($bPoints)
		{
?>
			<div class="pagination__dots">...</div>
<?
			$bPoints = false;
		}
	}
	
	$pageNumber++;
} while($pageNumber <= $arResult["NavPageCount"]);

// Кнопка "Вперед" (следующая страница)
if ($arResult["NavPageNomer"] < $arResult["NavPageCount"]):
	$nextUrl = $arResult["sUrlPath"] . '?' . $strNavQueryString . 'PAGEN_' . $arResult["NavNum"] . '=' . ($arResult["NavPageNomer"] + 1);
?>
	<a class="pagination__arrow" href="<?= $nextUrl ?>" aria-label="Следующая страница">
		<svg>
			<use xlink:href="<?= $spritePath ?>/assets/img/sprite.svg#pagination-arrow"></use>
		</svg>
	</a>
<? endif; ?>

</div>