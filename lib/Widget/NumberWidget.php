<?php

namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Виджет с числовыми значениями. Точная копия StringWidget, только работает с числами и не ищет по подстроке.
 */
class NumberWidget extends StringWidget
{
    static protected $defaults = array(
        'FILTER' => '=',
        'EDIT_IN_LIST' => true
    );

    public function checkFilter($operationType, $value)
    {
        return $this->isNumber($value);
    }

    public function checkRequired()
    {
        if ($this->getSettings('REQUIRED') == true) {
            $value = $this->getValue();
            return !is_null($value) && $value !== '';
        } else {
            return true;
        }
    }

    public function processEditAction()
    {
        if (!$this->checkRequired()) {
            $this->addError('DIGITALWAND_AH_REQUIRED_FIELD_ERROR');

        } else if (!$this->isNumber($this->getValue())) {
            $this->addError('VALUE_IS_NOT_NUMERIC');
        }
    }

    protected function isNumber($value)
    {
        return is_numeric($value) OR is_null($value) OR empty($value);
    }
}
