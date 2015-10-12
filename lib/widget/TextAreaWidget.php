<?php
namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Class TextAreaWidget
 * Выводит textarea для редактирования длинных строк.
 * Урезает длинные строки при отображении в списке
 *
 * Доступные опции:
 * <ul>
 * <li><b>COLS</b> - ширина</li>
 * <li><b>ROWS</b> - высота</li>
 * </ul>
 */
class TextAreaWidget extends StringWidget
{
    /**
     * количество отображаемых символов в режиме списка.
     */
    const LIST_TEXT_SIZE = 150;

    static protected $defaults = array(
        'COLS' => 65,
        'ROWS' => 5,
        'EDIT_IN_LIST' => false
    );

    /**
     * Генерирует HTML для редактирования поля
     * @return mixed
     */
    protected function genEditHTML()
    {
        $cols = $this->getSettings('COLS');
        $rows = $this->getSettings('ROWS');
        return '<textarea cols="' . $cols . '" rows="' . $rows . '" name="' . $this->getEditInputName() . '">' . $this->getValue() . '</textarea>';
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
        $text = $this->getValue();

        if ($this->getSettings('EDIT_IN_LIST') AND !$this->getSettings('READONLY')) {
            $row->AddInputField($this->getCode(), array('style' => 'width:90%'));

        } else {
            if (strlen($text) > self::LIST_TEXT_SIZE && !$this->isExcelView()) {
                $pos = false;
                $pos = $pos === false ? stripos($text, " ", self::LIST_TEXT_SIZE) : $pos;
                $pos = $pos === false ? stripos($text, "\n", self::LIST_TEXT_SIZE) : $pos;
                $pos = $pos === false ? stripos($text, "</", self::LIST_TEXT_SIZE) : $pos;
                $pos = $pos === false ? 300 : $pos;
                $text = substr($text, 0, $pos) . " ...";
            }

            $row->AddViewField($this->code, $text);
        }

    }

}