<?php

namespace DigitalWand\AdminHelper\Helper;

/**
 * Class BaseAdminInterface
 * Базовый класс для описания админского интерфейса
 */
abstract class AdminInterface
{
    /**
     * Имя модуля для которого описывается интерфейс
     * @return string
     */
    abstract public static function getModuleName();

    /**
     * Описание полей
     * @return array[]
     */
    abstract public static function getFields();

    /**
     * Названия классов для хелперов
     * @return string[]
     */
    abstract public static function getHelpers();

    /**
     * Описание кнопок интерфейса, false - набор по умолчанию
     * @return array|bool
     */
    public static function getButtons()
    {
        return false;
    }

    /**
     * Регистрируем поля, табы и кнопки в AdminBaseHelper::setInterfaceSettings
     */
    public static function register()
    {
        /**
         * Поля и табы
         */
        $fieldsAndTabs = array('FIELDS' => array(), 'TABS' => array());
        $tabsWithFields = static::getFields();
        /**
         * Приводим формат [таб => имя, поля] к формату [табы, поля]
         */
        foreach ($tabsWithFields as $tabCode => $tab) {
            $fieldsAndTabs['TABS'][$tabCode] = $tab['NAME'];
            foreach ($tab['FIELDS'] as $fieldCode => $field) {
                $field['TAB'] = $tabCode;
                $fieldsAndTabs['FIELDS'][$fieldCode] = $field;
            }
        }

        /**
         * Регистрируем настройки хелперов
         */
        AdminBaseHelper::setInterfaceSettings($fieldsAndTabs, static::getHelpers(), static::getModuleName());

        /**
         * Привязываем хелперы к классу интерфейса
         */
        foreach(static::getHelpers() as $helperClass)
        {
            $helperClass::setInterfaceClass(get_called_class());
        }
    }

}