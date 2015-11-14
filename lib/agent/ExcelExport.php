<?php

namespace DigitalWand\AdminHelper\Agent;

use Bitrix\Main\Loader;
use DigitalWand\AdminHelper\Helper\AdminBaseHelper;
use DigitalWand\AdminHelper\Helper\AdminListHelper;

class ExcelExport
{
    protected $lAdmin;
    protected $headers;
    protected $fileName;

    const EXPORT_DONE = 'DW_AH_LISTVIEW_EXPORT_DONE';


    public static function run($module, $view, $uid, $siteUrl, $interfaces)
    {
        $interfaces = unserialize($interfaces);
        (new self())->export($module, $view, $uid, $siteUrl, $interfaces);

        return '';
    }

    /**
     * Отправка публикаций.
     */
    protected function export($module, $view, $uid, $siteUrl, $interfaces)
    {
        global $USER;
        $USER->Authorize($uid);

        foreach ($interfaces as $interface) {
            \DigitalWand\AdminHelper\Loader::forceLoadInterface($interface);
        }

        require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_admin_before.php";
        require_once $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/prolog.php";

        AdminListHelper::sessionSortFix();
        list($helper, $interface) = AdminBaseHelper::getGlobalInterfaceSettings($module, $view);

        if (!$helper OR !$interface) {
            return;
        }

        $fields = isset($interface['FIELDS']) ? $interface['FIELDS'] : array();
        $tabs = isset($interface['TABS']) ? $interface['TABS'] : array();

        ob_start();
        /** @var AdminListHelper $adminHelper */
        $adminHelper = new $helper($fields, false, 'excel_delayed');
        $fakeCurPage = $adminHelper::getListPageURL();

        //TODO: загадка, почему это не работает при штатном вызове внутри битиркса
        $aOptSort = \CUserOptions::GetOption("list", $adminHelper->getListTableID(), array("by" => 'ID', "order" => 'asc'));
        print_r($aOptSort);

        if (empty($aOptSort['by'])) {
            $aOptSort['by'] = 'ID';
        }
        if (empty($aOptSort['order'])) {
            $aOptSort['order'] = 'asc';
        }
        $adminHelper->getData(array($aOptSort['by'] => $aOptSort['order']));
        $fileContent = ob_get_clean();


        $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/export/';
        if (!file_exists($targetDir)) {
            mkdir($targetDir, BX_DIR_PERMISSIONS, true);
        }

        $date = date('Y.m.d-H.i.s');
        $fileName = $adminHelper::getViewName() . '_' . $date . '.xls';
        if (file_put_contents($targetDir . $fileName, $fileContent)) {
            $this->sendEmail($USER->GetEmail(), 'upload/export/' . $fileName, $siteUrl);
            return true;
        }

        return false;
    }

    public function sendEmail($email, $fileName, $siteUrl)
    {
        $arEventFields = array(
            "EMAIL_TO" => $email,
            'LINK' => $siteUrl . '/' . $fileName,
            'DEFAULT_EMAIL_FROM' => 'noreply@shkolamam.ru',
            'SITE_NAME' => 'Школа мам',
        );

        \CEvent::Send(static::EXPORT_DONE, SITE_ID, $arEventFields);
    }

    public static function deleteOld()
    {
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/upload/export/';

        if (file_exists($targetDir)) {
            foreach (new \DirectoryIterator($targetDir) as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }
                if (time() - $fileInfo->getCTime() >= 7 * 24 * 60 * 60) {
                    unlink($fileInfo->getRealPath());
                }
            }
        }
    }
}