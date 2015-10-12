<?php

namespace DigitalWand\AdminHelper\Widget;

/**
 * Class ComboBoxWidget Выпадающий список
 * Доступные опции:
 * <ul>
 * <li> STYLE - inline-стили</li>
 * <li> VARIANTS - массив с вариантами занчений или функция для их получения</li>
 * <li> DEFAULT_VARIANT - ID варианта по-умолчанию</li>
 * </ul>
 */
class ComboBoxWidget extends HelperWidget
{
    static protected $defaults = array(
        'EDIT_IN_LIST' => true
    );

    /**
     * Генерирует HTML для редактирования поля
     * @see AdminEditHelper::showField();
     * @param bool $forFilter
     * @return mixed
     */
    protected function genEditHTML($forFilter = false)
    {
        $style = $this->getSettings('STYLE');

        $name = $forFilter ? $this->getFilterInputName() : $this->getEditInputName();
        $result = "<select name='" . $name . "' style='" . $style . "'>";
        $variants = $this->getVariants();
        $default = $this->getValue();
        if (is_null($default)) {
            $default = $this->getSettings('DEFAULT_VARIANT');
        }

        foreach ($variants as $id => $name) {
            $result .= "<option value='" . $id . "' " . ($id == $default ? "selected" : "") . ">" . $name . "</option>";
        }

        $result .= "</select>";

        return $result;
    }

    protected function getValueReadonly()
    {
        $variants = $this->getVariants();
        $value = $variants[$this->getValue()];
        return $value;
    }

    /**
     * Возвращает массив в формате
     * <code>
     * array(
     *      '123' => array('ID' => 123, 'TITLE' => 'ololo'),
     *      '456' => array('ID' => 456, 'TITLE' => 'blablabla'),
     *      '789' => array('ID' => 789, 'TITLE' => 'pish-pish'),
     * )
     * </code>
     * Результат будет выводиться в комбобоксе
     * @return array
     */
    protected function getVariants()
    {
        $variants = $this->getSettings('VARIANTS');
        if (is_callable($variants)) {
            $var = call_user_func($variants);
            if (is_array($var)) {
                return $var;
            }
        } else if (is_array($variants) AND !empty($variants)) {
            return $variants;
        }

        return array();
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
            $variants = $this->getVariants();
            $row->AddSelectField($this->getCode(), $variants, array('style' => 'width:90%'));

        } else {
            $row->AddViewField($this->getCode(), $this->getValueReadonly());
        }
    }

    /**
     * Генерирует HTML для поля фильтрации
     * @see AdminListHelper::createFilterForm();
     * @return mixed
     */
    public function genFilterHTML()
    {
        print '<tr>';
        print '<td>' . $this->getSettings('TITLE') . '</td>';
        print '<td>' . $this->genEditHTML(true) . '</td>';
        print '</tr>';
    }
}