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

    public static function getButtons()
    {
        /**
         * Описание кнопок
         */
    }

    /**
     * Регистрируем поля и табы в AdminBaseHelper::setInterfaceSettings
     */
    public static function register()
    {
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
        AdminBaseHelper::setInterfaceSettings($fieldsAndTabs, static::getHelpers(), static::getModuleName());
    }

}