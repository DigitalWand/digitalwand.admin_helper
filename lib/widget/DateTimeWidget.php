<?php

namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Type\Date;
use Bitrix\Main\Type\DateTime;

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
                "INPUT_VALUE" => $this->getValue(),
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
     * @param \CAdminListRow $row
     * @param array $data - данные текущей строки
     * @return mixed
     */
    public function genListHTML(&$row, $data)
    {
        if ($this->getSettings('EDIT_IN_LIST') AND !$this->getSettings('READONLY')) {
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

    protected function setValue($value)
    {
        if (is_string($value)) {
            $value = new DateTime($value);
        }
        return parent::setValue($value);
    }


}
