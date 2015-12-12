<?php

namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Виджет "галочка"
 */
class CheckboxWidget extends HelperWidget
{
    /**
     * Строковый тип чекбокса (Y/N)
     */
    const TYPE_STRING = 'string';
    /**
     * Целочисленный тип чекбокса (1/0)
     */
    const TYPE_INT = 'integer';
    /**
     * Булевый тип чекбокса
     */
    const TYPE_BOOLEAN = 'boolean';
    /**
     * Значение положительного варианта для строкового чекбокса
     */
    const TYPE_STRING_YES = 'Y';
    /**
     * Значение отрицательного варианта для строкового чекбокса
     */
    const TYPE_STRING_NO = 'N';
    /**
     * Значение положительного варианта для целочисленного чекбокса
     */
    const TYPE_INT_YES = 1;
    /**
     * Значение отрицательного варианта для целочисленного чекбокса
     */
    const TYPE_INT_NO = 0;

    protected static $defaults = array(
        'EDIT_IN_LIST' => true
    );

    /**
     * @inheritdoc
     */
    protected function getEditHtml()
    {
        $html = '';

        $modeType = $this->getCheckboxType();

        switch ($modeType) {
            case static::TYPE_STRING: {
                $checked = $this->getValue() == self::TYPE_STRING_YES ? 'checked' : '';

                $html = '<input type="hidden" name="' . $this->getEditInputName() . '" value="' . self::TYPE_STRING_NO . '" />';
                $html .= '<input type="checkbox" name="' . $this->getEditInputName() . '" value="' . self::TYPE_STRING_YES . '" ' . $checked . ' />';
                break;
            }
            case static::TYPE_INT:
            case static::TYPE_BOOLEAN: {
                $checked = $this->getValue() == self::TYPE_INT_YES ? 'checked' : '';

                $html = '<input type="hidden" name="' . $this->getEditInputName() . '" value="' . self::TYPE_INT_NO . '" />';
                $html .= '<input type="checkbox" name="' . $this->getEditInputName() . '" value="' . self::TYPE_INT_YES . '" ' . $checked . ' />';
                break;
            }
        }

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function generateRow(&$row, $data)
    {
        $modeType = $this->getCheckboxType();

        $globalYes = '';
        $globalNo = '';

        switch ($modeType) {
            case self::TYPE_STRING: {
                $globalYes = self::TYPE_STRING_YES;
                $globalNo = self::TYPE_STRING_NO;
                break;
            }
            case self::TYPE_INT:
            case self::TYPE_BOOLEAN: {
                $globalYes = self::TYPE_INT_YES;
                $globalNo = self::TYPE_INT_NO;
                break;
            }
        }

        if ($this->getSettings('EDIT_IN_LIST') AND !$this->getSettings('READONLY')) {
            $checked = intval($this->getValue() == $globalYes) ? 'checked' : '';
            $js = 'var input = document.getElementsByName(\'' . $this->getEditableListInputName() . '\')[0];
                   input.value = this.checked ? \'' . $globalYes . '\' : \'' . $globalNo . '\';';
            $editHtml = '<input type="checkbox"
                                value="' . static::prepareToTagAttr($this->getValue()) . '" ' . $checked . '
                                onchange="' . $js . '"/>
                         <input type="hidden"
                                value="' . static::prepareToTagAttr($this->getValue()) . '"
                                name="' . $this->getEditableListInputName() . '" />';
            $row->AddEditField($this->getCode(), $editHtml);
        }

        if (intval($this->getValue() == $globalYes)) {
            $value = Loc::getMessage('DIGITALWAND_AH_CHECKBOX_YES');
        } else {
            $value = Loc::getMessage('DIGITALWAND_AH_CHECKBOX_NO');
        }

        $row->AddViewField($this->getCode(), $value);
    }

    /**
     * @inheritdoc
     */
    public function showFilterHtml()
    {
        $filterHtml = '<tr>';
        $filterHtml .= '<td>' . $this->getSettings('TITLE') . '</td>';
        $filterHtml .= '<td> <select  name="' . $this->getFilterInputName() . '">';
        $filterHtml .= '<option value=""></option>';

        $modeType = $this->getCheckboxType();

        $langYes = Loc::getMessage('DIGITALWAND_AH_CHECKBOX_YES');
        $langNo = Loc::getMessage('DIGITALWAND_AH_CHECKBOX_NO');

        switch ($modeType) {
            case self::TYPE_STRING: {
                $filterHtml .= '<option value="' . self::TYPE_STRING_YES . '">' . $langYes . '</option>';
                $filterHtml .= '<option value="' . self::TYPE_STRING_NO . '">' . $langNo . '</option>';
                break;
            }
            case self::TYPE_INT:
            case self::TYPE_BOOLEAN: {
                $filterHtml .= '<option value="' . self::TYPE_INT_YES . '">' . $langYes . '</option>';
                $filterHtml .= '<option value="' . self::TYPE_INT_NO . '">' . $langNo . '</option>';
                break;
            }
        }

        $filterHtml .= '</select></td>';
        $filterHtml .= '</tr>';

        print $filterHtml;
    }

    /**
     * @inheritdoc
     */
    public function getValueReadonly()
    {
        $code = $this->getCode();
        $value = isset($this->data[$code]) ? $this->data[$code] : null;
        $modeType = $this->getCheckboxType();

        switch ($modeType) {
            case static::TYPE_STRING: {
                $value = $value == 'Y' ? Loc::getMessage('DIGITALWAND_AH_CHECKBOX_YES') : Loc::getMessage('DIGITALWAND_AH_CHECKBOX_NO');
                break;
            }
            case static::TYPE_INT:
            case static::TYPE_BOOLEAN: {
                $value = $value ? Loc::getMessage('DIGITALWAND_AH_CHECKBOX_YES') : Loc::getMessage('DIGITALWAND_AH_CHECKBOX_NO');
                break;
            }
        }

        return static::prepareToOutput($value);
    }

    /**
     * @inheritdoc
     */
    public function processEditAction()
    {
        parent::processEditAction();

        if ($this->getCheckboxType() === static::TYPE_BOOLEAN) {
            $this->data[$this->getCode()] = (bool) $this->data[$this->getCode()];
        }
    }

    /**
     * Получить тип чекбокса по типу поля.
     *
     * @return mixed
     */
    public function getCheckboxType()
    {
        $fieldType = '';
        $entity = $this->getEntityName();
        $entityMap = $entity::getMap();
        $columnName = $this->getCode();

        if (!isset($entityMap[$columnName])) {
            foreach ($entityMap as $field) {
                if ($field->getColumnName() === $columnName) {
                    $fieldType = $field->getDataType();
                    break;
                }
            }
        } else {
            $fieldType = $entityMap[$columnName]['data_type'];
        }

        return $fieldType;
    }
}