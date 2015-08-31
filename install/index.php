<?php

global $MESS;

use Bitrix\Main\Localization\Loc;

$PathInstall = str_replace("\\", "/", __FILE__);
$PathInstall = substr($PathInstall, 0, strlen($PathInstall) - strlen('/index.php'));
Loc::loadMessages($PathInstall . '/install.php');
include($PathInstall . '/version.php');

if (class_exists('digitalwand_admin_helper')) return;

class digitalwand_admin_helper extends CModule
{
    var $MODULE_ID = 'digitalwand.admin_helper';
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_GROUP_RIGHTS = 'Y';
    var $MODULE_CSS;
    var $PARTNER_NAME = 'DigitalWand';
    var $PARTNER_URI = '';

    function digitalwand_admin_helper()
    {
        $this->MODULE_VERSION = ADMIN_HELPER_VERSION;
        $this->MODULE_VERSION_DATE = ADMIN_HELPER_VERSION_DATE;
        $this->MODULE_NAME = Loc::getMessage('ADMIN_HELPER_INSTALL_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('ADMIN_HELPER_INSTALL_DESCRIPTION');
    }

    function DoInstall()
    {
        global $APPLICATION;
        RegisterModule($this->MODULE_ID);
        $this->InstallFiles();
        $APPLICATION->IncludeAdminFile(Loc::getMessage('ADMIN_HELPER_INSTALL_TITLE'), __DIR__ . '/step.php');
    }

    function DoUninstall()
    {
        global $APPLICATION;
        UnRegisterModule($this->MODULE_ID);
        $APPLICATION->IncludeAdminFile(Loc::getMessage('ADMIN_HELPER_INSTALL_TITLE'), __DIR__ . '/unstep.php');
    }

    function InstallFiles()
    {
        CopyDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');

        return true;
    }
}