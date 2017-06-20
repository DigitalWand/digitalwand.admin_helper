<?php

namespace DigitalWand\AdminHelper\Helper;

use Bitrix\Main\Entity\ReferenceField;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use DigitalWand\AdminHelper\EntityManager;
use DigitalWand\AdminHelper\Widget\HelperWidget;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Context;

Loader::includeModule('highloadblock');
Loc::loadMessages(__FILE__);

/**
 * Данный модуль реализует подход MVC для создания административного интерфейса.
 *
 * Возможность построения административного интерфейса появляется благодаря наличию единого API для CRUD-операциями над
 * сущностями. Поэтому построение админ. интерфейса средствами данного модуля возможно только для классов, реализующих
 * API ORM Битрикс. При желании использовать данный модуль для сущностей, не использующих ORM Битрикс, можно
 * подготовить для таких сущностей класс-обёртку, реализующий необходимые функции.
 *
 * Основные понятия модуля:
 * <ul>
 * <li>Мдель: "model" в терминах MVC. Класс, унаследованный от DataManager или реализующий аналогичный API.</li>
 * <li>Хэлпер: "view" в терминах MVC. Класс, реализующий отрисовку интерфейса списка или детальной страницы.</li>
 * <li>Роутер: "controller" в терминах MVC. Файл, принимающий все запросы к админке данного модуля, создающий нужные
 * хэлперы с нужными настройками. С ним напрямую работать не придётся.</li>
 * <li>Виджеты: "delegate" в терминах MVC. Классы, отвечающие за отрисовку элементов управления для отдельных полей
 * сущностей. В списке и на детальной.</li>
 * </ul>
 *
 * Схема работы с модулем следующая:
 * <ul>
 * <li>Реализация класса AdminListHelper - для управления страницей списка элементов</li>
 * <li>Реализация класса AdminEditHelper - для управления страницей просмотра/редактирования элемента</li>
 * <li>Реализация класса AdminInterface - для описания конфигурации полей админки и классы интерфейсов</li>
 * <li>Реализация класса AdminSectionListHelper - для описания странице списка разделов(если они используются)</li>
 * <li>Реализация класса AdminSectionEditHelper - для управления страницей просмотра/редактирования раздела(если они используются)</li>
 * <li>Если не хватает возможностей виджетов, идущих с модулем, можно реализовать свой виджет, унаследованный от любого
 * другого готового виджета или от абстрактного класса HelperWidget</li>
 * </ul>
 *
 * Устаревший функционал:
 * <ul>
 * <li>Файл Interface.php с вызовом AdminBaseHelper::setInterfaceSettings(), в который передается
 * конфигурация полей админки и классы.</li>
 *
 * Рекомендуемая файловая структура для модулей, использующих данный функционал:
 * <ul>
 * <li>Каталог <b>admin</b>. Достаточно поместить в него файл menu.php, отдельные файлы для списка и детальной
 * создавать не надо благодаря единому роутингу.</li>
 * <li>Каталог <b>classes</b> (или lib): содержит классы модли, представлений и делегатов.</li>
 * <li> -- <b>classes/admininterface</b>: каталог, содержащий классы "view", унаследованные от AdminListHelper,
 * AdminEditHelper, AdminInterface, AdminSectionListHelper и AdminSectionEditHelper.</li>
 * <li> -- <b>classes/widget</b>: каталог, содержащий виджеты ("delegate"), если для модуля пришлось создавать
 * свои.</li>
 * <li> -- <b>classes/model</b>: каталог с моделями, если пришлось переопределять поведение стандартынх функций getList
 * и т.д.</li>
 * </ul>
 *
 * Использовать данную структуру не обязательно, это лишь рекомендация, основанная на успешном опыте применения модуля
 * в ряде проектов.
 *
 * Единственное <b>обязательное</b> условие - расположение  всех реализуемых классов админ хелперов и админ интерфейсов
 * в одном неймспейсе
 *
 * При использовании разделов нужно обязательно прописать в модели элементов привязку к модели разделов, например:
 *
 * ```php
 * <?php
 * class ElementModel
 * {
 * 		public static function getMap()
 *  	{
 * 			return [
 * 				'CATEGORY' => [
 *					'data_type' => 'Vendor\Module\CategoryTable',
 *					'reference' => ['=this.CATEGORY_ID' => 'ref.ID'],
 *				]
 * 			];
 * 		}
 * ```
 *
 * @see AdminInterface::fields()
 * @package AdminHelper
 *
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 * @author Artem Yarygin <artx19@yandex.ru>
 */
abstract class AdminBaseHelper
{
	/**
	 * @internal
	 * @var string адрес обработчика запросов к админ. интерфейсу.
	 */
	static protected $routerUrl = '/bitrix/admin/admin_helper_route.php';

	/**
	 * @var string
	 * Имя класса используемой модели. Используется для выполнения CRUD-операций.
	 * При наследовании класса необходимо переопределить эту переменную, указав полное имя класса модели.
	 *
	 * @see DataManager
	 * @api
	 */
	static protected $model;

	/**
	 * @var string
	 * Имя класса используемого менеджера сущностей. Используется для выполнения CRUD-операций.
	 *
	 * @see DataManager
	 * @api
	 */
	static protected $entityManager = '\DigitalWand\AdminHelper\EntityManager';

	/**
	 * @var string
	 * Назвние модуля данной модели.
	 * При наследовании класса необходимо указать нзвание модуля, в котором он находится.
	 * А можно и не указывать, в этому случае он определится автоматически по namespace класса
	 * Используется для избежания конфликтов между именами представлений.
	 *
	 * @api
	 */
	static public $module = array();

	/**
	 * @var string[]
	 * Название представления.
	 * При наследовании класса необходимо указать название представления.
	 * А можно и не указывать, в этому случае оно определится автоматически по namespace класса.
	 * Оно будет использовано при построении URL к данному разделу админки.
	 * Не должно содержать пробелов и других символов, требующих преобразований для
	 * адресной строки браузера.
	 *
	 * @api
	 */
	static protected $viewName = array();

	/**
	 * @var array
	 * Настройки интерфейса
	 * @see AdminBaseHelper::setInterfaceSettings()
	 * @internal
	 */
	static protected $interfaceSettings = array();

	/**
	 * @var array
	 * Привязка класса интерфеса к классу хелпера
	 */
	static protected $interfaceClass = array();

	/**
	 * @var array
	 * Хранит список отображаемых полей и настройки их отображения
	 * @see AdminBaseHelper::setInterfaceSettings()
	 */
	protected $fields = array();

	/**
	 * @var \CMain
	 * Замена global $APPLICATION;
	 */
	protected $app;
	protected $validationErrors = array();

	/**
	 * @var string
	 * Позволяет непосредственно указать адрес страницы списка. Полезно, в случае, если такая станица реализована без
	 * использования данного модуля. В случае, если поле определено для класса, роутинг не используется.
	 *
	 * @see AdminBaseHelper::getListPageUrl
	 * @api
	 */
	static protected $listPageUrl;

	/**
	 * @var string
	 * $viewName представления, отвечающего за страницу списка. Необходимо указывать только для классов, уналедованных
	 * от AdminEditHelper.
	 * Необязательное, сгенерируется автоматически если не определено
	 *
	 * @see AdminBaseHelper::getViewName()
	 * @see AdminBaseHelper::getListPageUrl
	 * @see AdminEditHelper
	 * @api
	 */
	static protected $listViewName;

	/**
	 * @var string
	 * Позволяет непосредственно указать адрес страницы просмотра/редактирования элемента. Полезно, в случае, если
	 * такая станица реализована без использования данного модуля. В случае, если поле определено для класса,
	 * роутинг не используется.
	 *
	 * @see AdminBaseHelper::getEditPageUrl
	 * @api
	 */
	static protected $editPageUrl;

	/**
	 * @var string
	 * $viewName представления, отвечающего за страницу редактирования/просмотра элемента. Необходимо указывать только
	 *     для классов, уналедованных от AdminListHelper.
	 *
	 * @see AdminBaseHelper::getViewName()
	 * @see AdminBaseHelper::getEditPageUrl
	 * @see AdminListHelper
	 * @api
	 */
	static protected $editViewName;

	/**
	 * @var string
	 * Позволяет непосредственно указать адрес страницы просмотра/редактирования раздела. Полезно, в случае, если
	 * такая станица реализована без использования данного модуля. В случае, если поле определено для класса,
	 * роутинг не используется.
	 *
	 * @see AdminBaseHelper::getEditPageUrl
	 * @api
	 */
	static protected $sectionsEditPageUrl;

	/**
	 * @var string
	 * $viewName представления, отвечающего за страницу редактирования/просмотра раздела. Необходимо указывать только
	 * для классов, уналедованных от AdminListHelper.
	 * Необязательное, сгенерируется автоматически если не определено
	 *
	 * @see AdminBaseHelper::getViewName()
	 * @see AdminBaseHelper::getEditPageUrl
	 * @see AdminListHelper
	 * @api
	 */
	static protected $sectionsEditViewName;

	/**
	 * @var array
	 * Дополнительные параметры URL, которые будут добавлены к параметрам по-умолчанию, генерируемым автоматически
	 * @api
	 */
	protected $additionalUrlParams = array();

	/**
	 * @var string контекст выполнения. Полезен для информирования виджетов о том, какая операция в настоящий момент
	 *     производится.
	 */
	protected $context = '';

	/**
	 * Флаг использования разделов, необходимо переопределять в дочернем классе
	 * @var bool
	 */
	static protected $useSections = false;

	/**
	 * Правило именования хелперов для разделов по умолчанию
	 * @var string
	 */
	static protected $sectionSuffix = 'Sections';

	/**
	 * @param array $fields список используемых полей и виджетов для них
	 * @param array $tabs список вкладок для детальной страницы
	 * @param string $module название модуля
	 */
	public function __construct(array $fields, array $tabs = array(), $module = "")
	{
		global $APPLICATION;

		$this->app = $APPLICATION;

		$settings = array(
			'FIELDS' => $fields,
			'TABS' => $tabs
		);
		if (static::setInterfaceSettings($settings)) {
			$this->fields = $fields;
		}
		else {
			$settings = static::getInterfaceSettings();
			$this->fields = $settings['FIELDS'];
		}
	}

	/**
	 * @param string $viewName Имя вьюхи, для которой мы хотим получить натсройки
	 *
	 * @return array Возвращает настройки интерфейса для данного класса.
	 *
	 * @see AdminBaseHelper::setInterfaceSettings()
	 * @api
	 */
	public static function getInterfaceSettings($viewName = '')
	{
		if (empty($viewName)) {
			$viewName = static::getViewName();
		}

		return self::$interfaceSettings[static::getModule()][$viewName]['interface'];
	}

	/**
	 * Основная функция для конфигурации всего административного интерфейса.
	 *
	 * @param array $settings настройки полей и вкладок
	 * @param array $helpers список классов-хэлперов, используемых для отрисовки админки
	 * @param string $module название модуля
	 *
	 * @return bool false, если для данного класса уже были утановлены настройки
	 *
	 * @api
	 */
	public static function setInterfaceSettings(array $settings, array $helpers = array(), $module = '')
	{
		foreach ($helpers as $helperClass => $helperSettings) {
			if (!is_array($helperSettings)) { // поддержка старого формата описания хелперов
				$helperClass = $helperSettings; // в значении передается класс хелпера а не настройки
				$helperSettings = array(); // настроек в старом формате нет
			}
			$success = $helperClass::registerInterfaceSettings($module, array_merge($settings, $helperSettings));
			if (!$success) return false;
		}

		return true;
	}

	/**
	 * Привязывает класса хелпера из которого вызывается к интерфесу, используется при получении
	 * данных об элементах управления из интерфейса.
	 *
     * @param $class
	 */
	public static function setInterfaceClass($class)
	{
		static::$interfaceClass[get_called_class()] = $class;
	}

	/**
	 * Возвращает класс интерфейса к которому привязан хелпер из которого вызван метод.
     *
	 * @return array
	 */
	public static function getInterfaceClass()
	{
		return isset(static::$interfaceClass[get_called_class()]) ? static::$interfaceClass[get_called_class()] : false;
	}

	/**
	 * Регистрирует настройки интерфейса для текущего хелпера
	 *
	 * @param string $module имя текущего модуля
	 * @param $interfaceSettings
     *
	 * @return bool
	 * @internal
	 */
	public static function registerInterfaceSettings($module, $interfaceSettings)
	{
		if (isset(self::$interfaceSettings[$module][static::getViewName()]) || empty($module)
			|| empty($interfaceSettings)
		) {
			return false;
		}

		self::$interfaceSettings[$module][static::getViewName()] = array(
			'helper' => get_called_class(),
			'interface' => $interfaceSettings
		);

		return true;
	}

	/**
	 * Получает настройки интерфейса для данного модуля и представления. Используется при роутинге.
	 * Возвращается массив со следующими ключами:
	 *
	 * <ul>
	 * <li> helper - название класса-хэлпера, который будет рисовать страницу</li>
	 * <li> interface - настройки интерфейса для хелпера</li>
	 * </ul>
	 *
	 * @param string $module Модуль, для которого нужно получить настройки.
	 * @param string $view Название представления.
     *
	 * @return array
	 * @internal
	 */
	public static function getGlobalInterfaceSettings($module, $view)
	{
		if (!isset(self::$interfaceSettings[$module][$view])) {
			return false;
		}

		return array(
			self::$interfaceSettings[$module][$view]['helper'],
			self::$interfaceSettings[$module][$view]['interface'],
		);
	}

	/**
     * Возвращает имя текущего представления.
     *
	 * @return string
	 * @api
	 */
	public static function getViewName()
	{
		if (!is_array(static::$viewName)) {
			return static::$viewName;
		}

		$className = get_called_class();

		if (!isset(static::$viewName[$className])) {
			$classNameParts = explode('\\', trim($className, '\\'));

			if (count($classNameParts) > 2) {
				$classCaption = array_pop($classNameParts); // название класса без namespace
				preg_match_all('/((?:^|[A-Z])[a-z]+)/', $classCaption, $matches);
				$classCaptionParts = $matches[0];

				if (end($classCaptionParts) == 'Helper') {
					array_pop($classCaptionParts);
				}

				static::$viewName[$className] = strtolower(implode('_', $classCaptionParts));
			}
		}

		return static::$viewName[$className];
	}

	/**
	 * Возвращает поле модели которое используется для привязки к разделу из поля с типом совпадающим с классом модели
	 * раздела.
	 * @return string
	 * @throws Exception
	 */
	public static function getSectionField()
	{
		$sectionListHelper = static::getHelperClass(AdminSectionListHelper::className());

		if (empty($sectionListHelper))
		{
			return null;
		}

		$sectionModelClass = $sectionListHelper::getModel();
		$sectionModelClass = preg_replace('/Table$/', '', $sectionModelClass);
		$modelClass = static::getModel();

		foreach ($modelClass::getMap() as $field => $data) {
			if ($data instanceof ReferenceField && $data->getDataType() . 'Table' === $sectionModelClass) {
				return str_replace('=this.', '', reset($data->getReference()));
			}
			if (is_array($data) && $data['data_type'] === $sectionModelClass) {
				return str_replace('=this.', '', key($data['reference']));
			}
		}

		throw new Exception('References to section model not found');
	}

	/**
     * Возвращает имя класса используемой модели.
     *
	 * @return \Bitrix\Main\Entity\DataManager|string
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\SystemException
	 * @throws \Exception
	 * @api
	 */
	public static function getModel()
	{
		if (static::$model) {
			return static::getHLEntity(static::$model);
		}

		return null;
	}

	/**
	 * Возвращает имя модуля. Если оно не задано, то определяет автоматически из namespace класса.
     *
	 * @return string
     *
	 * @throws LoaderException
	 * @api
	 */
	public static function getModule()
	{
		if (!is_array(static::$module)) {
			return static::$module;
		}

		$className = get_called_class();

		if (!isset(static::$module[$className])) {
			$classNameParts = explode('\\', trim($className, '\\'));

			$moduleNameParts = array();
			$moduleName = false;

			while (count($classNameParts)) {
				$moduleNameParts[] = strtolower(array_shift($classNameParts));
				$moduleName = implode('.', $moduleNameParts);

				if (ModuleManager::isModuleInstalled($moduleName)) {
					static::$module[$className] = $moduleName;
					break;
				}
			}

			if (empty($moduleName)) {
				throw new LoaderException('Module name not found');
			}
		}

		return static::$module[$className];
	}

	/**
	 * Возвращает модифцированный массив с описанием элемента управления по его коду. Берет название и настройки
     * из админ-интерфейса, если они не заданы — используются значения по умолчанию.
     *
     * Если элемент управления описан в админ-интерфейсе, то дефолтные настройки и описанные в классе интерфейса
     * будут совмещены (смержены).
     *
	 * @param $code
	 * @param $params
	 * @param array $keys
     *
	 * @return array|bool
	 */
	protected function getButton($code, $params, $keys = array('name', 'TEXT'))
	{
		$interfaceClass = static::getInterfaceClass();
		$interfaceSettings = static::getInterfaceSettings();

		if ($interfaceClass && !empty($interfaceSettings['BUTTONS'])) {
			$buttons = $interfaceSettings['BUTTONS'];

			if (is_array($buttons) && isset($buttons[$code])) {
				if ($buttons[$code]['VISIBLE'] == 'N') {
					return false;
				}
				$params = array_merge($params, $buttons[$code]);

				return $params;
			}
		}

		$text = Loc::getMessage('DIGITALWAND_ADMIN_HELPER_' . $code);

		foreach ($keys as $key) {
			$params[$key] = $text;
		}

		return $params;
	}

	/**
	 * Возвращает список полей интерфейса.
     *
	 * @see AdminBaseHelper::setInterfaceSettings()
     *
	 * @return array
     *
	 * @api
	 */
	public function getFields()
	{
		return $this->fields;
	}

	/**
	 * Окончательно выводит административную страницу.
	 */
	abstract public function show();

	/**
	 * Получает название таблицы используемой модели.
     *
	 * @return mixed
	 */
	public function table()
	{
		/**
         * @var DataManager $className
         */
		$className = static::getModel();

		return $className::getTableName();
	}

	/**
	 * Возвращает первичный ключ таблицы используемой модели
	 * Для HL-инфоблоков битрикс - всегда ID. Но может поменяться для какой-либо другой сущности.
	 * @return string
	 * @api
	 */
	public function pk()
	{
		return 'ID';
	}

	/**
	 * Возвращает значение первичного ключа таблицы используемой модели
	 * @return array|int|null
	 * 
	 * @api
	 */
	public function getPk()
	{
		return isset($_REQUEST['FIELDS'][$this->pk()]) ? $_REQUEST['FIELDS'][$this->pk()] : $_REQUEST[$this->pk()];
	}

	/**
	 * Возвращает первичный ключ таблицы используемой модели разделов. Для HL-инфоблоков битрикс - всегда ID.
     * Но может поменяться для какой-либо другой сущности.
     *
	 * @return string
	 *
     * @api
	 */
	public function sectionPk()
	{
		return 'ID';
	}

	/**
	 * Устанавливает заголовок раздела в админке.
     *
	 * @param string $title
	 *
     * @api
	 */
	public function setTitle($title)
	{
		$this->app->SetTitle($title);
	}

	/**
	 * Функция для обработки дополнительных операций над элементами в админке. Как правило, должно оканчиваться
     * LocalRedirect после внесения изменений.
	 *
	 * @param string $action Название действия.
	 * @param null|int $id ID элемента.
     *
	 * @api
	 */
	protected function customActions($action, $id = null)
	{
		return;
	}

	/**
	 * Выполняется проверка прав на доступ к сущности.
     *
	 * @return bool
	 *
     * @api
	 */
	protected function hasRights()
	{
		return true;
	}

	/**
	 * Выполняется проверка прав на выполнение операций чтения элементов.
     *
	 * @return bool
	 *
     * @api
	 */
	protected function hasReadRights()
	{
		return true;
	}

	/**
	 * Выполняется проверка прав на выполнение операций редактирования элементов.
	 *
     * @return bool
     *
	 * @api
	 */
	protected function hasWriteRights()
	{
		return true;
	}

	/**
	 * Проверка прав на изменение определенного элемента.
     *
	 * @param array $element Массив данных элемента.
     *
	 * @return bool
     *
     * @api
	 */
	protected function hasWriteRightsElement($element = array())
	{
		if (!$this->hasWriteRights()) {
			return false;
		}

		return true;
	}

	/**
	 * Выполняется проверка прав на выполнение опреаций удаления элементов.
     *
	 * @return bool
     *
	 * @api
	 */
	protected function hasDeleteRights()
	{
		return true;
	}

	/**
	 * Выводит сообщения об ошибках.
     *
	 * @internal
	 */
	protected function showMessages()
	{
		$allErrors = $this->getErrors();
		$notes = $this->getNotes();

		if (!empty($allErrors)) {
			$errorList[] = implode("\n", $allErrors);
		}
		if ($e = $this->getLastException()) {
			$errorList[] = trim($e->GetString());
		}

		if (!empty($errorList)) {
			$errorText = implode("\n\n", $errorList);
			\CAdminMessage::ShowOldStyleError($errorText);
		}
		else {
			if (!empty($notes)) {
				$noteText = implode("\n\n", $notes);
				\CAdminMessage::ShowNote($noteText);
			}
		}
	}

	/**
	 * @return bool|\CApplicationException
     *
	 * @internal
	 */
	protected function getLastException()
	{
		if (isset($_SESSION['APPLICATION_EXCEPTION']) AND !empty($_SESSION['APPLICATION_EXCEPTION'])) {
			/** @var CApplicationException $e */
			$e = $_SESSION['APPLICATION_EXCEPTION'];
			unset($_SESSION['APPLICATION_EXCEPTION']);

			return $e;
		}
		else {
			return false;
		}
	}

	/**
	 * @param $e
	 */
	protected function setAppException($e)
	{
		$_SESSION['APPLICATION_EXCEPTION'] = $e;
	}

	/**
	 * Добавляет ошибку или массив ошибок для показа пользователю.
     *
	 * @param array|string $errors
	 *
     * @api
	 */
	public function addErrors($errors)
	{
		if (!is_array($errors)) {
			$errors = array($errors);
		}

		if (isset($_SESSION['ELEMENT_SAVE_ERRORS']) AND !empty($_SESSION['ELEMENT_SAVE_ERRORS'])) {
			$_SESSION['ELEMENT_SAVE_ERRORS'] = array_merge($_SESSION['ELEMENT_SAVE_ERRORS'], $errors);
		}
		else {
			$_SESSION['ELEMENT_SAVE_ERRORS'] = $errors;
		}
	}

	/**
	 * Добавляет уведомление или список уведомлений для показа пользователю.
     *
	 * @param array|string $notes
	 *
     * @api
	 */
	public function addNotes($notes)
	{
		if (!is_array($notes)) {
			$notes = array($notes);
		}

		if (isset($_SESSION['ELEMENT_SAVE_NOTES']) AND !empty($_SESSION['ELEMENT_SAVE_NOTES'])) {
			$_SESSION['ELEMENT_SAVE_NOTES'] = array_merge($_SESSION['ELEMENT_SAVE_NOTES'],
				$notes);
		}
		else {
			$_SESSION['ELEMENT_SAVE_NOTES'] = $notes;
		}
	}

	/**
	 * @return bool|array
     *
	 * @api
	 */
	protected function getErrors()
	{
		if (isset($_SESSION['ELEMENT_SAVE_ERRORS']) AND !empty($_SESSION['ELEMENT_SAVE_ERRORS'])) {
			$errors = $_SESSION['ELEMENT_SAVE_ERRORS'];
			unset($_SESSION['ELEMENT_SAVE_ERRORS']);

			return $errors;
		}
		else {
			return false;
		}
	}

	/**
	 * @return bool
     *
	 * @api
	 */
	protected function getNotes()
	{
		if (isset($_SESSION['ELEMENT_SAVE_NOTES']) AND !empty($_SESSION['ELEMENT_SAVE_NOTES'])) {
			$notes = $_SESSION['ELEMENT_SAVE_NOTES'];
			unset($_SESSION['ELEMENT_SAVE_NOTES']);

			return $notes;
		}
		else {
			return false;
		}
	}

	/**
	 * Возвращает класс хелпера нужного типа из всех зарегистрированных хелперов в модуле и находящихся
	 * в том же неймспейсе что класс хелпера из которого вызван этот метод
	 *
	 * Под типом понимается ближайший родитель из модуля AdminHelper.
	 *
	 * Например если нам нужно получить ListHelper для формирования ссылки на список из EditHelper,
	 * то это будет вглядеть так $listHelperClass = static::getHelperClass(AdminListHelper::getClass())
	 *
	 * @param $class
     *
	 * @return string|bool
	 */
	public function getHelperClass($class)
	{
		$interfaceSettings = self::$interfaceSettings[static::getModule()];

		foreach ($interfaceSettings as $viewName => $settings) {
			$parentClasses = class_parents($settings['helper']);
			array_pop($parentClasses); // AdminBaseHelper

			$parentClass = array_pop($parentClasses);
			$thirdClass = array_pop($parentClasses);

			if (in_array($thirdClass, array(AdminSectionListHelper::className(), AdminSectionEditHelper::className()))) {
				$parentClass = $thirdClass;
			}

			if ($parentClass == $class && class_exists($settings['helper'])) {
				$helperClassParts = explode('\\', $settings['helper']);
				array_pop($helperClassParts);
				$helperNamespace = implode('\\', $helperClassParts);

				$сlassParts = explode('\\', get_called_class());
				array_pop($сlassParts);
				$classNamespace = implode('\\', $сlassParts);

				if ($helperNamespace == $classNamespace) {
					return $settings['helper'];
				}
			}
		}

		return false;
	}

	/**
	 * Возвращает относительный namespace до хелперов в виде URL параметра.
     *
	 * @return string
	 */
	public static function getEntityCode()
	{
		$namespaceParts = explode('\\', get_called_class());
		array_pop($namespaceParts);
		array_shift($namespaceParts);
		array_shift($namespaceParts);

		if (end($namespaceParts) == 'AdminInterface') {
			array_pop($namespaceParts);
		}

		return str_replace(
			'\\',
			'_',
			implode(
				'\\',
				array_map('lcfirst', $namespaceParts)
			)
		);
	}

	/**
	 * Возвращает URL страницы редактирования класса данного представления.
     *
	 * @param array $params
	 *
     * @return string
	 *
     * @api
	 */
	public static function getEditPageURL($params = array())
	{
		$editHelperClass = str_replace('List', 'Edit', get_called_class());
		if (empty(static::$editViewName) && class_exists($editHelperClass)) {
			return $editHelperClass::getViewURL($editHelperClass::getViewName(), static::$editPageUrl, $params);
		}
		else {
			return static::getViewURL(static::$editViewName, static::$editPageUrl, $params);
		}
	}

	/**
	 * Возвращает URL страницы редактирования класса данного представления.
     *
	 * @param array $params
	 *
     * @return string
	 *
     * @api
	 */
	public static function getSectionsEditPageURL($params = array())
	{
		$sectionEditHelperClass = str_replace('List', 'SectionsEdit', get_called_class());

        if (empty(static::$sectionsEditViewName) && class_exists($sectionEditHelperClass)) {
			return $sectionEditHelperClass::getViewURL($sectionEditHelperClass::getViewName(), static::$sectionsEditPageUrl, $params);
		}
		else {
			return static::getViewURL(static::$sectionsEditViewName, static::$sectionsEditPageUrl, $params);
		}
	}

	/**
	 * Возвращает URL страницы списка класса данного представления.
     *
	 * @param array $params
	 *
     * @return string
	 *
     * @api
	 */
	public static function getListPageURL($params = array())
	{
		$listHelperClass = str_replace('Edit', 'List', get_called_class());

        if (empty(static::$listViewName) && class_exists($listHelperClass)) {
			return $listHelperClass::getViewURL($listHelperClass::getViewName(), static::$listPageUrl, $params);
		}
		else {
			return static::getViewURL(static::$listViewName, static::$listPageUrl, $params);
		}
	}

	/**
	 * Получает URL для указанного представления
	 *
	 * @param string $viewName Название представления.
	 * @param string $defaultURL Позволяет указать URL напрямую. Если указано, то будет использовано это значение.
	 * @param array $params Дополнительные query-параметры в URL.
     *
	 * @return string
	 *
     * @internal
	 */
	public static function getViewURL($viewName, $defaultURL, $params = array())
	{
		$params['entity'] = static::getEntityCode();

		if (isset($defaultURL)) {
			$url = $defaultURL . "?lang=" . LANGUAGE_ID;
		}
		else {
			$url = static::getRouterURL() . '?lang=' . LANGUAGE_ID . '&module=' . static::getModule() . '&view=' . $viewName;
		}

		if (!empty($params)) {
			unset($params['lang']);
			unset($params['module']);
			unset($params['view']);

			$query = http_build_query($params);
			$url .= '&' . $query;
		}

		return $url;
	}

	/**
	 * Возвращает адрес обработчика запросов к админ. интерфейсу.
	 *
     * @return string
	 *
     * @api
	 */
	public static function getRouterURL()
	{
		return static::$routerUrl;
	}

    /**
     * Возвращает URL страницы с хелпером. Как правило, метод вызывается при генерации административного
     * меню (`menu.php`).
     *
     * @param array $params Дополнительные GET-параметры для подстановки в URL.
     *
     * @return string
     */
	public static function getUrl(array $params = array())
	{
		return static::getViewURL(static::getViewName(), null, $params);
	}

	/**
	 * Получает виджет для текущего поля, выполняет базовую инициализацию.
	 *
	 * @param string $code Ключ поля для данного виджета (должен быть в массиве $data).
	 * @param array $data Данные объекта в виде массива.
	 *
     * @return bool|\DigitalWand\AdminHelper\Widget\HelperWidget
     *
	 * @throws \DigitalWand\AdminHelper\Helper\Exception
	 *
     * @internal
	 */
	public function createWidgetForField($code, &$data = array())
	{
		if (!isset($this->fields[$code]['WIDGET'])) {
			$error = str_replace('#CODE#', $code, 'Can\'t create widget for the code "#CODE#"');
			throw new Exception($error, Exception::CODE_NO_WIDGET);
		}

		/** @var HelperWidget $widget */
		$widget = $this->fields[$code]['WIDGET'];

		$widget->setHelper($this);
		$widget->setCode($code);
		$widget->setData($data);
		$widget->setEntityName($this->getModel());

		$this->onCreateWidgetForField($widget, $data);

		if (!$this->hasWriteRightsElement($data)) {
			$widget->setSetting('READONLY', true);
		}

		return $widget;
	}

	/**
	 * Метод вызывается при создании виджета для текущего поля. Может быть использован для изменения настроек виджета
     * на основе передаваемых данных.
	 *
	 * @param \DigitalWand\AdminHelper\Widget\HelperWidget $widget
	 * @param array $data
	 */
	protected function onCreateWidgetForField(&$widget, $data = array())
	{
	}

	/**
	 * Если класс не объявлен, то битрикс генерирует новый класс в рантайме. Если класс уже есть, то возвращаем имя
     * как есть.
	 *
	 * @param $className
	 * @return \Bitrix\Highloadblock\DataManager
	 *
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\SystemException
	 * @throws Exception
	 */
	public static function getHLEntity($className)
	{
		if (!class_exists($className)) {
			$info = static::getHLEntityInfo($className);

			if ($info) {
				$entity = HL\HighloadBlockTable::compileEntity($info);

				return $entity->getDataClass();
			}
			else {
				$error = Loc::getMessage('DIGITALWAND_ADMIN_HELPER_GETMODEL_EXCEPTION', array('#CLASS#' => $className));
				$exception = new Exception($error, Exception::CODE_NO_HL_ENTITY_INFORMATION);

				throw $exception;
			}
		}

		return $className;
	}

	/**
	 * Получает запись из БД с информацией об HL.
	 *
	 * @param string $className Название класса, обязательно без Table в конце и без указания неймспейса.
     *
	 * @return array|false
	 *
     * @throws \Bitrix\Main\ArgumentException
	 */
	public static function getHLEntityInfo($className)
	{
		$className = str_replace('\\', '', $className);
		$pos = strripos($className, 'Table', -5);

        if ($pos !== false) {
			$className = substr($className, 0, $pos);
		}

        $parameters = array(
			'filter' => array(
				'NAME' => $className,
			),
			'limit' => 1
		);

		return HL\HighloadBlockTable::getList($parameters)->fetch();
	}

	/**
	 * Отобразить страницу 404 ошибка
	 */
	protected function show404()
	{
		// инициализация глобальных переменных, необходимых для вывода страницы административного раздела в
		// текущей области видимости
		global $APPLICATION, $adminPage, $adminMenu, $USER;
		\CHTTP::SetStatus(404);
		include $_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/admin/404.php';
		die();
	}

	/**
	 * Выставляет текущий контекст исполнения.
     *
	 * @param $context
	 *
     * @see $context
	 */
	protected function setContext($context)
	{
		$this->context = $context;
	}

	public function getContext()
	{
		return $this->context;
	}

	public static function className()
	{
		return get_called_class();
	}
}