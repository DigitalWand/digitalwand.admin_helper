<?
global $MESS;

use Bitrix\Main\Localization\Loc;

$PathInstall = str_replace("\\", "/", __FILE__);
$PathInstall = substr($PathInstall, 0, strlen($PathInstall) - strlen("/index.php"));
Loc::loadMessages($PathInstall . "/install.php");
include($PathInstall . "/version.php");

if (class_exists("digitalwand_admin_helper")) return;

Class digitalwand_admin_helper extends CModule
{
    var $MODULE_ID = "digitalwand.admin_helper";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_GROUP_RIGHTS = "Y";
    var $MODULE_CSS;

    function digitalwand_admin_helper()
    {
        $this->MODULE_VERSION = ADMIN_HELPER_VERSION;
        $this->MODULE_VERSION_DATE = ADMIN_HELPER_VERSION_DATE;
        $this->MODULE_NAME = GetMessage("ADMIN_HELPER_INSTALL_NAME");
        $this->MODULE_DESCRIPTION = GetMessage("ADMIN_HELPER_INSTALL_DESCRIPTION");

        $this->PARTNER_NAME = "DigitalWand";
        $this->PARTNER_URI = "";
    }

    function DoInstall()
    {
        global $APPLICATION;
        RegisterModule($this->MODULE_ID);
        $this->InstallFiles();
        $APPLICATION->IncludeAdminFile(
            GetMessage("ADMIN_HELPER_INSTALL_TITLE"),
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/install/step.php"
        );
    }

    function DoUninstall()
    {
        global $APPLICATION;
        UnRegisterModule($this->MODULE_ID);
        $APPLICATION->IncludeAdminFile(
            GetMessage("ADMIN_HELPER_INSTALL_TITLE"),
            $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/" . $this->MODULE_ID . "/install/unstep.php"
        );
    }

    function InstallFiles()
    {
        CopyDirFiles($_SERVER['DOCUMENT_ROOT'] . "/local/modules/" . $this->MODULE_ID . "/install/admin",
            $_SERVER['DOCUMENT_ROOT'] . "/bitrix/admin");

        return true;
    }
}

?>