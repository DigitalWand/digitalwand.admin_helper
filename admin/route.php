<?php
use Bitrix\Main\Loader;
use DigitalWand\AdminHelper\Helper\AdminBaseHelper;
use DigitalWand\AdminHelper\Helper\AdminListHelper;
use DigitalWand\AdminHelper\Helper\AdminEditHelper;

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php");

function getRequestParams($param)
{
    if (!isset($_REQUEST[$param])) {
        return false;
    } else {
        return htmlspecialcharsbx($_REQUEST[$param]);
    }
}

/*Очищаем переменные сессии, чтобы сортировка восстанавливалась с учетом $table_id */
/** @global CMain $APPLICATION */
global $APPLICATION;
$uniq = md5($APPLICATION->GetCurPage());
if (isset($_SESSION["SESS_SORT_BY"][$uniq])) {
    unset($_SESSION["SESS_SORT_BY"][$uniq]);
}
if (isset($_SESSION["SESS_SORT_ORDER"][$uniq])) {
    unset($_SESSION["SESS_SORT_ORDER"][$uniq]);
}

$module = getRequestParams('module');
$view = getRequestParams('view');
if (!$module OR !$view OR !Loader::IncludeModule($module)) {
    include $_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/admin/404.php';
}

/**
 * Пытаемся определить имя класса админского интерфейса
 */
$interfaceNameParts = explode('.', $module);
$helperNameParts = explode('_', $view);
$helperType = array_pop($helperNameParts);
$entityName = array_pop($helperNameParts);
$interfaceNameParts = array_merge($interfaceNameParts, $helperNameParts);
$interfaceNameParts[]=$entityName;
$interfaceNameParts[]= $entityName.'AdminInterface';
foreach($interfaceNameParts as $i => $v)
{
    $interfaceNameParts[$i] = ucfirst($v);
}
$interfaceNameClass = implode('\\',$interfaceNameParts);

/**
 * Регистрируем класс интерфейса если он существует
 */
if(class_exists($interfaceNameClass))
{
    $interfaceNameClass::register();
}


list($helper, $interface) = AdminBaseHelper::getGlobalInterfaceSettings($module, $view);

if (!$helper OR !$interface) {
    include $_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/admin/404.php';
}

$isPopup = isset($_REQUEST['popup']) AND $_REQUEST['popup'] == 'Y';
$fields = isset($interface['FIELDS']) ? $interface['FIELDS'] :array();
$tabs = isset($interface['TABS']) ? $interface['TABS'] :array();
$helperType = false;


if (is_subclass_of($helper, 'DigitalWand\AdminHelper\Helper\AdminEditHelper')) {
    $helperType = 'edit';
    /** @var AdminEditHelper $adminHelper */
    $adminHelper = new $helper($fields, $tabs);

} else if (is_subclass_of($helper, 'DigitalWand\AdminHelper\Helper\AdminListHelper')) {
    $helperType = 'list';
    /** @var AdminListHelper $adminHelper */
    $adminHelper = new $helper($fields, $isPopup);
    $adminHelper->buildList(array($by => $order));

} else {
    include $_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/admin/404.php';
    exit();
}

if ($isPopup) {
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_popup_admin.php");
} else {
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_after.php");
}

if ($helperType == 'list') {
    $adminHelper->createFilterForm();
}
$adminHelper->show();

if ($isPopup) {
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_popup_admin.php");
} else {
    require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");
}

