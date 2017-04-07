<?php

namespace DigitalWand\AdminHelper\Widget;

class DateTimeWidget extends HelperWidget
{
	static protected $defaults = array(
		'FILTER' => 'BETWEEN',
	);
	
	/**
	 * Генерирует HTML для редактирования поля
	 * @see AdminEditHelper::showField();
	 * @return mixed
	 */
	protected function getEditHtml()
	{
		return \CAdminCalendar::CalendarDate($this->getEditInputName(), ConvertTimeStamp(strtotime($this->getValue()), "FULL"), 10, true);
	}

	/**
	 * Генерирует HTML для поля в списке
	 * @see AdminListHelper::addRowCell();
	 * @param CAdminListRow $row
	 * @param array $data - данные текущей строки
	 * @return mixed
	 */
	public function generateRow(&$row, $data)
	{
		if (isset($this->settings['EDIT_IN_LIST']) AND $this->settings['EDIT_IN_LIST'])
		{
			$row->AddCalendarField($this->getCode());
		}
		else
		{
			$arDate = ParseDateTime($this->getValue());

			if ($arDate['YYYY'] < 10)
			{
				$stDate = '-';
			}
			else
			{
				$stDate = ConvertDateTime($this->getValue(), "DD.MM.YYYY HH:MI:SS", "ru");
			}

			$row->AddViewField($this->getCode(), $stDate);
		}
	}

	/**
	 * Генерирует HTML для поля фильтрации
	 * @see AdminListHelper::createFilterForm();
	 * @return mixed
	 */
	public function showFilterHtml()
	{
		list($inputNameFrom, $inputNameTo) = $this->getFilterInputName();
		print '<tr>';
		print '<td>' . $this->settings['TITLE'] . '</td>';
		print '<td width="0%" nowrap>' . CalendarPeriod($inputNameFrom, $$inputNameFrom, $inputNameTo, $$inputNameTo, "find_form") . '</td>';
	}

	/**
	 * Сконвертируем дату в формат Mysql
	 * @return boolean
	 */
	public function processEditAction()
	{
		try
		{
			$this->setValue(new \Bitrix\Main\Type\Datetime($this->getValue()));
		} catch (\Exception $e)
		{
		}
		if (!$this->checkRequired())
		{
			$this->addError('REQUIRED_FIELD_ERROR');
		}
	}
}
