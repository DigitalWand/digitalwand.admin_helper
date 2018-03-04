<?php

namespace DigitalWand\AdminHelper\Helper;

/**
 * Базовый класс для описания админского интерфейса.
 * Включает в себя методы описывающие элементы управления, названия столбцов, типы полей и т.д.
 *
 * Есть 2 метода которые обязательно должны быть описаны в реализуемых классах:
 *
 * getFields()  - должен возвращать массив со списком табов и описанием полей для каждого таба
 * getHelpers() - должен возваращать массив со списком классов хелперов, также может включать
 * описание настроек элементов управления для хелпера.
 *
 * Для того что бы модуль мог корректна работать необходима регистрация классов унаследованных от AdminInterface.
 * Это можно сделтаь в include.php другого модуля(не рекомендуется) или AdminInterface зарегистрируется
 * автоматически если при генерации ссылок на страницы админского интерфейса использовался статический
 * метод getLink из соответствующего хелпера (ListHelper для списка элементов и EditHelper для страницы редактирования)
 *
 * При использовании разделов необходимо уведомить AdminInterface элементов и AdminInterface разделов о существовании
 * друг друга, что бы каждый из них регистрировал другого в момент собственной регистрации. Для этого достаточно указать полное
 * имя класса в методе getDependencies(), это нужно сделать как для AdminInterface элементов так и для AdminInterface разделов.
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
	 * Описание интерфейса админки: списка табов и полей. Метод должен вернуть массив вида:
	 *
	 * ```
	 * array(
	 *    'TAB_1' => array(
	 *        'NAME' => Loc::getMessage('VENDOR_MODULE_ENTITY_TAB_1_NAME'),
	 *        'FIELDS' => array(
	 *            'FIELD_1' => array(
	 *                'WIDGET' => new StringWidget(),
	 *                'TITLE' => Loc::getMessage('VENDOR_MODULE_ENTITY_FIELD_1_TITLE'),
	 *                ...
	 *            ),
	 *            'FIELD_2' => array(
	 *                'WIDGET' => new NumberWidget(),
	 *                'TITLE' => Loc::getMessage('VENDOR_MODULE_ENTITY_FIELD_2_TITLE'),
	 *                ...
	 *            ),
	 *            ...
	 *        )
	 *    ),
	 *    'TAB_2' => array(
	 *        'NAME' => Loc::getMessage('VENDOR_MODULE_ENTITY_TAB_2_NAME'),
	 *        'FIELDS' => array(
	 *            'FIELD_3' => array(
	 *                'WIDGET' => new DateTimeWidget(),
	 *                'TITLE' => Loc::getMessage('VENDOR_MODULE_ENTITY_FIELD_3_TITLE'),
	 *                ...
	 *            ),
	 *            'FIELD_4' => array(
	 *                'WIDGET' => new UserWidget(),
	 *                'TITLE' => Loc::getMessage('VENDOR_MODULE_ENTITY_FIELD_4_TITLE'),
	 *                ...
	 *            ),
	 *            ...
	 *        )
	 *    ),
	 *  ...
	 * )
	 * ```
	 *
	 * Где TAB_1..2 - символьные коды табов, FIELD_1..4 - название столбцов в таблице сущности. TITLE для поля задавать
	 * не обязательно, в этому случае он будет запрашиваться из модели.
	 *
	 * Более подробную информацию о формате описания настроек виджетов см. в классе HelperWidget.
	 *
	 * @see DigitalWand\AdminHelper\Widget\HelperWidget
	 *
	 * @return array[]
	 */
	abstract public function fields();

	/**
	 * Список классов хелперов с настройками. Метод должен вернуть массив вида:
	 *
	 * ```
	 * array(
	 *    '\Vendor\Module\Entity\AdminInterface\EntityListHelper' => array(
	 *        'BUTTONS' => array(
	 *            'RETURN_TO_LIST' => array('TEXT' => Loc::getMessage('VENDOR_MODULE_ENTITY_RETURN_TO_LIST')),
	 *            'ADD_ELEMENT' => array('TEXT' => Loc::getMessage('VENDOR_MODULE_ENTITY_ADD_ELEMENT'),
	 *            ...
	 *        )
	 *    ),
	 *    '\Vendor\Module\Entity\AdminInterface\EntityEditHelper' => array(
	 *        'BUTTONS' => array(
	 *            'LIST_CREATE_NEW' => array('TEXT' => Loc::getMessage('VENDOR_MODULE_ENTITY_LIST_CREATE_NEW')),
	 *            'LIST_CREATE_NEW_SECTION' => array('TEXT' => Loc::getMessage('VENDOR_MODULE_ENTITY_LIST_CREATE_NEW_SECTION'),
	 *            ...
	 *        )
	 *    )
	 * )
	 * ```
	 *
	 * или
	 *
	 * ```
	 * array(
	 *    '\Vendor\Module\Entity\AdminInterface\EntityListHelper',
	 *    '\Vendor\Module\Entity\AdminInterface\EntityEditHelper'
	 * )
	 * ```
	 *
	 * Где:
	 * <ul>
	 * <li> `Vendor\Module\Entity\AdminInterface` - namespace до реализованных классов AdminHelper.
	 * <li> `BUTTONS` - ключ для массива с описанием элементов управления (подробнее в методе getButton()
	 *          класса AdminBaseHelper).
	 * <li> `LIST_CREATE_NEW`, `LIST_CREATE_NEW_SECTION`, `RETURN_TO_LIST`, `ADD_ELEMENT` - символьные код элементов
	 *          управления.
	 * <li> `EntityListHelper` и `EntityEditHelper` - реализованные классы хелперов.
	 *
	 * Оба формата могут сочетаться друг с другом.
	 *
	 * @see \DigitalWand\AdminHelper\Helper\AdminBaseHelper::getButton()
	 *
	 * @return string[]
	 */
	abstract public function helpers();

	/**
	 * Список зависимых админских интерфейсов, которые будут зарегистрированы при регистраци админского интерфейса,
	 * например, админские интерфейсы разделов.
	 *
	 * @return string[]
	 */
	public function dependencies()
	{
		return array();
	}

	/**
	 * Регистрируем поля, табы и кнопки.
	 */
	public function registerData()
	{
		$fieldsAndTabs = array('FIELDS' => array(), 'TABS' => array());
		$tabsWithFields = $this->fields();

		// приводим массив хелперов к формату класс => настройки
		$helpers = array();

		foreach ($this->helpers() as $key => $value) {
			if (is_array($value)) {
				$helpers[$key] = $value;
			}
			else {
				$helpers[$value] = array();
			}
		}

		$helperClasses = array_keys($helpers);
		/**
		 * @var \Bitrix\Main\Entity\DataManager
		 */
		$model = $helperClasses[0]::getModel();
		foreach ($tabsWithFields as $tabCode => $tab) {
			$fieldsAndTabs['TABS'][$tabCode] = $tab['NAME'];

			foreach ($tab['FIELDS'] as $fieldCode => $field) {
				if (empty($field['TITLE']) && $model) {
				    //Битрикс не использует параметр title при создании экземпляра ReferenceField.
                    if (is_a($model::getEntity()->getField($fieldCode), 'Bitrix\Main\Entity\ReferenceField')) {
                        $map = $model::getMap();
                        if(isset($map[$fieldCode]['title'])){
                            $field['TITLE'] = $map[$fieldCode]['title'];
                        }
                    } else {
                        $field['TITLE'] = $model::getEntity()->getField($fieldCode)->getTitle();
                    }
				}

				$field['TAB'] = $tabCode;
				$fieldsAndTabs['FIELDS'][$fieldCode] = $field;
			}
		}

		AdminBaseHelper::setInterfaceSettings($fieldsAndTabs, $helpers, $helperClasses[0]::getModule());

		foreach ($helperClasses as $helperClass) {
			/**
			 * @var AdminBaseHelper $helperClass
			 */
			$helperClass::setInterfaceClass(get_called_class());
		}
	}

	/**
	 * Регистрация интерфейса и его зависимостей.
	 */
	public static function register()
	{
		if (!in_array(get_called_class(), static::$registeredInterfaces)) {
			static::$registeredInterfaces[] = get_called_class();

			$adminInterface = new static();
			$adminInterface->registerData();

			foreach ($adminInterface->dependencies() as $adminInterfaceClass) {
				$adminInterfaceClass::register();
			}
		}
	}
}