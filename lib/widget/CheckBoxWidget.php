<?php
namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Class CheckBoxWidget
 * Виджет "галочка"
 *
 */
class CheckBoxWidget extends HelperWidget
{
    /**
     * Типы чекбоксов
     * TYPE_STRING - строковый (Y/N)
     * TYPE_INT - целочисленный (1/0)
     */
    const TYPE_STRING = 'string';
    const TYPE_INT = 'integer';
    const TYPE_BOOLEAN = 'boolean';

    /**
     * Значения возможных вариантов для строкового чекбокса
     */
    const TYPE_STRING_YES = 'Y';
    const TYPE_STRING_NO = 'N';

    /**
     * Значения возможных вариантов для целочисленного чекбокса
     */
    const TYPE_INT_YES = 1;
    const TYPE_INT_NO = 0;

    /**
     * Генерирует HTML для редактирования поля
     *
     * @return mixed
     */
    protected function genEditHTML()
    {
        // Выбран ли чекбокс
        $html = '';
        // Получаем тип чекбокса
        $modeType = $this->getCheckboxType();
        // Для возможного расширения поведения чекбокса
        switch($modeType)
        {
            case self::TYPE_STRING:
            {
                // Сравниваем со строковым значением
                $checked = $this->getValue() == self::TYPE_STRING_YES ? 'checked' : '';
                // Получаем результирующее html представление
                $html = '<input type="checkbox" name="'.$this->getEditInputName().'" value="'.self::TYPE_STRING_YES.'" '.$checked.' />';

                break;
            }
            case self::TYPE_INT: case self::TYPE_BOOLEAN:
        {
            // Сравниваем со строковым значением
            $checked = $this->getValue() == self::TYPE_INT_YES ? 'checked' : '';
            // Получаем результирующее html представление
            $html = '<input type="checkbox" name="'.$this->getEditInputName().'" value="'.self::TYPE_INT_YES.'" '.$checked.' />';

            break;
        }
        }
        // Возвращаем html представление
        return $html;
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
        // Получаем тип чекбокса
        $modeType = $this->getCheckboxType();
        // Глобальные значения да/нет
        $globalYes = '';
        $globalNo = '';
        // Для возможного расширения поведения чекбокса
        switch($modeType)
        {
            case self::TYPE_STRING:
            {
                $globalYes = self::TYPE_STRING_YES;
                $globalNo = self::TYPE_STRING_NO;

                break;
            }
            case self::TYPE_INT: case self::TYPE_BOOLEAN:
        {
            $globalYes = self::TYPE_INT_YES;
            $globalNo = self::TYPE_INT_NO;

            break;
        }
        }
        // Если можно редактировать
        if ($this->getSettings('EDIT_IN_LIST') AND !$this->getSettings('READONLY')) {
            $checked = intval($this->getValue() == $globalYes) ? 'checked' : '';
            $js = 'var input = document.getElementsByName(\''.$this->getEditableListInputName().'\')[0];
                   input.value = this.checked ? \'' . $globalYes . '\' : \'' . $globalNo . '\';';
            $editHtml
                = '<input type="checkbox"
                                value="'.$this->getValue().'" '.$checked.'
                                onchange="'.$js.'"/>
                         <input type="hidden"
                                value="'.$this->getValue().'"
                                name="'.$this->getEditableListInputName().'" />';
            $row->AddEditField($this->getCode(), $editHtml);
        }

        $value = intval($this->getValue() == $globalYes) ? Loc::getMessage('CHECKBOX_YES') : Loc::getMessage('CHECKBOX_NO');
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
        // Формируем каркас
        $filterHtml = '<tr>';
        $filterHtml .= '<td>'.$this->getSettings('TITLE').'</td>';
        $filterHtml .= '<td> <select  name="'.$this->getFilterInputName().'">';
        $filterHtml .= '<option value=""></option>';
        // Получаем тип чекбокса
        $modeType = $this->getCheckboxType();
        // Для возможного расширения поведения чекбокса
        switch($modeType)
        {
            case self::TYPE_STRING:
            {
                $filterHtml .= '<option value="'.self::TYPE_STRING_YES.'">'.Loc::getMessage('CHECKBOX_YES').'</option>';
                $filterHtml .= '<option value="'.self::TYPE_STRING_NO.'">'.Loc::getMessage('CHECKBOX_NO').'</option>';

                break;
            }
            case self::TYPE_INT: case self::TYPE_BOOLEAN:
        {
            $filterHtml .= '<option value="'.self::TYPE_INT_YES.'">'.Loc::getMessage('CHECKBOX_YES').'</option>';
            $filterHtml .= '<option value="'.self::TYPE_INT_NO.'">'.Loc::getMessage('CHECKBOX_NO').'</option>';

            break;
        }
        }
        // Завершаем каркас
        $filterHtml .= '</select></td>';
        $filterHtml .= '</tr>';
        // Выводим фильтр
        print $filterHtml;
    }


    public function getValueReadonly()
    {
        $code = $this->getCode();
        $value = isset($this->data[$code]) ? $this->data[$code] : null;

        $modeType = $this->getCheckboxType();

        // Для возможного расширения поведения чекбокса
        switch($modeType)
        {
            case self::TYPE_STRING:
            {
                $value = $value == 'Y' ? Loc::getMessage('CHECKBOX_YES') : Loc::getMessage('CHECKBOX_NO');

                break;
            }
            case self::TYPE_INT: case self::TYPE_BOOLEAN:
        {
            $value = $value ? Loc::getMessage('CHECKBOX_YES') : Loc::getMessage('CHECKBOX_NO');

            break;
        }
        }

        return $value;
    }


    /**
     * Получить тип чекбокса по типу поля
     * @return mixed
     */
    public function getCheckboxType()
    {
        $fieldType = '';
        $entity = $this->getEntityName();
        $entityMap = $entity::getMap();
        $columnName = $this->getCode();

        if (!isset($entityMap[$columnName]))
        {
            foreach ($entityMap as $field)
            {
                if ($field->getColumnName() === $columnName)
                {
                    $fieldType = $field->getDataType();
                    break;
                }
            }
        }
        else
        {
            $fieldType = $entityMap[$columnName]['data_type'];
        }

        return $fieldType;
    }
}