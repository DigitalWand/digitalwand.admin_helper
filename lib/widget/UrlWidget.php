<?php

namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Виджет инпута для ввода ссылки
 *
 * Доступные опции:
 * <ul>
 * <li> PROTOCOL_REQUIRED - ссылка должна иметь протокол</li>
 * <li> STYLE - inline-стили </li>
 * <li> SIZE - значение атрибута size для input </li>
 * <li> MAX_URL_LEN - длина отображаемого URL</li>
 * </ul>
 */
class UrlWidget extends StringWidget
{
    static protected $defaults = [
        'MAX_URL_LEN' => 256,
        'PROTOCOL_REQUIRED' => false,
    ];

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
        if ($this->getSettings('EDIT_IN_LIST') AND !$this->getSettings('READONLY'))
        {
            $row->AddInputField($this->getCode(), ['style' => 'width:90%']);
        }
        $row->AddViewField($this->getCode(), $value);
    }

    /**
     * Возвращает текущее значение, хранимое в поле виджета
     * Если такого поля нет, возвращает null
     *
     * @return mixed|null
     */
    public function getValue()
    {
        $code = $this->getCode();
        $value = isset($this->data[$code]) ? $this->data[$code] : null;

        if($value !== null)
        {
            $urlText = htmlspecialchars($value);
            if (strlen($urlText) > $this->getSettings('MAX_URL_LEN'))
            {
                $urlText = substr($urlText, 0, $this->getSettings('MAX_URL_LEN'));
            }
            if(($this->getSettings('READONLY') && $this->getCurrentViewType() == static::EDIT_HELPER) || $this->getCurrentViewType() == static::LIST_HELPER)
            {
                $value = '<a href="' . $value . '" target="_blank">' . $urlText . '</a>';
            }
            else
            {
                $value = $urlText;
            }
        }

        return $value;
    }

    public function processEditAction()
    {
        $value = $this->getValue();

        if (
            $this->getSettings('PROTOCOL_REQUIRED')
            && !empty($value)
            && preg_match('/^https?:\/\//', $value) == 0
        )
        {

            $this->addError('PROTOCOL_REQUIRED');
        }

    }
}