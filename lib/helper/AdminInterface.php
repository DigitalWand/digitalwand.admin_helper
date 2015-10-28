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
		foreach ($tabsWithFields as $tabCode => $tab)
		{
			$fieldsAndTabs['TABS'][$tabCode] = $tab['NAME'];
			foreach ($tab['FIELDS'] as $fieldCode => $field)
			{
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
		foreach (static::getHelpers() as $helperClass)
		{
			$helperClass::setInterfaceClass(get_called_class());
		}
	}

	/**
	 * Возвращает URL для хелперов, в зависимости от его родителя,
	 * используется для генерации ссылок в меню
	 * @param $class
	 * @return string
	 */
	public static function getUrl($class)
	{
		if (strpos('\\', $class) === false) // собираем полный путь до класса если это относительный url
		{
			$helperClassParts = explode('\\', get_called_class());
			array_pop($helperClassParts);
			array_push($helperClassParts, $class);
			$class = '\\' . implode('\\', $helperClassParts);
		}

		if (is_subclass_of($class, 'DigitalWand\AdminHelper\Helper\AdminListHelper'))
		{
			$method = 'getListPageURL';
		}
		elseif (is_subclass_of($class, 'DigitalWand\AdminHelper\Helper\AdminEditHelper'))
		{
			$method = 'getEditPageURL';
		}
		else
		{
			return '/admin/404'; // @todo возможно это должно выглядеть как-то по другому
		}

		return $class::$method();
	}

	/**
	 * Возвращает часть namespace в форме url параметра
	 * используется в getListPageURL и getEditPageURL
	 * @return string
	 */
	public static function getNamespaceUrlParam()
	{
		$moduleNameSpace = implode( // собираем namespace модуля
			'\\',
			array_map('ucfirst',
				explode('.', static::getModuleName())
			)
		);

		// собираем namespace интерфейса
		$interfaceClass = trim(str_replace($moduleNameSpace, '', get_called_class()), '\\');

		$namespaceParts = explode('\\', $interfaceClass);
		array_pop($namespaceParts);

		// исключаем namespace модуля из namespace интерфейса
		return str_replace(
			'\\',
			'_',
			implode(
				'\\',
				array_map('lcfirst', $namespaceParts)
			)
		);
	}

}