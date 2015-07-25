<?php

namespace AdminHelper\Widget;

IncludeModuleLangFile(__FILE__);

/**
 * Class NumberWidget Виджет с числовыми значениями
 * Доступные опции:
 * <ul>
 * <li> STYLE - inline-стили
 * <li> SIZE - значение атрибута size для input
 * </ul>
 */
class NumberWidget extends StringWidget
{
    public function checkFilter($operationType, $value)
    {
        return $this->isNumber($value);
    }

    public function checkRequired()
    {
        if ($this->getSettings('REQUIRED') == true) {
            $value = $this->getValue();
            return !is_null($value);
        } else {
            return true;
        }
    }

    public function processEditAction()
    {
        if (!$this->checkRequired()) {
            $this->addError('REQUIRED_FIELD_ERROR');

        } else if (!$this->isNumber($this->getValue())) {

            $value = $this->getValue();
            $this->addError('VALUE_IS_NOT_NUMERIC');
        }
    }

    protected function isNumber($value)
    {
        return intval($value) OR floatval($value) OR doubleval($value) OR is_null($value) OR empty($value);
    }
}