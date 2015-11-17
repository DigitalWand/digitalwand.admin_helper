<?php

namespace DigitalWand\AdminHelper\Helper;

use Bitrix\Main\Entity\DataManager;

/**
 * Базовый класс для описания админского интерфейса.
 *
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 * @author Artem Yarygin <artx19@yandex.ru>
 */
abstract class AdminInterface
{
    /**
     * Список зависимых админских интерфейсов которые будут зарегистрированы
     * при регистраци админского интерфейса, например админские интерфейсы разделов
     * @var string[]
     */
    protected static $dependencies = [];

    /**
     * Список зарегистрированных интерфейсов
     * @var string
     */
    protected static $registeredInterfaces = [];

    /**
     * Имя модуля для которого описывается интерфейс.
     *
     * @return string
     */
    abstract public static function getModuleName();

    /**
     * Описание полей.
     *
     * @return array[]
     */
    abstract public static function getFields();

    /**
     * Названия классов для хелперов.
     *
     * @return string[]
     */
    abstract public static function getHelpers();

    /**
     * Описание кнопок интерфейса, false - набор по умолчанию.
     *
     * @return array|bool
     */
    public static function getButtons()
    {
        return false;
    }

    /**
     * Регистрируем поля, табы и кнопки в AdminBaseHelper::setInterfaceSettings.
     */
    public static function register()
    {
        // если интерфейс уже зарегистрирован ничего не делаем
        if(in_array(get_called_class(), static::$registeredInterfaces))
        {
            return false;
        }
        $fieldsAndTabs = array('FIELDS' => array(), 'TABS' => array());
        $tabsWithFields = static::getFields();

        $helpers = static::getHelpers();
        /**
         * @var AdminBaseHelper $helper
         */
        $helper = $helpers[0];
        /**
         * @var DataManager $model
         */
        $model = $helper::getModel();

        foreach ($tabsWithFields as $tabCode => $tab) {
            $fieldsAndTabs['TABS'][$tabCode] = $tab['NAME'];
            
            foreach ($tab['FIELDS'] as $fieldCode => $field) {
                if (empty($field['TITLE']))
                {
                    $field['TITLE'] = $model::getEntity()->getField($fieldCode)->getTitle();
                }
                $field['TAB'] = $tabCode;
                $fieldsAndTabs['FIELDS'][$fieldCode] = $field;
            }
        }

        AdminBaseHelper::setInterfaceSettings($fieldsAndTabs, static::getHelpers(), static::getModuleName());

        foreach (static::getHelpers() as $helperClass) {
            $helperClass::setInterfaceClass(get_called_class());
        }

        static::$registeredInterfaces[] = get_called_class();

        // Регистрация зависимых админских интерфейсов
        foreach(static::$dependencies as $adminInterfaceClass)
        {
            $adminInterfaceClass::register();
        }
    }
}