<?php

namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Виджет текстового поля для ввода гиперссылки.
 *
 * Доступные опции:
 * <ul>
 * <li> PROTOCOL_REQUIRED - ссылка должна иметь протокол</li>
 * <li> STYLE - inline-стили </li>
 * <li> SIZE - значение атрибута size для input </li>
 * <li> MAX_URL_LEN - длина отображаемого URL</li>
 * </ul>
 *
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 */
class UrlWidget extends StringWidget
{
    static protected $defaults = array(
        'MAX_URL_LEN' => 256,
        'PROTOCOL_REQUIRED' => false,
    );

    /**
     * @inheritdoc
     */
    public function generateRow(&$row, $data)
    {
        $value = $this->getValue();

        if ($this->getSettings('EDIT_IN_LIST') AND !$this->getSettings('READONLY')) {
            $row->AddInputField($this->getCode(), array('style' => 'width:90%'));
        }

        $row->AddViewField($this->getCode(), $value);
    }

    /**
     * @inheritdoc
     */
    public function getValue()
    {
        $code = $this->getCode();
        $value = isset($this->data[$code]) ? $this->data[$code] : null;

        if ($value !== null) {
            $urlText = static::prepareToOutput($value);
            $urlText = preg_replace('/^javascript:/i', '', $urlText);

            if (strlen($urlText) > $this->getSettings('MAX_URL_LEN')) {
                $urlText = substr($urlText, 0, $this->getSettings('MAX_URL_LEN'));
            }

            if (($this->getSettings('READONLY') && $this->getCurrentViewType() == static::EDIT_HELPER) || $this->getCurrentViewType() == static::LIST_HELPER) {
                $value = '<a href="' . $value . '" target="_blank">' . $urlText . '</a>';
            } else {
                $value = $urlText;
            }
        }

        return $value;
    }
    
    /**
     * @inheritdoc
     */
    protected function getValueReadonly()
    {
        return $this->getValue();
    }

    /**
     * @inheritdoc
     */
    public function processEditAction()
    {
        $value = $this->getValue();

        if (
            $this->getSettings('PROTOCOL_REQUIRED')
            && !empty($value)
            && preg_match('/^https?:\/\//', $value) == 0
        ) {

            $this->addError('PROTOCOL_REQUIRED');
        }
    }
}
