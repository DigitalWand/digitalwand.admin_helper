<?
if (!@include_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/digitalwand.admin_helper/admin/route.php") {
    if (!@include_once $_SERVER["DOCUMENT_ROOT"] . "/local/modules/digitalwand.admin_helper/admin/route.php") {
        include $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/404.php';
    }
}
