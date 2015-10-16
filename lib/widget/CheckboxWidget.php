<?php
namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Class CheckboxWidget
 * Виджет "галочка"
 *
 */
class CheckboxWidget extends HelperWidget
{
    /**
     * Генерирует HTML для редактирования поля
     *
     * @return mixed
     */
    protected function genEditHTML()
    {
        $checked = $this->getValue() == 'Y' ? 'checked' : '';

        return '<input type="checkbox" name="'.$this->getEditInputName().'" value="Y" '.$checked.' />';
    }

    /**
     * Генерирует HTML для поля в списке
     *
     * @see AdminListHelper::addRowCell();
     *
     * @param \CAdminListRow $row
     * @param array          $data - данные текущей строки
     *
     * @return mixed
     */
    public function genListHTML(&$row, $data)
    {
        if ($this->getSettings('EDIT_IN_LIST') AND !$this->getSettings('READONLY')) {
            $checked = intval($this->getValue() == 'Y') ? 'checked' : '';
            $js = 'var input = document.getElementsByName(\''.$this->getEditableListInputName().'\')[0];
                   input.value = this.checked ? \'Y\' : \'N\';';
            $editHtml
                = '<input type="checkbox"
                                value="'.$this->getValue().'" '.$checked.'
                                onchange="'.$js.'"/>
                         <input type="hidden"
                                value="'.$this->getValue().'"
                                name="'.$this->getEditableListInputName().'" />';
            $row->AddEditField($this->getCode(), $editHtml);
        }

        $value = intval($this->getValue() == 'Y') ? Loc::getMessage('CHECKBOX_YES') : Loc::getMessage('CHECKBOX_NO');
        $row->AddViewField($this->getCode(), $value);

    }

    /**
     * Генерирует HTML для поля фильтрации
     *
     * @see AdminListHelper::createFilterForm();
     * @return mixed
     */
    public function genFilterHTML()
    {
        print '<tr>';
        print '<td>'.$this->getSettings('TITLE').'</td>';
        print '<td> <select  name="'.$this->getFilterInputName().'">';

        print '<option value="Y">'.Loc::getMessage('CHECKBOX_YES').'</option>';
        print '<option value="N">'.Loc::getMessage('CHECKBOX_NO').'</option>';

        print '</select></td>';
        print '</tr>';
    }

    public function getValue()
    {
        $rawValue = parent::getValue();
        if (!is_string($rawValue)) {
            return $this->toString($rawValue);
        }

        return $rawValue;
    }

    public static function toInt($stringValue)
    {
        return $stringValue == 'Y' ? 1 : 0;
    }

    public static function toString($boolValue)
    {
        return $boolValue ? 'Y' : 'N';
    }

    public function processEditAction()
    {
        parent::processEditAction();
        if(!isset($this->data[$this->getCode()])){
            $this->data[$this->getCode()] = 'N';
        }
    }
}