<?if(!check_bitrix_sessid()) return;?>
<?
global $APPLICATION;
UnRegisterModule("digitalwand.admin_helper");
echo CAdminMessage::ShowNote(GetMessage("ADMIN_HELPER_UNINSTALL_COMPLETE"));
?>

<form action="<?echo $APPLICATION->GetCurPage()?>">
	<input type="hidden" name="lang" value="<?echo LANG?>">
	<input type="submit" name="" value="<?echo GetMessage("ADMIN_HELPER_INSTALL_BACK")?>">
<form>