<?php

use Bitrix\Main\Localization\Loc;

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

        $eventManager = \Bitrix\Main\EventManager::getInstance();

        RegisterModule($this->MODULE_ID);
        $this->InstallFiles();

        $eventManager->registerEventHandler(
            'main',
            'OnPageStart',
            $this->MODULE_ID,
            '\DigitalWand\AdminHelper\EventHandlers',
            'onPageStart'
        );

        $APPLICATION->IncludeAdminFile(Loc::getMessage('ADMIN_HELPER_INSTALL_TITLE'), __DIR__ . '/step.php');
    }

    function DoUninstall()
    {
        global $APPLICATION;

        $eventManager = \Bitrix\Main\EventManager::getInstance();

        UnRegisterModule($this->MODULE_ID);

        $eventManager->unRegisterEventHandler(
            'main',
            'OnPageStart',
            $this->MODULE_ID,
            '\DigitalWand\AdminHelper\EventHandlers',
            'onPageStart'
        );

        $APPLICATION->IncludeAdminFile(Loc::getMessage('ADMIN_HELPER_INSTALL_TITLE'), __DIR__ . '/unstep.php');
    }

    function InstallFiles()
    {
        CopyDirFiles(__DIR__ . '/admin', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');

        return true;
    }
}