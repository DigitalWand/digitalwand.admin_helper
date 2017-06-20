<?php

namespace DigitalWand\AdminHelper\Helper;

use Bitrix\Main\Context;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\DB\Result;
use DigitalWand\AdminHelper\EntityManager;

Loc::loadMessages(__FILE__);

/**
 * Базовый класс для реализации страницы списка админки.
 * При создании своего класса необходимо переопределить следующие переменные:
 * <ul>
 * <li> static protected $model </Li>
 * </ul>
 *
 * Этого будет дастаточно для получения минимальной функциональности
 * Также данный класс может использоваться для отображения всплывающих окон с возможностью выбора элемента из списка
 *
 * @see AdminBaseHelper::$model
 * @see AdminBaseHelper::$module
 * @see AdminBaseHelper::$editViewName
 * @see AdminBaseHelper::$viewName
 * @package AdminHelper
 *
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 * @author Artem Yarygin <artx19@yandex.ru>
 */
abstract class AdminListHelper extends AdminBaseHelper
{
	const OP_GROUP_ACTION = 'AdminListHelper::__construct_groupAction';
	const OP_ADMIN_VARIABLES_FILTER = 'AdminListHelper::prepareAdminVariables_filter';
	const OP_ADMIN_VARIABLES_HEADER = 'AdminListHelper::prepareAdminVariables_header';
	const OP_GET_DATA_BEFORE = 'AdminListHelper::getData_before';
	const OP_ADD_ROW_CELL = 'AdminListHelper::addRowCell';
	const OP_CREATE_FILTER_FORM = 'AdminListHelper::createFilterForm';
	const OP_CHECK_FILTER = 'AdminListHelper::checkFilter';
	const OP_EDIT_ACTION = 'AdminListHelper::editAction';

	/**
	 * @var bool
	 * Выводить кнопку экспорта в Excel
	 * @api
	 */
	protected $exportExcel = true;
	/**
	 * @var bool
	 * Выводить в списке кол-во элементов пункт Все
	 */
	protected $showAll = true;
	/**
	 * @var bool
	 * Является ли список всплывающим окном для выбора элементов из списка.
	 * В этой версии не должно быть операций удаления/перехода к редактированию.
	 */
	protected $isPopup = false;
	/**
	 * @var string
	 * Название поля, в котором хранится результат выбора во всплывающем окне
	 */
	protected $fieldPopupResultName = '';
	/**
	 * @var string
	 * Уникальный индекс поля, в котором хранится результат выбора во всплывающем окне
	 */
	protected $fieldPopupResultIndex = '';
	protected $sectionFields = array();
	/**
	 * @var string
	 * Название столбца, в котором хранится название элемента
	 */
	protected $fieldPopupResultElTitle = '';
	/**
	 * @var string
	 * Название функции, вызываемой при даблклике на строке списка, в случае, если список выводится в режиме
	 *     всплывающего окна
	 */
	protected $popupClickFunctionName = 'selectRow';
	/**
	 * @var string
	 * Код функции, вызываемой при клике на строке списка
	 * @see AdminListHelper::genPipupActionJS()
	 */
	protected $popupClickFunctionCode;
	/**
	 * @var array
	 * Массив с заголовками таблицы
	 * @see \CAdminList::AddHeaders()
	 */
	protected $arHeader = array();
	/**
	 * @var array
	 * параметры фильтрации списка в классическим битриксовом формате
	 */
	protected $arFilter = array();
	/**
	 * @var array
	 * Массив, хранящий тип фильтра для данного поля. Позволяет избежать лишнего парсинга строк.
	 */
	protected $filterTypes = array();
	/**
	 * @var array
	 * Поля, предназначенные для фильтрации
	 * @see \CAdminList::InitFilter();
	 */
	protected $arFilterFields = array();
	/**
	 * Список полей, для которых доступна фильтрация
	 * @var array
	 * @see \CAdminFilter::__construct();
	 */
	protected $arFilterOpts = array();
	/**
	 * @var \CAdminList
	 */
	protected $list;
	/**
	 * @var string
	 * Префикс таблицы. Нужен, чтобы обеспечить уникальность относительно других админ. интерфейсов.
	 * Без его добавления к конструктору таблицы повычается вероятность, что возникнет конфликт с таблицей из другого
	 * административного интерфейса, в результате чего неправильно будет работать паджинация, фильтрация. Вероятны
	 * ошибки запросов к БД.
	 */
	static protected $tablePrefix = "digitalwand_admin_helper_";
	/**
	 * @var array
	 * @see \CAdminList::AddGroupActionTable()
	 */
	protected $groupActionsParams = array();
	/**
	 * Текущие параметры пагинации,
	 * требуются для составления смешанного списка разделов и элементов
	 * @var array
	 */
	protected $navParams = array();
	/**
	 * Количество элементов смешанном списке
	 * @see AdminListHelper::CustomNavStart
	 * @var int
	 */
	protected $totalRowsCount = 0;
	/**
	 * Массив для слияния столбцов элементов и разделов
	 * @var array
	 */
	protected $tableColumnsMap = array();
	/**
	 * @var string
	 * HTML верхней части таблицы
	 * @api
	 */
	public $prologHtml;

	/**
	 * @var string
	 * HTML нижней части таблицы
	 * @api
	 */
	public $epilogHtml;

	/**
	 * Производится инициализация переменных, обработка запросов на редактирование
	 *
	 * @param array $fields
	 * @param bool $isPopup
	 * @throws \Bitrix\Main\ArgumentException
	 */
	public function __construct(array $fields, $isPopup = false)
	{
		$this->isPopup = $isPopup;

		if ($this->isPopup) {
			$this->fieldPopupResultName = preg_replace("/[^a-zA-Z0-9_:\\[\\]]/", "", $_REQUEST['n']);
			$this->fieldPopupResultIndex = preg_replace("/[^a-zA-Z0-9_:]/", "", $_REQUEST['k']);
			$this->fieldPopupResultElTitle = $_REQUEST['eltitle'];
		}

		parent::__construct($fields);

		$this->restoreLastGetQuery();
		$this->prepareAdminVariables();

		$className = static::getModel();
		$oSort = $this->initSortingParameters(Context::getCurrent()->getRequest());
		$this->list = new \CAdminList($this->getListTableID(), $oSort);
		$this->list->InitFilter($this->arFilterFields);

		if ($this->list->EditAction() AND $this->hasWriteRights()) {
			global $FIELDS;
			foreach ($FIELDS as $id => $fields) {
				if (!$this->list->IsUpdated($id)) {
					continue;
				}
				$this->editAction($id, $fields);
			}
		}
		if ($IDs = $this->list->GroupAction() AND $this->hasWriteRights()) {
			if ($_REQUEST['action_target'] == 'selected') {
				$this->setContext(AdminListHelper::OP_GROUP_ACTION);
				$IDs = array();

				//Текущий фильтр должен быть модифицирован виждтами
				//для соответствия результатов фильтрации тому, что видит пользователь в интерфейсе.
				$raw = array(
					'SELECT' => $this->pk(),
					'FILTER' => $this->arFilter,
					'SORT' => array()
				);

				foreach ($this->fields as $code => $settings) {
					$widget = $this->createWidgetForField($code);
					$widget->changeGetListOptions($this->arFilter, $raw['SELECT'], $raw['SORT'], $raw);
				}

				$res = $className::getList(array(
					'filter' => $this->arFilter,
					'select' => array($this->pk()),
				));

				while ($el = $res->Fetch()) {
					$IDs[] = $el[$this->pk()];
				}
			}

			$filteredIDs = array();

			foreach ($IDs as $id) {
				if (strlen($id) <= 0) {
					continue;
				}
				$filteredIDs[] = IntVal($id);
			}
			$this->groupActions($IDs, $_REQUEST['action']);
		}

		if (isset($_REQUEST['action']) || isset($_REQUEST['action_button']) && count($this->getErrors()) == 0) {
			$listHelperClass = $this->getHelperClass(AdminListHelper::className());
			$id = isset($_GET['ID']) ? $_GET['ID'] : null;
			$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : $_REQUEST['action_button'];
			if ($action != 'edit' && $_REQUEST['cancel'] != 'Y') {
				$params = $_GET;
				unset($params['action']);
				unset($params['action_button']);
				$this->customActions($action, $id);
				LocalRedirect($listHelperClass::getUrl($params));
			}
		}

		if ($this->isPopup()) {
			$this->genPopupActionJS();
		}

		// Получаем параметры навигации
		$navUniqSettings = array(
			'nPageSize' => 20,
			'sNavID' => $this->getListTableID()
		);
		$this->navParams = array(
			'nPageSize' => \CAdminResult::GetNavSize($this->getListTableID(), $navUniqSettings),
			'navParams' => \CAdminResult::GetNavParams($navUniqSettings)
		);
	}

	/**
	 * Инициализирует параметры сортировки на основании запроса
	 * @return \CAdminSorting
	 */
	protected function initSortingParameters(HttpRequest $request)
	{
		$sortByParameter = 'by';
		$sortOrderParameter = 'order';

		$sortBy = $request->get($sortByParameter);
		$sortBy = $sortBy ?: static::pk();

		$sortOrder = $request->get($sortOrderParameter);
		$sortOrder = $sortOrder ?: 'desc';

		return new \CAdminSorting($this->getListTableID(), $sortBy, $sortOrder, $sortByParameter, $sortOrderParameter);
	}

	/**
	 * Подготавливает переменные, используемые для инициализации списка.
	 *
	 * - добавляет поля в список фильтра только если FILTER не задано false по умолчанию для виджета и поле не является
	 * полем связи сущностью разделов
	 */
	protected function prepareAdminVariables()
	{
		$this->arHeader = array();
		$this->arFilter = array();
		$this->arFilterFields = array();
		$arFilter = array();
		$this->filterTypes = array();
		$this->arFilterOpts = array();

		$sectionField = static::getSectionField();

		foreach ($this->fields as $code => $settings) {
			$widget = $this->createWidgetForField($code);

			if (
				($sectionField != $code && $widget->getSettings('FILTER') !==false)
				&&
				((isset($settings['FILTER']) AND $settings['FILTER'] != false) OR !isset($settings['FILTER']))
			) {

				$this->setContext(AdminListHelper::OP_ADMIN_VARIABLES_FILTER);
				$filterVarName = 'find_' . $code;
				$this->arFilterFields[] = $filterVarName;
				$filterType = '';

				if (is_string($settings['FILTER'])) {
					$filterType = $settings['FILTER'];
				}

				if (isset($_REQUEST[$filterVarName])
					AND !isset($_REQUEST['del_filter'])
					AND $_REQUEST['del_filter'] != 'Y'
				) {
					$arFilter[$filterType . $code] = $_REQUEST[$filterVarName];
					$this->filterTypes[$code] = $filterType;
				}

				$this->arFilterOpts[$code] = $widget->getSettings('TITLE');
			}

			if (!isset($settings['LIST']) || $settings['LIST'] === true) {
				$this->setContext(AdminListHelper::OP_ADMIN_VARIABLES_HEADER);
				$mergedColumn = false;
				// проверяем есть ли столбец раздела с таким названием
				if ($widget->getSettings('LIST_TITLE')) {
					$sectionHeader = $this->getSectionsHeader();
					foreach ($sectionHeader as $sectionColumn) {
						if ($sectionColumn['content'] == $widget->getSettings('LIST_TITLE')) {
							// добавляем столбец элементов в карту столбцов
							$this->tableColumnsMap[$code] = $sectionColumn['id'];
							$mergedColumn = true;
							break;
						}
					}
				}
				if (!$mergedColumn) {
					$this->arHeader[] = array(
						"id" => $code,
						"content" => $widget->getSettings('LIST_TITLE') ? $widget->getSettings('LIST_TITLE') : $widget->getSettings('TITLE'),
						"sort" => $code,
						"default" => !isset($settings['HEADER']) || $settings['HEADER'] === true,
						'admin_list_helper_sort' => $widget->getSettings('LIST_COLUMN_SORT') ? $widget->getSettings('LIST_COLUMN_SORT') : 100
					);
				}
			}
		}

		if ($this->checkFilter($arFilter)) {
			$this->arFilter = $arFilter;
		}

		if (static::getHelperClass(AdminSectionEditHelper::className())) {
			$this->arFilter[static::getSectionField()] = $_GET['ID'];
		}
	}

	/**
	 * Возвращает список столбцов для разделов
	 * @return array
	 */
	public function getSectionsHeader()
	{
		$arSectionsHeaders = array();
		$sectionHelper = static::getHelperClass(AdminSectionEditHelper::className());
		$sectionsInterfaceSettings = static::getInterfaceSettings($sectionHelper::getViewName());
		$this->sectionFields = $sectionsInterfaceSettings['FIELDS'];

		foreach ($sectionsInterfaceSettings['FIELDS'] as $code => $settings) {

			if (!isset($settings['LIST']) || $settings['LIST'] === true) {
				$arSectionsHeaders[] = array(
					"id" => $code,
					"content" => isset($settings['LIST_TITLE']) ? $settings['LIST_TITLE'] : $settings['TITLE'],
					"sort" => $code,
					"default" => !isset($settings['HEADER']) || $settings['HEADER'] === true,
					'admin_list_helper_sort' => isset($settings['LIST_COLUMN_SORT']) ? $settings['LIST_COLUMN_SORT'] : 100
				);
			}
			unset($settings['WIDGET']);

			foreach ($settings as $c => $v) {
				$sectionsInterfaceSettings['FIELDS'][$code]['WIDGET']->setSetting($c, $v);
			}
		}

		return $arSectionsHeaders;
	}

	/**
	 * Производит проверку корректности данных (в массиве $_REQUEST), переданных в фильтр
	 * @TODO: нужно сделать вывод сообщений об ошибке фильтрации.
	 * @param $arFilter
	 * @return bool
	 */
	protected function checkFilter($arFilter)
	{
		$this->setContext(AdminListHelper::OP_CHECK_FILTER);
		$filterValidationErrors = array();
		foreach ($this->filterTypes as $code => $type) {
			$widget = $this->createWidgetForField($code);
			$value = $arFilter[$type . $code];
			if (!$widget->checkFilter($type, $value)) {
				$filterValidationErrors = array_merge($filterValidationErrors,
					$widget->getValidationErrors());
			}
		}

		return empty($filterValidationErrors);
	}

	/**
	 * Подготавливает массив с настройками контекстного меню. По-умолчанию добавлена кнопка "создать элемент".
	 *
	 * @see $contextMenu
	 *
	 * @api
	 */
	protected function getContextMenu()
	{
		$contextMenu = array();
		/** @var AdminSectionEditHelper $sectionEditHelper */
		$sectionEditHelper = static::getHelperClass(AdminSectionEditHelper::className());
		if ($sectionEditHelper) {
			$sectionId = $_GET['SECTION_ID'] ?: $_GET['ID'] ?: null;
			$this->additionalUrlParams['SECTION_ID'] = $sectionId = $sectionId > 0 ? (int)$sectionId : null;
		}

		/**
		 * Если задан для разделов добавляем кнопку создать раздел и
		 * кнопку на уровень вверх если это не корневой раздел
		 */
		if (isset($sectionId)) {
			$params = $this->additionalUrlParams;
			$sectionModel = $sectionEditHelper::getModel();
			$sectionField = $sectionEditHelper::getSectionField();
			$section = $sectionModel::getById(
				$this->getCommonPrimaryFilterById($sectionModel, null, $sectionId)
			)->Fetch();
			if ($this->isPopup()) {
				$params = array_merge($_GET);
			}
			if ($section[$sectionField]) {
				$params['ID'] = $section[$sectionField];
			}
			else {
				unset($params['ID']);
			}
			unset($params['SECTION_ID']);
			$contextMenu[] = $this->getButton('LIST_SECTION_UP', array(
				'LINK' => static::getUrl($params),
				'ICON' => 'btn_list'
			));
		}

		/**
		 * Добавляем кнопку создать элемент и создать раздел
		 */
		if (!$this->isPopup() && $this->hasWriteRights()) {
			$editHelperClass = static::getHelperClass(AdminEditHelper::className());
			if ($editHelperClass) {
				$contextMenu[] = $this->getButton('LIST_CREATE_NEW', array(
					'LINK' => $editHelperClass::getUrl($this->additionalUrlParams),
					'ICON' => 'btn_new'
				));
			}
			$sectionsHelperClass = static::getHelperClass(AdminSectionEditHelper::className());
			if ($sectionsHelperClass) {
				$contextMenu[] = $this->getButton('LIST_CREATE_NEW_SECTION', array(
					'LINK' => $sectionsHelperClass::getUrl($this->additionalUrlParams),
					'ICON' => 'btn_new'
				));
			}
		}

		return $contextMenu;
	}

	/**
	 * Возвращает массив с настройками групповых действий над списком.
	 *
	 * @return array
	 *
	 * @api
	 */
	protected function getGroupActions()
	{
		$result = array();

		if (!$this->isPopup()) {
			if ($this->hasDeleteRights()) {
				$result = array('delete' => Loc::getMessage("DIGITALWAND_ADMIN_HELPER_LIST_DELETE"));
			}
		}

		return $result;
	}

	/**
	 * Обработчик групповых операций. По-умолчанию прописаны операции активации / деактивации и удаления.
	 *
	 * @param array $IDs
	 * @param string $action
	 *
	 * @api
	 */
	protected function groupActions($IDs, $action)
	{
		$sectionEditHelperClass = $this->getHelperClass(AdminSectionEditHelper::className());
		$listHelperClass = $this->getHelperClass(AdminListHelper::className());

		$className = static::getModel();
		if (isset($_REQUEST['model'])) {
			$className = $_REQUEST['model'];
		}

		if ($sectionEditHelperClass && !isset($_REQUEST['model-section'])) {
			$sectionClassName = $sectionEditHelperClass::getModel();
		}
		else {
			$sectionClassName = $_REQUEST['model-section'];
		}

		if ($action == 'delete') {
			if ($this->hasDeleteRights()) {
				$complexPrimaryKey = is_array($className::getEntity()->getPrimary());
				if ($complexPrimaryKey) {
					$IDs = $this->getIds();
				}

				// ищем правильный урл для перехода
				if (!empty($IDs[0])) {

					$id = $complexPrimaryKey ? $IDs[0][$this->pk()] : $IDs[0];
					$model = $className;

					if (strpos($id, 's') === 0) {
						$model = $sectionClassName;
						$listHelper = $this->getHelperClass(AdminSectionListHelper::className());
						if (!$listHelper) {
							$this->addErrors(Loc::getMessage('DIGITALWAND_ADMIN_HELPER_LIST_SECTION_HELPER_NOT_FOUND'));
							unset($_GET['ID']);
							return;
						}
						$id = substr($id, 1);
					} else {
						$listHelper = $listHelperClass;
					}

					if ($listHelper) {
						$id = $this->getCommonPrimaryFilterById($model, null, $id);
						$element = $model::getById($id)->Fetch();
						$sectionField = $listHelper::getSectionField();
						if ($element[$sectionField]) {
							$_GET[$this->pk()] = $element[$sectionField];
						} else {
							unset($_GET['ID']);
						}
					}
				}

				foreach ($IDs as $id) {
					$model = $className;
					$id = $complexPrimaryKey ? $id[$this->pk()] : $id;
					if (strpos($id, 's') === 0) {
						$model = $sectionClassName;
						$id = substr($id, 1);
					}
					/** @var EntityManager $entityManager */
					$entityManager = new static::$entityManager($model, empty($this->data) ? array() : $this->data, $id,
						$this);
					$result = $entityManager->delete();
					$this->addNotes($entityManager->getNotes());
					if (!$result->isSuccess()) {
						$this->addErrors($result->getErrorMessages());
						break;
					}
				}
			}
			else {
				$this->addErrors(Loc::getMessage('DIGITALWAND_ADMIN_HELPER_LIST_DELETE_FORBIDDEN'));
			}
		}

		if ($action == 'delete-section') {
			if ($this->hasDeleteRights()) {

				// ищем правильный урл для перехода
				if (!empty($IDs[0])) {
					$id = $this->getCommonPrimaryFilterById($sectionClassName, null, $IDs[0]);
					$sectionListHelperClass = $this->getHelperClass(AdminSectionListHelper::className());
					if ($sectionListHelperClass) {
						$element = $sectionClassName::getById($id)->Fetch();
						$sectionField = $sectionListHelperClass::getSectionField();
						if ($element[$sectionField]) {
							$_GET[$this->pk()] = $element[$sectionField];
						} else {
							unset($_GET['ID']);
						}
					} else {
						unset($_GET['ID']);
						$this->addErrors(Loc::getMessage('DIGITALWAND_ADMIN_HELPER_LIST_SECTION_HELPER_NOT_FOUND'));
						return;
					}
				}

				foreach ($IDs as $id) {
					$id = $this->getCommonPrimaryFilterById($sectionClassName, null, $id);
					$entityManager = new static::$entityManager($sectionClassName, array(), $id, $this);
					$result = $entityManager->delete();
					$this->addNotes($entityManager->getNotes());
					if(!$result->isSuccess()){
						$this->addErrors($result->getErrorMessages());
						break;
					}
				}
			}
			else {
				$this->addErrors(Loc::getMessage('DIGITALWAND_ADMIN_HELPER_LIST_DELETE_FORBIDDEN'));
			}
		}
	}

	/**
	 * Сохранение полей для отной записи, отредактированной в списке.
	 * Этапы:
	 * <ul>
	 * <li> Выборка элемента по ID, чтобы удостовериться, что он существует. В противном случае  возвращается
	 * ошибка</li>
	 * <li> Создание виджета для каждой ячейки, валидация значений поля</li>
	 * <li> TODO: вывод ошибок валидации</li>
	 * <li> Сохранение записи</li>
	 * <li> Вывод ошибок сохранения, если таковые появились</li>
	 * <li> Модификация данных сроки виджетами.</li>
	 * </ul>
	 *
	 * @param int $id ID записи в БД
	 * @param array $fields Поля с изменениями
	 *
	 * @see HelperWidget::processEditAction();
	 * @see HelperWidget::processAfterSaveAction();
	 */
	protected function editAction($id, $fields)
	{
		$this->setContext(AdminListHelper::OP_EDIT_ACTION);
		if(strpos($id, 's')===0){ // для раделов другой класс модели
			$editHelperClass = $this->getHelperClass(AdminSectionEditHelper::className());
			$sectionsInterfaceSettings = static::getInterfaceSettings($editHelperClass::getViewName());
			$className = $editHelperClass::getModel();
			$id = str_replace('s','',$id);
		}else{
			$className = static::getModel();
			$sectionsInterfaceSettings = false;
		}

		$idForLog = $id;
		$complexPrimaryKey = is_array($className::getEntity()->getPrimary());
		if ($complexPrimaryKey) {
			$oldRequest = $_REQUEST;
			$_REQUEST = array($this->pk() => $id);
			$id = $this->getCommonPrimaryFilterById($className, null, $id);
			$idForLog = json_encode($id);
			$_REQUEST = $oldRequest;
		}

		$el = $className::getById($id);
		if ($el->getSelectedRowsCount() == 0) {
			$this->list->AddGroupError(Loc::getMessage("MAIN_ADMIN_SAVE_ERROR"), $idForLog);
			return;
		}

		// замена кодов для столбцов элементов соединенных со столбцами разделов
		if($sectionsInterfaceSettings==false){
			$tableColumnsMap = array_flip($this->tableColumnsMap);
			$replacedFields = array();
			foreach($fields as $key => $value){
				if(!empty($tableColumnsMap[$key])) {
					$key = $tableColumnsMap[$key];
				}
				$replacedFields[$key] = $value;
			}
			$fields = $replacedFields;
		}

		$allWidgets = array();
		foreach ($fields as $key => $value) {
			if($sectionsInterfaceSettings!==false){ // для разделов свои виджеты
				$widget = $sectionsInterfaceSettings['FIELDS'][$key]['WIDGET'];
			}else{
				$widget = $this->createWidgetForField($key, $fields); // для элементов свои
			}

			$widget->processEditAction();
			$this->validationErrors = array_merge($this->validationErrors, $widget->getValidationErrors());
			$allWidgets[] = $widget;
		}
		//FIXME: может, надо добавить вывод ошибок ДО сохранения?..
		$this->addErrors($this->validationErrors);

		$result = $className::update($id, $fields);
		$errors = $result->getErrorMessages();
		if (empty($this->validationErrors) AND !empty($errors)) {
			$fieldList = implode("\n", $errors);
			$this->list->AddGroupError(Loc::getMessage("MAIN_ADMIN_SAVE_ERROR") . " " . $fieldList, $idForLog);
		}

		if (!empty($errors)) {
			foreach ($allWidgets as $widget) {
				/** @var \DigitalWand\AdminHelper\Widget\HelperWidget $widget */
				$widget->setData($fields);
				$widget->processAfterSaveAction();
			}
		}
	}

	/**
	 * Является ли список всплывающим окном для выбора элементов из списка.
	 * В этой версии не должно быть операций удаления/перехода к редактированию.
	 *
	 * @return boolean
	 */
	public function isPopup()
	{
		return $this->isPopup;
	}

	/**
	 * Функция определяет js-функцию для двойонго клика по строке.
	 * Вызывается в том случае, если окно открыто в режиме попапа.
	 *
	 * @api
	 */
	protected function genPopupActionJS()
	{
		$this->popupClickFunctionCode = '<script>
			function ' . $this->popupClickFunctionName . '(data){
				var input = window.opener.document.getElementById("' . $this->fieldPopupResultName . '[' . $this->fieldPopupResultIndex . ']");
				if(!input)
					input = window.opener.document.getElementById("' . $this->fieldPopupResultName . '");
				if(input)
				{
					input.value = data.ID;
					if (window.opener.BX)
						window.opener.BX.fireEvent(input, "change");
				}
				var span = window.opener.document.getElementById("sp_' . md5($this->fieldPopupResultName) . '_' . $this->fieldPopupResultIndex . '");
				if(!span)
					span = window.opener.document.getElementById("sp_' . $this->fieldPopupResultName . '");
				if(!span)
					span = window.opener.document.getElementById("' . $this->fieldPopupResultName . '_link");
				if(span)
					span.innerHTML = data["' . $this->fieldPopupResultElTitle . '"];
				window.close();
			}
		</script>';
	}

	/**
	 * Основной цикл отображения списка. Этапы:
	 * <ul>
	 * <li> Вывод заголовков страницы </li>
	 * <li> Определение списка видимых колонок и колонок, участвующих в выборке. </li>
	 * <li> Создание виджета для каждого поля выборки </li>
	 * <li> Модификация параметров запроса каждым из виджетов </li>
	 * <li> Выборка данных </li>
	 * <li> Вывод строк таблицы. Во время итерации по строкам возможна модификация данных строки. </li>
	 * <li> Отрисовка футера таблицы, добавление контекстного меню </li>
	 * </ul>
	 *
	 * @param array $sort Настройки сортировки.
	 *
	 * @see AdminListHelper::getList();
	 * @see AdminListHelper::getMixedData();
	 * @see AdminListHelper::modifyRowData();
	 * @see AdminListHelper::addRowCell();
	 * @see AdminListHelper::addRow();
	 * @see HelperWidget::changeGetListOptions();
	 */
	public function buildList($sort)
	{
		$this->setContext(AdminListHelper::OP_GET_DATA_BEFORE);

		$headers = $this->arHeader;

		$sectionEditHelper = static::getHelperClass(AdminSectionEditHelper::className());

		if ($sectionEditHelper) { // если есть реализация класса AdminSectionEditHelper, значит используются разделы
			$sectionHeaders = $this->getSectionsHeader();
			foreach ($sectionHeaders as $sectionHeader) {
				$found = false;
				foreach ($headers as $i => $elementHeader) {
					if ($sectionHeader['content'] == $elementHeader['content'] || $sectionHeader['id'] == $elementHeader['id']) {
						if (!$elementHeader['default'] && $sectionHeader['default']) {
							$headers[$i] = $sectionHeader;
						} else {
							$found = true;
						}
						break;
					}
				}
				if (!$found) {
					$headers[] = $sectionHeader;
				}
			}
		}

		// сортировка столбцов с сохранением исходной позиции в
		// массиве для развнозначных элементов
		// массив $headers модифицируется
		$this->mergeSortHeader($headers);

		$this->list->AddHeaders($headers);
		$visibleColumns = $this->list->GetVisibleHeaderColumns();

		$modelClass = $this->getModel();
		$elementFields = array_keys($modelClass::getEntity()->getFields());

		if ($sectionEditHelper) {
			$sectionsVisibleColumns = array();
			foreach ($visibleColumns as $k => $v) {
				if (isset($this->sectionFields[$v])) {
					if(!in_array($v, $elementFields)){
						unset($visibleColumns[$k]);
					}
					if (!isset($this->sectionFields[$v]['LIST']) || $this->sectionFields[$v]['LIST'] !== false) {
						$sectionsVisibleColumns[] = $v;
					}
				}
			}
			$visibleColumns = array_values($visibleColumns);
			$visibleColumns = array_merge($visibleColumns, array_keys($this->tableColumnsMap));
		}

		$className = static::getModel();
		$visibleColumns[] = $this->pk();
		$sectionsVisibleColumns[] = $this->sectionPk();

		$raw = array(
			'SELECT' => $visibleColumns,
			'FILTER' => $this->arFilter,
			'SORT' => $sort
		);

		foreach ($this->fields as $name => $settings) {
			$key = array_search($name, $visibleColumns);
			if ((isset($settings['VIRTUAL']) AND $settings['VIRTUAL'] == true)) {
				unset($visibleColumns[$key]);
				unset($this->arFilter[$name]);
				unset($sort[$name]);
			}
			if (isset($settings['LIST']) && $settings['LIST'] === false) {
				unset($visibleColumns[$key]);
			}
			if (isset($settings['FORCE_SELECT']) AND $settings['FORCE_SELECT'] == true) {
				$visibleColumns[] = $name;
			}
		}

		$visibleColumns = array_unique($visibleColumns);
		$sectionsVisibleColumns = array_unique($sectionsVisibleColumns);

		// Поля для селекта (перевернутый массив)
		$listSelect = array_flip($visibleColumns);
		foreach ($this->fields as $code => $settings) {
            if($_REQUEST['del_filter'] !== 'Y') {
                $widget = $this->createWidgetForField($code);
                $widget->changeGetListOptions($this->arFilter, $visibleColumns, $sort, $raw);
            }
			// Множественные поля не должны быть в селекте
			if (!empty($settings['MULTIPLE'])) {
				unset($listSelect[$code]);
			}
		}
		// Поля для селекта (множественные поля отфильтрованы)
		$listSelect = array_flip($listSelect);

		if ($sectionEditHelper) // Вывод разделов и элементов в одном списке
		{
			$mixedData = $this->getMixedData($sectionsVisibleColumns, $visibleColumns, $sort, $raw);
			$res = new \CDbResult;
			$res->InitFromArray($mixedData);
			$res = new \CAdminResult($res, $this->getListTableID());
			$res->nSelectedCount = $this->totalRowsCount;
			// используем кастомный NavStart что бы определить правильное количество страниц и элементов в списке
			$this->customNavStart($res);
			$this->list->NavText($res->GetNavPrint(Loc::getMessage("PAGES")));
			while ($data = $res->NavNext(false)) {
				$this->modifyRowData($data);
				if ($data['IS_SECTION']) // для разделов своя обработка
				{
					list($link, $name) = $this->getRow($data, $this->getHelperClass(AdminSectionEditHelper::className()));
					$row = $this->list->AddRow('s' . $data[$this->pk()], $data, $link, $name);
					foreach ($this->sectionFields as $code => $settings) {
						if (in_array($code, $sectionsVisibleColumns)) {
							$this->addRowSectionCell($row, $code, $data);
						}
					}
					$row->AddActions($this->getRowActions($data, true));
				}
				else // для элементов своя
				{
					$this->modifyRowData($data);
					list($link, $name) = $this->getRow($data);
					// объединение полей элемента с полями раздела
					foreach ($this->tableColumnsMap as $elementCode => $sectionCode) {
						if (isset($data[$elementCode])) {
							$data[$sectionCode] = $data[$elementCode];
						}
					}
					$row = $this->list->AddRow($data[$this->pk()], $data, $link, $name);
					foreach ($this->fields as $code => $settings) {
						if(in_array($code, $listSelect)) {
							$this->addRowCell($row, $code, $data,
							isset($this->tableColumnsMap[$code]) ? $this->tableColumnsMap[$code] : false);
						}
					}
					$row->AddActions($this->getRowActions($data));
				}
			}
		}
		else // Обычный вывод элементов без использования разделов
		{
			$this->totalRowsCount = $className::getCount($this->getElementsFilter($this->arFilter));
			$res = $this->getData($className, $this->arFilter, $listSelect, $sort, $raw);
			$res = new \CAdminResult($res, $this->getListTableID());
			$this->customNavStart($res);
			// отключаем отображение всех элементов, если установлено св-во
			$res->bShowAll = $this->showAll;
			$this->list->NavText($res->GetNavPrint(Loc::getMessage("PAGES")));
			while ($data = $res->NavNext(false)) {
				$this->modifyRowData($data);
				list($link, $name) = $this->getRow($data);
				$row = $this->list->AddRow($data[$this->pk()], $data, $link, $name);
				foreach ($this->fields as $code => $settings) {
					if(in_array($code, $listSelect)) {
						$this->addRowCell($row, $code, $data);
					}
				}
				$row->AddActions($this->getRowActions($data));
			}
		}

		$this->list->AddFooter($this->getFooter($res));
		$this->list->AddGroupActionTable($this->getGroupActions(), $this->groupActionsParams);
		$this->list->AddAdminContextMenu($this->getContextMenu(), $this->exportExcel);

		$this->list->BeginPrologContent();
		echo $this->prologHtml;
		$this->list->EndPrologContent();

		$this->list->BeginEpilogContent();
		echo $this->epilogHtml;
		$this->list->EndEpilogContent();

		// добавляем ошибки в CAdminList для режимов list и frame
		$errors = $this->getErrors();
		if(in_array($_GET['mode'], array('list','frame')) && is_array($errors)) {
			foreach($errors as $error) {
				$this->list->addGroupError($error);
			}
		}

		$this->list->CheckListMode();
	}

	/**
	 * Функция сортировки столбцов c сохранением порядка равнозначных элементов
	 * @param $array
	 */
	protected function mergeSortHeader(&$array)
	{
		// для сортировки нужно хотя бы 2 элемента
		if (count($array) < 2) return;

		// делим массив пополам
		$halfway = count($array) / 2;
		$array1 = array_slice($array, 0, $halfway);
		$array2 = array_slice($array, $halfway);

		// реукрсивно сортируем каждую половину
		$this->mergeSortHeader($array1);
		$this->mergeSortHeader($array2);

		// если последний элемент первой половины меньше или равен первому элементу
		// второй половины, то просто соединяем массивы
		if ($this->mergeSortHeaderCompare(end($array1), $array2[0]) < 1) {
			$array = array_merge($array1, $array2);
			return;
		}

		// соединяем 2 отсортированных половины в один отсортированный массив
		$array = array();
		$ptr1 = $ptr2 = 0;
		while ($ptr1 < count($array1) && $ptr2 < count($array2)) {
			// собираем в 1 массив последовательную цепочку
			// элементов из 2-х отсортированных половинок
			if ($this->mergeSortHeaderCompare($array1[$ptr1], $array2[$ptr2]) < 1) {
				$array[] = $array1[$ptr1++];
			}
			else {
				$array[] = $array2[$ptr2++];
			}
		}

		// если в исходных массивах что-то осталось забираем в основной массив
		while ($ptr1 < count($array1)) $array[] = $array1[$ptr1++];
		while ($ptr2 < count($array2)) $array[] = $array2[$ptr2++];

		return;
	}

	/**
	 * Функция сравнения столбцов по их весу в сортировке
	 * @param $a
	 * @param $b
	 * @return int
	 */
	public function mergeSortHeaderCompare($a, $b)
	{
		$a = $a['admin_list_helper_sort'];
		$b = $b['admin_list_helper_sort'];
		if ($a == $b) {
			return 0;
		}

		return ($a < $b) ? -1 : 1;
	}

	/**
	 * Получение смешанного списка из разделов и элементов.
	 *
	 * @param $sectionsVisibleColumns
	 * @param $elementVisibleColumns
	 * @param $sort
	 * @param $raw
	 * @return array
	 */
	protected function getMixedData($sectionsVisibleColumns, $elementVisibleColumns, $sort, $raw)
	{
		/** @var AdminSectionEditHelper $sectionEditHelperClass */
		$sectionEditHelperClass = $this->getHelperClass(AdminSectionEditHelper::className());
		/** @var AdminEditHelper $elementEditHelperClass */
		$elementEditHelperClass = $this->getHelperClass(AdminEditHelper::className());
		$sectionField = $sectionEditHelperClass::getSectionField();
		$sectionId = $_GET['SECTION_ID'] ? $_GET['SECTION_ID'] : $_GET['ID'];
		$returnData = array();
		/**
		 * @var DataManager $sectionModel
		 */
		$sectionModel = $sectionEditHelperClass::getModel();
		$sectionFilter = array();

		// добавляем из фильтра те поля которые есть у разделов
		foreach ($this->arFilter as $field => $value) {
			$fieldName = $this->escapeFilterFieldName($field);

			if(!empty($this->tableColumnsMap[$fieldName])) {
				$field = str_replace($fieldName, $this->tableColumnsMap[$fieldName], $field);
				$fieldName = $this->tableColumnsMap[$fieldName];
			}

			if (isset($this->sectionFields[$fieldName])) {
				$sectionFilter[$field] = $value;
			}
		}

		$sectionFilter[$sectionField] = $sectionId;

		$raw['SELECT'] = array_unique($raw['SELECT']);

		// при использовании в качестве popup окна исключаем раздел из выборке
		// что бы не было возможности сделать раздел родителем самого себя
		if (!empty($_REQUEST['self_id'])) {
			$sectionFilter['!' . $this->sectionPk()] = $_REQUEST['self_id'];
		}

		$sectionSort = array();
		$limitData = $this->getLimits();
		// добавляем к общему количеству элементов количество разделов
		$this->totalRowsCount = $sectionModel::getCount($this->getSectionsFilter($sectionFilter));
		foreach ($sort as $field => $direction) {
			if (in_array($field, $sectionsVisibleColumns)) {
				$sectionSort[$field] = $direction;
			}
		}
		// добавляем к выборке разделы
		$rsSections = $sectionModel::getList(array(
			'filter' => $this->getSectionsFilter($sectionFilter),
			'select' => $sectionsVisibleColumns,
			'order' => $sectionSort,
			'limit' => $limitData[1],
			'offset' => $limitData[0],
		));

		while ($section = $rsSections->fetch()) {
			$section['IS_SECTION'] = true;
			$returnData[] = $section;
		}

		// расчитываем offset и limit для элементов
		if (count($returnData) > 0) {
			$elementOffset = 0;
		}
		else {
			$elementOffset = $limitData[0] - $this->totalRowsCount;
		}

		// для списка разделов элементы не нужны
		if (static::getHelperClass(AdminSectionListHelper::className()) == static::className()) {
			return $returnData;
		}

		$elementLimit = $limitData[1] - count($returnData);
		$elementModel = static::$model;
		$elementFilter = $this->arFilter;
		$elementFilter[$elementEditHelperClass::getSectionField()] = $sectionId;
		// добавляем к общему количеству элементов количество элементов
		$this->totalRowsCount += $elementModel::getCount($this->getElementsFilter($elementFilter));

		// возвращае данные без элементов если разделы занимают всю страницу выборки
		if (!empty($returnData) && $limitData[0] == 0 && $limitData[1] == $this->totalRowsCount) {
			return $returnData;
		}

		$elementSort = array();
		foreach ($sort as $field => $direction) {
			if (in_array($field, $elementVisibleColumns)) {
				$elementSort[$field] = $direction;
			}
		}

		$elementParams = array(
			'filter' => $this->getElementsFilter($elementFilter),
			'select' => $elementVisibleColumns,
			'order' => $elementSort,
		);
		if ($elementLimit > 0 && $elementOffset >= 0) {
			$elementParams['limit'] = $elementLimit;
			$elementParams['offset'] = $elementOffset;
			// добавляем к выборке элементы
			$rsSections = $elementModel::getList($elementParams);

			while ($element = $rsSections->fetch()) {
				$element['IS_SECTION'] = false;
				$returnData[] = $element;
			}
		}

		/**
		 * Вернем результат с первой страницы если на текущей нет элементов.
		 * Для списка элементов аналогичная проверка есть в $this->getLimits()
		 */
		if (!count($returnData) && $this->totalRowsCount > 0)
		{
			$this->navParams['navParams']['PAGEN'] = 1;
			return $this->getMixedData($sectionsVisibleColumns, $elementVisibleColumns, $sort, $raw);
		}

		return $returnData;
	}

	/**
	 * Огранчения выборки из CAdminResult
	 * @return array
	 */
	protected function getLimits()
	{
		if ($this->navParams['navParams']['SHOW_ALL']) {
			return array();
		}
		else {
			if (!intval($this->navParams['navParams']['PAGEN']) OR !isset($this->navParams['navParams']['PAGEN'])) {
				$this->navParams['navParams']['PAGEN'] = 1;
			}
			$from = $this->navParams['nPageSize'] * ((int)$this->navParams['navParams']['PAGEN'] - 1);

			/**
			 * Вернем результат с первой страницы если на текущей нет элементов.
			 *
			 * $this->totalRowsCount еще не заполнен при смешанном отображении элементов и разделов,
			 * в $this->>getMixedData() есть отдельная проверка на этот счет
			 */
			if ($this->totalRowsCount && $from >= $this->totalRowsCount)
			{
				$this->navParams['navParams']['PAGEN'] = 1;
				$from = 0;
			}

			return array($from, $this->navParams['nPageSize']);
		}
	}

	/**
	 * Очищает название поля от операторов фильтра
	 * @param string $fieldName названия поля из фильтра
	 * @return string название поля без без операторов фильтра
	 */
	protected function escapeFilterFieldName($fieldName)
	{
		return str_replace(array('!','<', '<=', '>', '>=', '><', '=', '%'), '', $fieldName);
	}

	/**
	 * Выполняет CDBResult::NavNext с той разницей, что общее количество элементов берется не из count($arResult),
	 * а из нашего параметра, полученного из SQL-запроса.
	 * array_slice также не делается.
	 *
	 * @param \CAdminResult $res
	 */
	protected function customNavStart(&$res)
	{
		$res->NavStart($this->navParams['nPageSize'],
			$this->navParams['navParams']['SHOW_ALL'],
			(int)$this->navParams['navParams']['PAGEN']
		);
		// отключаем отображение всех элементов
		$res->bShowAll = $this->showAll;

		$res->NavRecordCount = $this->totalRowsCount;
		if ($res->NavRecordCount < 1)
			return;

		if ($res->NavShowAll)
			$res->NavPageSize = $res->NavRecordCount;

		$res->NavPageCount = floor($res->NavRecordCount / $res->NavPageSize);
		if ($res->NavRecordCount % $res->NavPageSize > 0)
			$res->NavPageCount++;

		$res->NavPageNomer =
			($res->PAGEN < 1 || $res->PAGEN > $res->NavPageCount
				?
				(\CPageOption::GetOptionString("main", "nav_page_in_session", "Y") != "Y"
				|| $_SESSION[$res->SESS_PAGEN] < 1
				|| $_SESSION[$res->SESS_PAGEN] > $res->NavPageCount
					?
					1
					:
					$_SESSION[$res->SESS_PAGEN]
				)
				:
				$res->PAGEN
			);
	}

	/**
	 * Преобразует данные строки, перед тем как добавлять их в список.
	 *
	 * @param $data
	 *
	 * @see AdminListHelper::getList()
	 *
	 * @api
	 */
	protected function modifyRowData(&$data)
	{
	}

	/**
	 * Настройки строки таблицы.
	 *
	 * @param array $data Данные текущей строки БД.
	 * @param bool|string $class Класс хелпера через метод getUrl которого идет получение ссылки.
	 *
	 * @return array Возвращает ссылку на детальную страницу и её название.
	 *
	 * @api
	 */
	protected function getRow($data, $class = false)
	{
		if (empty($class)) {
			$class = static::getHelperClass(AdminEditHelper::className());
		}
		if ($this->isPopup()) {
			return array();
		}
		else {
			$query = array_merge($this->additionalUrlParams, array(
				'lang' => LANGUAGE_ID,
				$this->pk() => $data[$this->pk()]
			));

			return array($class::getUrl($query));
		}
	}

	/**
	 * Для каждой ячейки(раздела) таблицы создаёт виджет соответствующего типа.
	 * Виджет подготавливает необходимый HTML для списка.
	 *
	 * @param \CAdminListRow $row
	 * @param $code Сивольный код поля.
	 * @param $data Данные текущей строки.
	 *
	 * @throws Exception
	 *
	 * @see HelperWidget::generateRow()
	 */
	protected function addRowSectionCell($row, $code, $data)
	{
		$sectionEditHelper = $this->getHelperClass(AdminSectionEditHelper::className());
		if (!isset($this->sectionFields[$code]['WIDGET'])) {
			$error = str_replace('#CODE#', $code, 'Can\'t create widget for the code "#CODE#"');
			throw new Exception($error, Exception::CODE_NO_WIDGET);
		}

		/**
		 * @var \DigitalWand\AdminHelper\Widget\HelperWidget $widget
		 */
		$widget = $this->sectionFields[$code]['WIDGET'];

		$widget->setHelper($this);
		$widget->setCode($code);
		$widget->setData($data);
		$widget->setEntityName($sectionEditHelper::getModel());

		$this->setContext(AdminListHelper::OP_ADD_ROW_CELL);
		$widget->generateRow($row, $data);
	}

	/**
	 * Возвращает массив со списком действий при клике правой клавишей мыши на строке таблицы
	 * По-умолчанию:
	 * <ul>
	 * <li> Редактировать элемент </li>
	 * <li> Удалить элемент </li>
	 * <li> Если это всплывающее окно - запустить кастомную JS-функцию. </li>
	 * </ul>
	 *
	 * @param $data Данные текущей строки.
	 * @param $section Признак списка для раздела.
	 *
	 * @return array
	 *
	 * @see CAdminListRow::AddActions
	 *
	 * @api
	 */
	protected function getRowActions($data, $section = false)
	{
		$actions = array();

		if ($this->isPopup()) {
			$jsData = \CUtil::PhpToJSObject($data);
			$actions['select'] = array(
				'ICON' => 'select',
				'DEFAULT' => true,
				'TEXT' => Loc::getMessage('DIGITALWAND_ADMIN_HELPER_LIST_SELECT'),
				"ACTION" => 'javascript:' . $this->popupClickFunctionName . '(' . $jsData . ')'
			);
		}
		else {
			$viewQueryString = 'module=' . static::getModule() . '&view=' . static::getViewName() . '&entity=' . static::getEntityCode();
			$query = array_merge($this->additionalUrlParams,
				array($this->pk() => $data[$this->pk()]));
			if ($this->hasWriteRights()) {
				$sectionHelperClass = static::getHelperClass(AdminSectionEditHelper::className());
				$editHelperClass = static::getHelperClass(AdminEditHelper::className());

				$actions['edit'] = array(
					'ICON' => 'edit',
					'DEFAULT' => true,
					'TEXT' => Loc::getMessage('DIGITALWAND_ADMIN_HELPER_LIST_EDIT'),
					'ACTION' => $this->list->ActionRedirect($section ? $sectionHelperClass::getUrl($query) : $editHelperClass::getUrl($query))
				);
			}
			if ($this->hasDeleteRights()) {
				$actions['delete'] = array(
					'ICON' => 'delete',
					'TEXT' => Loc::getMessage("DIGITALWAND_ADMIN_HELPER_LIST_DELETE"),
					'ACTION' => "if(confirm('" . Loc::getMessage('DIGITALWAND_ADMIN_HELPER_LIST_DELETE_CONFIRM') . "')) " . $this->list->ActionDoGroup($data[$this->pk()],
							$section ? "delete-section" : "delete", $viewQueryString)
				);
			}
		}

		return $actions;
	}

	/**
	 * Для каждой ячейки таблицы создаёт виджет соответствующего типа. Виджет подготавливает необходимый HTML-код
	 * для списка.
	 *
	 * @param \CAdminListRow $row Объект строки списка записей.
	 * @param string $code Сивольный код поля.
	 * @param array $data Данные текущей строки.
	 * @param bool $virtualCode
	 *
	 * @throws Exception
	 *
	 * @see HelperWidget::generateRow()
	 */
	protected function addRowCell($row, $code, $data, $virtualCode = false)
	{
		$widget = $this->createWidgetForField($code, $data);
		$this->setContext(AdminListHelper::OP_ADD_ROW_CELL);

		// устанавливаем виртуальный код ячейки, используется при слиянии столбцов
		if ($virtualCode) {
			$widget->setCode($virtualCode);
		}

		$widget->generateRow($row, $data);

		if ($virtualCode) {
			$widget->setCode($code);
		}
	}

	/**
	 * Производит выборку данных. Функцию стоит переопределить в случае, если необходима своя логика, и её нельзя
	 * вынести в класс модели.
	 *
	 * @param DataManager $className
	 * @param array $filter
	 * @param array $select
	 * @param array $sort
	 * @param array $raw
	 *
	 * @return Result
	 *
	 * @api
	 */
	protected function getData($className, $filter, $select, $sort, $raw)
	{
		$limits = $this->getLimits();
		$parameters = array(
			'filter' => $this->getElementsFilter($filter),
			'select' => $select,
			'order' => $sort,
			'offset' => $limits[0],
			'limit' => $limits[1],
		);

		/** @var Result $res */
		$res = $className::getList($parameters);

		return $res;
	}

	/**
	 * Подготавливает массив с настройками футера таблицы Bitrix
	 * @param \CAdminResult $res - результат выборки данных
	 * @see \CAdminList::AddFooter()
	 * @return array[]
	 */
	protected function getFooter($res)
	{
		return array(
			$this->getButton('MAIN_ADMIN_LIST_SELECTED', array("value" => $res->SelectedRowsCount())),
			$this->getButton('MAIN_ADMIN_LIST_CHECKED', array("value" => $res->SelectedRowsCount()), array(
				"counter" => true,
				"value" => "0",
			)),
		);
	}

	/**
	 * Выводит форму фильтрации списка
	 */
	public function createFilterForm()
	{
		//нужно пробрасывать параметр popup в форму, если она является таковой
		if($this->isPopup())
		{
			$this->additionalUrlParams['popup'] = 'Y';
		}

		$this->setContext(AdminListHelper::OP_CREATE_FILTER_FORM);
		print ' <form name="find_form" method="GET" action="' . static::getUrl($this->additionalUrlParams) . '?">';

		$sectionHelper = $this->getHelperClass(AdminSectionEditHelper::className());
		if($sectionHelper) {
			$sectionsInterfaceSettings = static::getInterfaceSettings($sectionHelper::getViewName());
			foreach($this->arFilterOpts as $code => $name) {
				if(!empty($this->tableColumnsMap[$code])) {
                    $newName = $sectionsInterfaceSettings['FIELDS'][$this->tableColumnsMap[$code]]['WIDGET']
                        ->getSettings('TITLE');
                    $this->arFilterOpts[$code] = $newName;
				}
			}

			unset($name);
		}

		$oFilter = new \CAdminFilter($this->getListTableID() . '_filter', $this->arFilterOpts);
		$oFilter->Begin();

		foreach ($this->arFilterOpts as $code => $name) {
			$widget = $this->createWidgetForField($code);
			if($widget->getSettings('TITLE') != $this->arFilterOpts[$code]) {
				$widget->setSetting('TITLE', $this->arFilterOpts[$code]);
			}
			$widget->showFilterHtml();
		}

		$oFilter->Buttons(array(
			"table_id" => $this->getListTableID(),
			"url" => static::getUrl($this->additionalUrlParams),
			"form" => "find_form",
		));
		$oFilter->End();

		print '</form>';
	}

	/**
	 * Возвращает ID таблицы, который не должен конфликтовать с ID в других разделах админки, а также нормально
	 * парситься в JS
	 *
	 * @return string
	 */
	protected function getListTableID()
	{
		return str_replace('.', '', static::$tablePrefix . $this->table());
	}

	/**
	 * Выводит сформированный список.
	 * Сохраняет обработанный GET-запрос в сессию
	 */
	public function show()
	{
		if (!$this->hasReadRights()) {
			$this->addErrors(Loc::getMessage('DIGITALWAND_ADMIN_HELPER_ACCESS_FORBIDDEN'));
			$this->showMessages();

			return false;
		}
		$this->showMessages();
		$this->list->DisplayList();

		if ($this->isPopup()) {
			print $this->popupClickFunctionCode;
		}

		$this->saveGetQuery();
	}

	/**
	 * Сохраняет параметры запроса для поторного использования после возврата с других страниц (к примеру, после
	 * перехода с детальной обратно в список - чтобы вернуться в точности в тот раздел, с которого ранее ушли)
	 */
	private function saveGetQuery()
	{
		$_SESSION['LAST_GET_QUERY'][get_called_class()] = $_GET;
	}

	/**
	 * Восстанавливает последний GET-запрос, если в текущем задан параметр restore_query=Y
	 */
	private function restoreLastGetQuery()
	{
		if (!isset($_SESSION['LAST_GET_QUERY'][get_called_class()])
			OR !isset($_REQUEST['restore_query'])
			OR $_REQUEST['restore_query'] != 'Y'
		) {
			return;
		}

		$_GET = array_merge($_GET, $_SESSION['LAST_GET_QUERY'][get_called_class()]);
		$_REQUEST = array_merge($_REQUEST, $_SESSION['LAST_GET_QUERY'][get_called_class()]);
	}

	/**
	 * @inheritdoc
	 */
	public static function getUrl(array $params = array())
	{
		return static::getViewURL(static::getViewName(), static::$listPageUrl, $params);
	}

	/**
	 * Кастомизация фильтра разделов
	 * @param $filter
	 * @return mixed
	 */
	protected function getSectionsFilter(array $filter)
	{
		return $filter;
	}

	/**
	 * Кастомизация фильтра элементов
	 * @param $filter
	 * @return mixed
	 */
	protected function getElementsFilter($filter)
	{
		return $filter;
	}

	/**
	 * Список идентификаторов для групповых операций
	 *
	 * @return array
	 */
	protected function getIds()
	{
		$className = static::getModel();
		if (isset($_REQUEST['model'])) {
			$className = $_REQUEST['model'];
		}

		$sectionEditHelperClass = $this->getHelperClass(AdminSectionEditHelper::className());
		if ($sectionEditHelperClass && !isset($_REQUEST['model-section'])) {
			$sectionClassName = $sectionEditHelperClass::getModel();
		}
		else {
			$sectionClassName = $_REQUEST['model-section'];
		}

        $pkValue = $this->getPk();
        if (isset($pkValue[$this->pk()]) && is_array($pkValue[$this->pk()])) {
			foreach ($pkValue[$this->pk()] as $id) {
				$class = strpos($id, 's') === 0 ? $sectionClassName : $className;
				$ids[] = $this->getCommonPrimaryFilterById($class, null, $id);
			}
		} else {
			$ids = array($this->getPk());
		}

		return $ids;
	}

	/**
	 * Получить оставшуюся часть составного первичного ключа
	 *
	 * @param $className
	 * @param null $sectionClassName
	 * @param $id
	 * @return array
	 */
	protected function getCommonPrimaryFilterById($className, $sectionClassName = null, $id)
	{
		if ($this->getHelperClass($sectionClassName) && strpos($id, 's') === 0) {
			$primary = $sectionClassName::getEntity()->getPrimary();
		} else {
			$primary = $className::getEntity()->getPrimary();
		}

		if (count($primary) === 1) {
			return array($this->pk() => $id);
		}

		$key = $this->getPk();
		$key[$this->pk()] = $id;

		return $key;
	}
}