<?php
namespace AdminHelper\Widget;
/**
 * Class TextAreaWidget
 * Доступные опции:
 * <ul>
 * <li> COLS - ширина
 * <li> ROWS - высота
 * </ul>
 */
class TextAreaWidget extends StringWidget
{
    // количество отображаемых символов в режиме списка.
    const LIST_TEXT_SIZE = 150;

    static protected $defaults = array(
        'COLS' => 65,
        'ROWS' => 5
    );
    /**
     * Генерирует HTML для редактирования поля
     * @see AdminEditHelper::showField();
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
     * @param CAdminListRow $row
     * @param array $data - данные текущей строки
     * @return mixed
     */
    public function genListHTML(&$row, $data)
    {
        $text = $data[$this->code];

        if (strlen($text) > self::LIST_TEXT_SIZE && !$this->isExcelView())
        {
            $pos = false;
            $pos = $pos === false ? stripos($text, " ", self::LIST_TEXT_SIZE) : $pos;
            $pos = $pos === false ? stripos($text, "\n", self::LIST_TEXT_SIZE) : $pos;
            $pos = $pos === false ? stripos($text, "</", self::LIST_TEXT_SIZE) : $pos;
            $pos = $pos === false ? 300 : $pos;
            $text = substr($text, 0, $pos)." ...";
        }
        
        
        $row->AddViewField($this->code, $text);

    }

}