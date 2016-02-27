<?
$bitrixPath = $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/digitalwand.admin_helper/admin/route.php";
$localPath = $_SERVER["DOCUMENT_ROOT"] . "/local/modules/digitalwand.admin_helper/admin/route.php";

if(!@include_once($bitrixPath) AND !@include_once($localPath) ){
    include $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/404.php';
}
?>