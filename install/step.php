<? use Bitrix\Main\Localization\Loc;

if (!check_bitrix_sessid()) return; ?>

<?
global $APPLICATION;
@CopyDirFiles(__DIR__ . '/admin', $DOCUMENT_ROOT . "/bitrix/admin", true);
echo CAdminMessage::ShowNote(Loc::getMessage("ADMIN_HELPER_INSTALL_COMPLETE_OK"));
?>

<form action="<? echo $APPLICATION->GetCurPage() ?>">
    <input type="hidden" name="lang" value="<? echo LANG ?>">
    <input type="submit" name="" value="<? echo Loc::getMessage("ADMIN_HELPER_INSTALL_BACK") ?>">
</form>