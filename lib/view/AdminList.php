<?php

namespace DigitalWand\AdminHelper\View;

use Bitrix\Main\Application;
use DigitalWand\AdminHelper\Helper\AdminListHelper;

class AdminList extends \CAdminList
{
    public $mode = false;

    /**
     * @var AdminListHelper $helper
     */
    protected $helper = null;

    function CAdminList($table_id, $sort = false)
    {
        parent::CAdminList($table_id, $sort = false);
    }

    /**
     * @var AdminListHelper $helper
     */
    public function setHelper(&$helper)
    {
        $this->helper = $helper;
    }

    function CheckListMode()
    {
        if ($this->helper->getCliMode() == 'excel_delayed') {

            $fname = $this->helper->getViewName();
            // http response splitting defence
            $fname = str_replace(array("\r", "\n"), "", $fname);

            header("Content-Type: application/vnd.ms-excel");
            header("Content-Disposition: filename=" . $fname . ".xls");
            $this->DisplayExcel();
            require($_SERVER["DOCUMENT_ROOT"] . BX_ROOT . "/modules/main/include/epilog_admin_after.php");

        } else {
            return parent::CheckListMode();
        }
    }

    function AddAdminContextMenu($aContext = array(), $aAdditionalContext = array(), $bShowExcel = true, $bShowSettings = true)
    {
        /** @global CMain $APPLICATION */
        global $APPLICATION;

        $aAdditionalMenu = array();

        if ($bShowSettings) {
            $link = DeleteParam(array("mode"));
            $link = $APPLICATION->GetCurPage() . "?mode=settings" . ($link <> "" ? "&" . $link : "");
            $aAdditionalMenu[] = array(
                "TEXT" => GetMessage("admin_lib_context_sett"),
                "TITLE" => GetMessage("admin_lib_context_sett_title"),
                "ONCLICK" => $this->table_id . ".ShowSettings('" . \CUtil::JSEscape($link) . "')",
                "GLOBAL_ICON" => "adm-menu-setting",
            );
        }

        if ($bShowExcel) {
            $link = DeleteParam(array("mode"));
            $link = $APPLICATION->GetCurPage() . "?mode=excel" . ($link <> "" ? "&" . $link : "");
            $aAdditionalMenu[] = array(
                "TEXT" => "Excel",
                "TITLE" => GetMessage("admin_lib_excel"),
                //"LINK"=>htmlspecialcharsbx($link),
                "ONCLICK" => "location.href='" . htmlspecialcharsbx($link) . "'",
                "GLOBAL_ICON" => "adm-menu-excel",
            );
        }

        $aAdditionalMenu = array_merge($aAdditionalMenu, $aAdditionalContext);

        if (count($aContext) > 0 OR count($aAdditionalMenu) > 0)
            $this->context = new \CAdminContextMenuList($aContext, $aAdditionalMenu);
    }

}