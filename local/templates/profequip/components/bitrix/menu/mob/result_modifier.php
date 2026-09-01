<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$start = 0;
$arResult['MENU_TREE'] = getChildrenMenu($arResult, $start, 0);