<?php

namespace DigitalWand\AdminHelper\Helper;

/**
 * Базовый класс для описания админского интерфейса.
 *
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 * @author Artem Yarygin <artx19@yandex.ru>
 */
abstract class AdminInterface
{
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
        $fieldsAndTabs = array('FIELDS' => array(), 'TABS' => array());
        $tabsWithFields = static::getFields();

		$helpers = static::getHelpers();
		$helper = $helpers[0];
		$model = $helper::getModel(); // получаем модель из первого хелпера
        foreach ($tabsWithFields as $tabCode => $tab) {
            $fieldsAndTabs['TABS'][$tabCode] = $tab['NAME'];
            foreach ($tab['FIELDS'] as $fieldCode => $field) {
				if(empty($field['TITLE'])) // если TITLE не задан в интерфейсе берем из модели
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
    }
}