<?php

use Bitrix\Main\Loader;
use DigitalWand\AdminHelper\Helper\AdminBaseHelper;
use DigitalWand\AdminHelper\Helper\AdminListHelper;
use DigitalWand\AdminHelper\Helper\AdminEditHelper;
use DigitalWand\AdminHelper\Helper\AdminInterface;

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

Loader::includeModule('digitalwand.admin_helper');

function getRequestParams($param)
{
	if (!isset($_REQUEST[$param])) {
		return false;
	}
	else {
		return htmlspecialcharsbx($_REQUEST[$param]);
	}
}

$module = getRequestParams('module');
$view = getRequestParams('view');
$entity = getRequestParams('entity');

if (!$module OR !$view OR !Loader::IncludeModule($module)) {
	include $_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/admin/404.php';
}

// Собираем имя класса админского интерфейса
$moduleNameParts = explode('.', $module);
$entityNameParts = explode('_', $entity);
$interfaceNameParts = array_merge($moduleNameParts, $entityNameParts);
$interfaceNameClass = null;
$viewParts = explode('_', $view);

$count = count($viewParts);
for ($i = 0; $i < $count; $i++) {
	$interfaceName = implode('', array_map('ucfirst', $viewParts));
	$parts = $interfaceNameParts;
	$parts[] = $interfaceName . 'AdminInterface';
	$class = array_map('ucfirst', $parts);
	$interfaceNameClass = implode('\\', $class);

	if (class_exists($interfaceNameClass)) {
		break;
	}
	else {
		$className = array_pop($parts);
		$parts[] = 'AdminInterface';
		$parts[] = $className;
		$class = array_map('ucfirst', $parts);
		$interfaceNameClass = implode('\\', $class);
		if (class_exists($interfaceNameClass)) {
			break;
		}
	}
	array_pop($viewParts);
}

/**
 * @var AdminInterface $interfaceNameClass
 */

if ($interfaceNameClass && class_exists($interfaceNameClass)) {
	$interfaceNameClass::register();
}

list($helper, $interface) = AdminBaseHelper::getGlobalInterfaceSettings($module, $view);

if (!$helper OR !$interface) {
	include $_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/admin/404.php';
}

$isPopup = isset($_REQUEST['popup']) AND $_REQUEST['popup'] == 'Y';
$fields = isset($interface['FIELDS']) ? $interface['FIELDS'] : array();
$tabs = isset($interface['TABS']) ? $interface['TABS'] : array();
$helperType = false;

if (is_subclass_of($helper, 'DigitalWand\AdminHelper\Helper\AdminEditHelper')) {
	$helperType = 'edit';
	/**
	 * @var AdminEditHelper $adminHelper
	 */
	$adminHelper = new $helper($fields, $tabs);
}
elseif (is_subclass_of($helper, 'DigitalWand\AdminHelper\Helper\AdminListHelper')) {
	$helperType = 'list';
	/**
	 * @var AdminListHelper $adminHelper
	 */
	$adminHelper = new $helper($fields, $isPopup);
	$adminHelper->buildList(array($by => $order));
}
elseif (is_subclass_of($helper, 'DigitalWand\AdminHelper\Helper\AdminBaseHelper')) {
	$adminHelper = new $helper($fields, $tabs);
}
else {
	include $_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/admin/404.php';
	exit();
}

if ($isPopup) {
	require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_popup_admin.php");
}
else {
	require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
}

if ($helperType == 'list') {
	$adminHelper->createFilterForm();
}

$adminHelper->show();

if ($isPopup) {
	require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_popup_admin.php");
}
else {
	require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
}
