<?php

namespace DigitalWand\AdminHelper\Widget;

class DateTimeWidget extends HelperWidget
{
    /**
     * Генерирует HTML для редактирования поля
     * @see AdminEditHelper::showField();
     * @return mixed
     */
    protected function genEditHTML()
    {
        ob_start();
        global $APPLICATION;
        $APPLICATION->IncludeComponent("bitrix:main.calendar", "",
			array(
				"SHOW_INPUT" => "Y",
				"FORM_NAME" => "",
				"INPUT_NAME" => $this->getEditInputName(),
				"INPUT_VALUE" => ConvertTimeStamp(strtotime($this->getValue()), "FULL"),
				"INPUT_VALUE_FINISH" => "",
				"SHOW_TIME" => "Y",
				"HIDE_TIMEBAR" => "N"
			)
		);
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    /**
     * Генерирует HTML для поля в списке
     * @see AdminListHelper::addRowCell();
     * @param CAdminListRow $row
     * @param array $data - данные текущей строки
     * @return mixed
     */
    public function genListHTML(&$row, $data)
    {
        if (isset($this->settings['EDIT_IN_LIST']) AND $this->settings['EDIT_IN_LIST']) {
            $row->AddCalendarField($this->getCode());
        } else {
            $row->AddViewField($this->getCode(), $this->getValue());
        }
    }

    /**
     * Генерирует HTML для поля фильтрации
     * @see AdminListHelper::createFilterForm();
     * @return mixed
     */
    public function genFilterHTML()
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
		$this->setValue(new \Bitrix\Main\Type\Datetime($this->getValue(), 'd.m.Y H:i:s'));

        if (!$this->checkRequired()) {
            $this->addError('REQUIRED_FIELD_ERROR');
        }
    }
}
