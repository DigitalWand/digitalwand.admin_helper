<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

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
    var $PARTNER_NAME = 'DigitalWand & Notamedia';
    var $PARTNER_URI = '';

    function digitalwand_admin_helper()
    {
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = ADMIN_HELPER_VERSION;
        $this->MODULE_VERSION_DATE = ADMIN_HELPER_VERSION_DATE;
        $this->MODULE_NAME = Loc::getMessage('ADMIN_HELPER_INSTALL_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('ADMIN_HELPER_INSTALL_DESCRIPTION');
    }

    function DoInstall()
    {
        global $APPLICATION;

        $this->InstallDB();
        $this->InstallFiles();

        $APPLICATION->IncludeAdminFile(Loc::getMessage('ADMIN_HELPER_INSTALL_TITLE'), __DIR__ . '/step.php');
    }

    function DoUninstall()
    {
        global $APPLICATION;

        $this->UnInstallDB();

        $APPLICATION->IncludeAdminFile(Loc::getMessage('ADMIN_HELPER_INSTALL_TITLE'), __DIR__ . '/unstep.php');
    }

    function InstallDB()
    {
        ModuleManager::registerModule($this->MODULE_ID);

        $eventManager = EventManager::getInstance();
        $eventManager->registerEventHandler(
            'main',
            'OnPageStart',
            $this->MODULE_ID,
            '\DigitalWand\AdminHelper\EventHandlers',
            'onPageStart'
        );

        return true;
    }

    function UnInstallDB()
    {
        ModuleManager::unRegisterModule($this->MODULE_ID);

        $eventManager = EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            'main',
            'OnPageStart',
            $this->MODULE_ID,
            '\DigitalWand\AdminHelper\EventHandlers',
            'onPageStart'
        );
    }

    function InstallFiles()
    {
        CopyDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');

        return true;
    }
}