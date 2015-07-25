<?if(!check_bitrix_sessid()) return;?>

<?
global $APPLICATION;
RegisterModule("digitalwand.admin_helper");
@CopyDirFiles($DOCUMENT_ROOT."/bitrix/modules/digitalwand.admin_helper/install/admin", $DOCUMENT_ROOT."/bitrix/admin", true);
echo CAdminMessage::ShowNote(GetMessage("ADMIN_HELPER_INSTALL_COMPLETE_OK"));
?>

<form action="<?echo $APPLICATION->GetCurPage()?>">
	<input type="hidden" name="lang" value="<?echo LANG?>">
	<input type="submit" name="" value="<?echo GetMessage("ADMIN_HELPER_INSTALL_BACK")?>">
<form>