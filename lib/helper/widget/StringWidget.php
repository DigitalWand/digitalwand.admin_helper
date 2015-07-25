<?php

namespace AdminHelper\Widget;

/**
 * Class StringWidget Виджет строки с текстом
 * Доступные опции:
 * <ul>
 * <li> STYLE - inline-стили
 * <li> SIZE - значение атрибута size для input
 * <li> TRANSLIT - true, если поле будет транслитерироваться в символьный код
 * </ul>
 */
class StringWidget extends HelperWidget
{
    /**
     * Генерирует HTML для редактирования поля
     * @see AdminEditHelper::showField();
     * @return mixed
     */
    protected function genEditHTML()
    {
        $style = $this->getSettings('STYLE');
        $size = $this->getSettings('SIZE');

        $link = '';
        if ($this->getSettings('TRANSLIT')) {

            //TODO: refactor this!
            $uniqId = get_class($this->entityName) . '_' . $this->getCode();
            $nameId = 'name_link_' . $uniqId;
            $linkedFunctionName = 'set_linked_' . get_class($this->entityName) . '_CODE';//FIXME: hardcode here!!!
            if (isset($this->entityName->{$this->entityName->pk()})) {
                $pkVal = $this->entityName->{$this->entityName->pk()};
            } else {
                $pkVal = '_new_';
            }
            $nameId .= $pkVal;
            $linkedFunctionName .= $pkVal;

            $link = '<image id="' . $nameId . '" title="' . GetMessage("IBSEC_E_LINK_TIP") . '" class="linked" src="/bitrix/themes/.default/icons/iblock/link.gif" onclick="' . $linkedFunctionName . '()" />';
        }

        //FIXME: тут было htmlentities, на на этом проекте оно превращает кириллицу в квакозябры.
        return '<input type="text"
                       name="' . $this->getEditInputName() . '"
                       value="' . $this->getValue() . '"
                       size="' . $size . '"
                       style="' . $style . '"/>' . $link;
    }

    public function checkRequired()
    {
        if ($this->getSettings('REQUIRED') == true) {
            $value = $this->getValue();

            return !empty($value);
        } else {
            return true;
        }
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
        $value = $this->getValue();
        if ($this->settings['EDIT_IN_LIST'] AND !$this->settings['READONLY']) {
            $row->AddInputField($this->getCode(),
              array('style' => 'width:90%'));
        }
        $row->AddViewField($this->getCode(), $value);

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

        if ($this->isFilterBetween()) {
            list($from, $to) = $this->getFilterInputName();
            print '<td>
            <div class="adm-filter-box-sizing">
                <span style="display: inline-block; left: 11px; top: 5px; position: relative;">От:</span>
                <div class="adm-input-wrap" style="display: inline-block">
                    <input type="text" class="adm-input" name="' . $from . '" value="' . $$from . '">
                </div>
                <span style="display: inline-block; left: 11px; top: 5px; position: relative;">До:</span>
                <div class="adm-input-wrap" style="display: inline-block">
                    <input type="text" class="adm-input" name="' . $to . '" value="' . $$to . '">
                </div>
            </div>
            </td> ';

        } else {
            print '<td><input type="text" name="' . $this->getFilterInputName() . '" size="47" value="' . $this->getCurrentFilterValue() . '"></td>';

        }
        print '</tr>';
    }

    /**
     * Плиск по подстроке
     * @param array $filter
     * @param $select
     * @param $sort
     * @param array $raw
     */
    public function changeGetListOptions(&$filter, &$select, &$sort, $raw)
    {
        parent::changeGetListOptions($filter, $select, $sort, $raw);
        if (isset($filter[$this->getCode()])) {
            $filter["%" . $this->getCode()] = $filter[$this->getCode()];
            unset($filter[$this->getCode()]);
        }

    }


}