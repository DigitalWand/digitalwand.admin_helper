<?php

namespace DigitalWand\AdminHelper;

use DigitalWand\AdminHelper\Helper\AdminBaseHelper;

/**
 * Оригинальный класс сохраняет в сессии выбранную сортировку по страницам админки.
 *
 * Поскольку у admin_helper всего одна страница в админке на все модели и представления,
 * нам необходимо сделать ключ сессии разным для разных классов-хелперов.
 *
 * Так мы сможем иметь свой порядок сортировки у каждого из них.
 *
 * @link:https://github.com/DigitalWand/digitalwand.admin_helper/issues/88
 */
class Sorting extends \CAdminSorting
{
	/** @noinspection PhpMissingParentConstructorInspection */
	/**
	 * Изменения относительно базового конструктора отмечены комментариями
	 *
	 * @param string $table_id ID в \CAdminList
	 * @param string|bool $by_initial Поле сортировки по умолчанию
	 * @param string|bool $order_initial Порядок сортирвоки по умолчанию
	 * @param string $by_name Параметр, содержащий поле сортировки
	 * @param string $ord_name Параметр, содержащий порядок сортировки
	 * @param AdminBaseHelper $adminHelper
	 */
	public function __construct(
		$table_id,
		$by_initial = false,
		$order_initial = false,
		$by_name = "by",
		$ord_name = "order",
		AdminBaseHelper $adminHelper = null // добавлено
	) {
		/** @global \CMain $APPLICATION */
		global $APPLICATION;

		$this->by_name = $by_name;
		$this->ord_name = $ord_name;
		$this->table_id = $table_id;
		$this->by_initial = $by_initial;
		$this->order_initial = $order_initial;

		$uniq = md5(($adminHelper ? get_class($adminHelper) : '') . '_' . $APPLICATION->GetCurPage()); // изменено

		$aOptSort = array();
		if(isset($GLOBALS[$this->by_name]))
		{
			$_SESSION["SESS_SORT_BY"][$uniq] = $GLOBALS[$this->by_name];
			$_SESSION["SESS_SORT_BY"][$uniq] = $GLOBALS[$this->by_name];
		}
		elseif(isset($_SESSION["SESS_SORT_BY"][$uniq]))
		{
			$GLOBALS[$this->by_name] = $_SESSION["SESS_SORT_BY"][$uniq];
		}
		else
		{
			$aOptSort = \CUserOptions::GetOption("list", $this->table_id, array("by"=>$by_initial, "order"=>$order_initial));
			if(!empty($aOptSort["by"]))
				$GLOBALS[$this->by_name] = $aOptSort["by"];
			elseif($by_initial !== false)
				$GLOBALS[$this->by_name] = $by_initial;
		}

		if(isset($GLOBALS[$this->ord_name]))
		{
			$_SESSION["SESS_SORT_ORDER"][$uniq] = $GLOBALS[$this->ord_name];
		}
		elseif(isset($_SESSION["SESS_SORT_ORDER"][$uniq]))
		{
			$GLOBALS[$this->ord_name] = $_SESSION["SESS_SORT_ORDER"][$uniq];
		}
		else
		{
			if(empty($aOptSort["order"]))
				$aOptSort = \CUserOptions::GetOption("list", $this->table_id, array("order"=>$order_initial));
			if(!empty($aOptSort["order"]))
				$GLOBALS[$this->ord_name] = $aOptSort["order"];
			elseif($order_initial !== false)
				$GLOBALS[$this->ord_name] = $order_initial;
		}

		$this->field = $GLOBALS[$this->by_name];
		$this->order = $GLOBALS[$this->ord_name];
	}
}