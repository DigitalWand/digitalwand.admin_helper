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
     * Список зарегистрированных интерфейсов
     * @var string
     */
    public static $registeredInterfaces = array();

    /**
     * Описание полей.
     *
     * @return array[]
     */
    abstract protected function getFields();

    /**
     * Названия классов для хелперов.
     *
     * @return string[]
     */
    abstract protected function getHelpers();

    /**
     * Список зависимых админских интерфейсов которые будут зарегистрированы
     * при регистраци админского интерфейса, например админские интерфейсы разделов
     * @return string[]
     */
    public function getDependencies()
	{
        return array();
    }

    /**
     * Регистрируем поля, табы и кнопки в AdminBaseHelper::setInterfaceSettings.
     */
    public function registerData()
    {
        $fieldsAndTabs = array('FIELDS' => array(), 'TABS' => array());
        $tabsWithFields = $this->getFields();

		// приводим массив хелперов к формату класс => настройки
		$helpers = array();
		foreach($this->getHelpers() as $key => $value)
		{
			if(is_array($value)) {
				$helpers[$key] = $value;
			}
			else {
				$helpers[$value] = array();
			}
		}

		// список классов хелперов
		$helperClasses = array_keys($helpers);

        /*
         * Получаем модель для автоподстановки TITLE из getMap, так как модель одинаковая
         * для всех хелперов, то просто берем модель из первого хелпера
         */
        $model = $helperClasses[0]::getModel();

        // разделяем описание полей на табы и поля
        foreach ($tabsWithFields as $tabCode => $tab) {
            $fieldsAndTabs['TABS'][$tabCode] = $tab['NAME'];

            foreach ($tab['FIELDS'] as $fieldCode => $field) {
                if (empty($field['TITLE'])) // если TITLE не задан то берем его из getMap модели
                {
                    $field['TITLE'] = $model::getEntity()->getField($fieldCode)->getTitle();
                }
                $field['TAB'] = $tabCode;
                $fieldsAndTabs['FIELDS'][$fieldCode] = $field;
            }
        }

        /*
         * Регистрируем настройки интерфейса в базовом классе, код модуля берем из хелпера,
         * так как все хелперы из одного модуля то просто получаем модуль первого хелпера
         */
        AdminBaseHelper::setInterfaceSettings($fieldsAndTabs, $helpers, $helperClasses[0]::getModule());

        // привязываем хелперы к классу их админ интерфейса
        foreach ($helperClasses as $helperClass) {
            $helperClass::setInterfaceClass(get_called_class());
        }
    }

    /**
     * Регистрация интерфейса
     */
    public static function register()
    {
        // регистрируем интерфейс если он еще не зарегистрирован
        if(!in_array(get_called_class(), static::$registeredInterfaces))
        {
            static::$registeredInterfaces[] = get_called_class(); // добавляем админ интерфейс в список зарегистрированных
            $adminInterface = new static();
            $adminInterface->registerData(); // собственно регистрация

            foreach($adminInterface->getDependencies() as $adminInterfaceClass)
            {
                $adminInterfaceClass::register(); // регистрируем зависимые админ интерфейсы
            }
        }
    }
}