<?php

namespace DigitalWand\AdminHelper\Helper;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\DB\Result;

Loc::loadMessages(__FILE__);

/**
 * Class AdminListHelper
 *
 * Базовый класс для реализации страницы списка админки.
 * При создании своего класса необходимо переопределить следующие переменные:
 * <ul>
 * <li> static protected $model </Li>
 * <li> static public $module </li>
 * <li> static protected $editViewName </li>
 * <li> static protected $viewName </li>
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
	public function __construct($fields, $isPopup = false)
	{
		$this->isPopup = $isPopup;

		if ($this->isPopup)
		{
			$this->fieldPopupResultName = preg_replace("/[^a-zA-Z0-9_:\\[\\]]/", "", $_REQUEST['n']);
			$this->fieldPopupResultIndex = preg_replace("/[^a-zA-Z0-9_:]/", "", $_REQUEST['k']);
			$this->fieldPopupResultElTitle = $_REQUEST['eltitle'];
		}

		parent::__construct($fields);

		$this->restoreLastGetQuery();
		$this->prepareAdminVariables();

		if (isset($_REQUEST['action']) || isset($_REQUEST['action_button']))
		{
			$id = isset($_REQUEST['ID']) ? $_REQUEST['ID'] : null;
			$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : $_REQUEST['action_button'];
			$this->customActions($action, $id);
		}

		$className = static::getModel();
		$oSort = new \CAdminSorting($this->getListTableID(), static::pk(), "desc");
		$this->list = new \CAdminList($this->getListTableID(), $oSort);
		$this->list->InitFilter($this->arFilterFields);

		if ($this->list->EditAction() AND $this->hasWriteRights())
		{
			global $FIELDS;
			foreach ($FIELDS as $id => $fields)
			{
				if (!$this->list->IsUpdated($id))
				{
					continue;
				}

				$id = intval($id);
				$this->editAction($id, $fields);
			}
		}

		if ($IDs = $this->list->GroupAction() AND $this->hasWriteRights())
		{

			if ($_REQUEST['action_target'] == 'selected')
			{
				$this->setContext(AdminListHelper::OP_GROUP_ACTION);
				$IDs = array();

				//Текущий фильтр должен быть модифицирован виждтами
				//для соответствия результатов фильтрации тому, что видит пользователь в интерфейсе.
				$raw = array(
					'SELECT' => $this->pk(),
					'FILTER' => $this->arFilter,
					'SORT' => array()
				);
				foreach ($this->fields as $code => $settings)
				{
					$widget = $this->createWidgetForField($code);
					$widget->changeGetListOptions($this->arFilter, $raw['SELECT'], $raw['SORT'], $raw);
				}

				$res = $className::getList(array(
					'filter' => $this->arFilter,
					'select' => array($this->pk()),
				));
				while ($el = $res->Fetch())
				{
					$IDs[] = $el[$this->pk()];
				}
			}

			$filteredIDs = array();
			foreach ($IDs as $id)
			{

				if (strlen($id) <= 0)
				{
					continue;
				}

				$filteredIDs[] = IntVal($id);
			}

			$this->groupActions($IDs, $_REQUEST['action']);
		}

		if ($this->isPopup())
		{
			$this->genPopupActionJS();
		}
	}

	/**
	 * Подготавливает переменные, используемые для инициализации списка.
	 */
	protected function prepareAdminVariables()
	{

		$this->arHeader = array();
		$this->arFilter = array();
		$this->arFilterFields = array();

		$arFilter = array();
		$this->filterTypes = array();

		$this->arFilterOpts = array();

		foreach ($this->fields as $code => $settings)
		{

			$widget = $this->createWidgetForField($code);
			if ((isset($settings['FILTER']) AND $settings['FILTER'] != false) OR !isset($settings['FILTER']))
			{

				$this->setContext(AdminListHelper::OP_ADMIN_VARIABLES_FILTER);

				$filterVarName = 'find_' . $code;
				$this->arFilterFields[] = $filterVarName;

				$filterType = '';
				if (is_string($settings['FILTER']))
				{
					$filterType = $settings['FILTER'];
				}

				if (isset($_REQUEST[$filterVarName])
					AND !isset($_REQUEST['del_filter'])
					AND $_REQUEST['del_filter'] != 'Y'
				)
				{
					$arFilter[$filterType . $code] = $_REQUEST[$filterVarName];
					$this->filterTypes[$code] = $filterType;
				}

				$this->arFilterOpts[$code] = $widget->getSettings('TITLE');
			}

			if (!isset($settings['HEADER']) OR $settings['HEADER'] != false)
			{
				$this->setContext(AdminListHelper::OP_ADMIN_VARIABLES_HEADER);
				$this->arHeader[] = array(
					"id" => $code,
					"content" => $widget->getSettings('TITLE'),
					"sort" => $code,
					"default" => true
				);
			}
		}

		if ($this->checkFilter($arFilter))
		{
			$this->arFilter = $arFilter;
		}

		if (static::$hasSections)
		{
			$model = $this->getModel();
			$this->arFilter[$model::getSectionField()] = $_REQUEST['ID'];
		}
	}

	/**
	 * Возвращает список столбцов для разделов
	 * @return array
	 */
	public function getSectionsHeader()
	{
		$arSectionsHeaders = array();
		$sectionsInterfaceSettings = static::getInterfaceSettings(static::$sectionsEditViewName);
		$this->sectionFields = $sectionsInterfaceSettings['FIELDS'];
		foreach ($sectionsInterfaceSettings['FIELDS'] as $code => $settings)
		{
			if (isset($settings['HEADER']) && $settings['HEADER'] == true)
			{
				$arSectionsHeaders[] = array(
					"id" => $code,
					"content" => $settings['TITLE'],
					"sort" => $code,
					"default" => true
				);
			}
			unset($settings['WIDGET']);
			foreach ($settings as $c => $v)
			{
				$sectionsInterfaceSettings['FIELDS'][$code]['WIDGET']->setSetting($c, $v);
			}
		}

		return $arSectionsHeaders;
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
			static::getButton('MAIN_ADMIN_LIST_SELECTED', array("value" => $res->SelectedRowsCount())),
			static::getButton('MAIN_ADMIN_LIST_CHECKED', array("value" => $res->SelectedRowsCount()), array(
				"counter" => true,
				"value" => "0",
			)),
		);
	}

	/**
	 * Подготавливает массив с настройками контекстного меню.
	 * По-умолчанию добавлена кнопка "создать элемент".
	 * @api
	 * @see $contextMenu
	 */
	protected function getContextMenu()
	{
		$contextMenu = array();
		if (static::$hasSections)
		{
			$this->additionalUrlParams['SECTION_ID'] = $_REQUEST['ID'];
		}

		/**
		 * Если задан для разделов добавляем кнопку создать раздел и
		 * кнопку на уровень вверх если это не корневой раздел
		 */
		if (!empty(static::$sectionModel) && isset($_REQUEST['ID']))
		{
			if ($_REQUEST['ID'])
			{
				$params = $this->additionalUrlParams;
				$sectionModel = static::$sectionModel;
				$section = $sectionModel::getById($_REQUEST['ID'])->Fetch();
				if ($this->isPopup())
				{
					$params = array_merge($_GET);
				}
				if ($section[$sectionModel::getSectionField()])
				{
					$params['ID'] = $section[$sectionModel::getSectionField()];
				}
				else
				{
					unset($params['ID']);
				}
				unset($params['SECTION_ID']);
				$contextMenu[] = static::getButton('LIST_SECTION_UP', array(
					'LINK' => static::getUrl($params),
					'ICON' => 'btn_list'
				));
			}
		}

		/**
		 * Добавляем кнопку создать элемент и создать раздел
		 */
		if (!$this->isPopup() && $this->hasWriteRights())
		{
			$editHelperClass = static::getEditHelperClass();
			$contextMenu[] = static::getButton('LIST_CREATE_NEW', array(
				'LINK' => $editHelperClass::getUrl($this->additionalUrlParams),
				'ICON' => 'btn_new'
			));
			if (static::$hasSections)
			{
				$sectionsHelperClass = static::getSectionsHelperClass();
				$contextMenu[] = static::getButton('LIST_CREATE_NEW_SECTION', array(
					'LINK' => $sectionsHelperClass::getUrl($this->additionalUrlParams),
					'ICON' => 'btn_new'
				));
			}
		}

		return $contextMenu;
	}

	/**
	 * Возвращает массив с настройками групповых действий над списком
	 * @api
	 * @return array
	 */
	protected function getGroupActions()
	{
		$result = array();
		if (!$this->isPopup())
		{
			if ($this->hasDeleteRights())
			{
				$result = array('delete' => Loc::getMessage("DIGITALWAND_ADMIN_HELPER_LIST_DELETE"));
			}
		}

		return $result;
	}

	/**
	 * Обработчик групповых операций.
	 * По-умолчанию прописаны операции активации/деактивации и удаления.
	 *
	 * @api
	 * @param array $IDs
	 * @param string $action
	 */
	protected function groupActions($IDs, $action)
	{
		if (!isset($_REQUEST['model']))
		{
			$className = static::getModel();
		}
		else
		{
			$className = $_REQUEST['model'];
		}

		if (!isset($_REQUEST['model-section']))
		{
			$sectionClassName = static::$sectionModel;
		}
		else
		{
			$sectionClassName = $_REQUEST['model-section'];
		}

		if ($action == 'delete')
		{
			if ($this->hasDeleteRights())
			{
				foreach ($IDs as $id)
				{
					$className::delete($id);
				}
			}
			else
			{
				$this->addErrors(Loc::getMessage('DIGITALWAND_ADMIN_HELPER_LIST_DELETE_FORBIDDEN'));
			}
		}

		if ($action == 'delete-section')
		{
			if ($this->hasDeleteRights())
			{
				foreach ($IDs as $id)
				{
					$sectionClassName::delete($id);
				}
			}
			else
			{
				$this->addErrors(Loc::getMessage('DIGITALWAND_ADMIN_HELPER_LIST_DELETE_FORBIDDEN'));
			}
		}
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
	 * <li> Отрисовка футера таблиы, добавление контекстного меню </li>
	 * </ul>
	 *
	 * @param array $sort Настройки сортировки.
	 *
	 * @see AdminListHelper::getList();
	 * @see AdminListHelper::modifyRowData();
	 * @see AdminListHelper::addRowCell();
	 * @see AdminListHelper::addRow();
	 * @see HelperWidget::changeGetListOptions();
	 */
	public function buildList($sort)
	{
		$this->setContext(AdminListHelper::OP_GET_DATA_BEFORE);

		if (static::$hasSections && $_REQUEST['PAGEN_1'] < 2)
		{
			/**
			 * Добавляем столбцы разделов если они используются
			 */
			$this->list->AddHeaders($this->getSectionsHeader());
			/**
			 * Добавляем разделы в выборку если не первая страница
			 */
			$sectionsModel = static::$sectionModel;
			$res = $sectionsModel::getList(['filter' => [$sectionsModel::getSectionField() => $_REQUEST['ID']]]);
			$fields = $this->fields;
			$this->fields = $this->sectionFields;
			while ($data = $res->Fetch())
			{
				$this->modifyRowData($data);
				list($link, $name) = $this->getRow($data, get_called_class());

				$row = $this->list->AddRow('s' . $data[$this->pk()], $data, $link, $name);
				foreach ($this->fields as $code => $settings)
				{
					$this->addRowCell($row, $code, $data);
				}
				$row->AddActions($this->getRowActions($data, true));
			}
			$this->fields = $fields;
			$sectionsVisibleColumns = $this->list->GetVisibleHeaderColumns();
		}

		$this->list->AddHeaders($this->arHeader);

		$visibleColumns = $this->list->GetVisibleHeaderColumns();

		if (static::$hasSections && $_REQUEST['PAGEN_1'] < 2)
		{
			foreach ($visibleColumns as $k => $v)
			{
				if (in_array($v, $sectionsVisibleColumns))
				{
					unset($visibleColumns[$k]);
				}
			}
			$visibleColumns = array_values($visibleColumns);
		}

		$className = static::getModel();
		$visibleColumns[] = static::pk();

		$raw = array(
			'SELECT' => $visibleColumns,
			'FILTER' => $this->arFilter,
			'SORT' => $sort
		);

		foreach ($this->fields as $name => $settings)
		{
			if ((isset($settings['VIRTUAL']) AND $settings['VIRTUAL'] == true))
			{
				$key = array_search($name, $visibleColumns);
				unset($visibleColumns[$key]);
				unset($this->arFilter[$name]);
				unset($sort[$name]);
			}
			if (isset($settings['FORCE_SELECT']) AND $settings['FORCE_SELECT'] == true)
			{
				$visibleColumns[] = $name;
			}
		}
		$visibleColumns = array_unique($visibleColumns);
		// Поля для селекта (перевернутый массив)
		$listSelect = array_flip($visibleColumns);
		foreach ($this->fields as $code => $settings)
		{
			$widget = $this->createWidgetForField($code);
			$widget->changeGetListOptions($this->arFilter, $visibleColumns, $sort, $raw);
			// Множественные поля не должны быть в селекте
			if (!empty($settings['MULTIPLE']))
			{
				unset($listSelect[$code]);
			}
		}
		// Поля для селекта (множественные поля отфильтрованы)
		$listSelect = array_flip($listSelect);
		$res = $this->getData($className, $this->arFilter, $listSelect, $sort, $raw);

		$res = new \CAdminResult($res, $this->getListTableID());
		$res->NavStart();

		$this->list->NavText($res->GetNavPrint(Loc::getMessage("PAGES")));

		while ($data = $res->NavNext(false))
		{
			$this->modifyRowData($data);
			list($link, $name) = $this->getRow($data);
			$row = $this->list->AddRow($data[$this->pk()], $data, $link, $name);
			foreach ($this->fields as $code => $settings)
			{
				$this->addRowCell($row, $code, $data);
			}
			$row->AddActions($this->getRowActions($data));
		}

		$this->list->AddFooter($this->getFooter($res));
		$this->list->AddGroupActionTable($this->getGroupActions(), $this->groupActionsParams);
		$this->list->AddAdminContextMenu($this->getContextMenu());

		$this->list->BeginPrologContent();
		echo $this->prologHtml;
		$this->list->EndPrologContent();

		$this->list->BeginEpilogContent();
		echo $this->epilogHtml;
		$this->list->EndEpilogContent();

		$this->list->CheckListMode();
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
	 * @api
	 *
	 * @return Result
	 */
	protected function getData($className, $filter, $select, $sort, $raw)
	{
		$parameters = array(
			'filter' => $filter,
			'select' => $select,
			'order' => $sort
		);

		/** @var Result $res */
		$res = $className::getList($parameters);

		return $res;
	}

	/**
	 * Является ли список всплывающим окном для выбора элементов из списка.
	 * В этой версии не должно быть операций удаления/перехода к редактированию.
	 * @return boolean
	 */
	public function isPopup()
	{
		return $this->isPopup;
	}

	/**
	 * Настройки строки таблицы
	 * @param array $data данные текущей строки БД
	 * @param string $method метод через который идет получение ссылки
	 * @return array возвращает ссылку на детальную страницу и её название
	 * @api
	 */
	protected function getRow($data, $class = false)
	{
		if (empty($class))
		{
			$class = static::getEditHelperClass();
		}
		if ($this->isPopup())
		{
			return array();
		}
		else
		{
			$query = array_merge($this->additionalUrlParams, array(
				'lang' => LANGUAGE_ID,
				static::pk() => $data[static::pk()]
			));

			return array($class::getUrl($query));
		}
	}

	/**
	 * Преобразует данные строки, перед тем как добавлять их в список
	 * @api
	 * @param $data
	 * @see AdminListHelper::getList();
	 */
	protected function modifyRowData(&$data)
	{
	}

	/**
	 * Для каждой ячейки таблицы создаёт виджет соответствующего типа.
	 * Виджет подготавливает необходимый HTML для списка
	 *
	 * @param \CAdminListRow $row
	 * @param $code - сивольный код поля
	 * @param $data - данные текущей строки
	 * @see HelperWidget::genListHTML()
	 */
	protected function addRowCell($row, $code, $data)
	{
		$widget = $this->createWidgetForField($code, $data);
		$this->setContext(AdminListHelper::OP_ADD_ROW_CELL);
		$widget->genListHTML($row, $data);
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
	 * @see CAdminListRow::AddActions
	 *
	 * @api
	 * @param $data - данные текущей строки
	 * @param $section - признак списка для раздела
	 * @return array
	 */
	protected function getRowActions($data, $section = false)
	{
		$actions = array();

		if ($this->isPopup())
		{
			$jsData = \CUtil::PhpToJSObject($data);
			$actions['select'] = array(
				"ICON" => "select",
				"DEFAULT" => true,
				"TEXT" => Loc::getMessage("DIGITALWAND_ADMIN_HELPER_LIST_SELECT"),
				"ACTION" => 'javascript:' . $this->popupClickFunctionName . '(' . $jsData . ')'
			);
		}
		else
		{
			$viewQueryString = 'module=' . static::getModule() . '&view=' . static::getViewName().'&entity=' . static::getNamespaceUrlParam();
			$query = array_merge($this->additionalUrlParams,
				array($this->pk() => $data[$this->pk()]));
			if ($this->hasWriteRights())
			{
				$sectionHelperClass = static::getSectionsHelperClass();
				$editHelperClass = static::getEditHelperClass();

				$actions['edit'] = array(
					"ICON" => "edit",
					"DEFAULT" => true,
					"TEXT" => Loc::getMessage("DIGITALWAND_ADMIN_HELPER_LIST_EDIT"),
					"ACTION" => $this->list->ActionRedirect($section ? $sectionHelperClass::getUrl($query) : $editHelperClass::getUrl($query))
				);
			}
			if ($this->hasDeleteRights())
			{
				$actions['delete'] = array(
					"ICON" => "delete",
					"TEXT" => Loc::getMessage("DIGITALWAND_ADMIN_HELPER_LIST_DELETE"),
					"ACTION" => "if(confirm('" . Loc::getMessage('DIGITALWAND_ADMIN_HELPER_LIST_DELETE_CONFIRM') . "')) " . $this->list->ActionDoGroup($data[$this->pk()],
							$section ? "delete-section" : "delete", $viewQueryString)
				);
			}
		}

		return $actions;
	}

	/**
	 * Функция определяет js-функцию для двойонго клика по строке.
	 * Вызывается в том случае, если окно открыто в режиме попапа.
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
	 * Выводит форму фильтрации списка
	 */
	public function createFilterForm()
	{
		$this->setContext(AdminListHelper::OP_CREATE_FILTER_FORM);
		print ' <form name="find_form" method="GET" action="' . static::getUrl($this->additionalUrlParams) . '?">';

		$oFilter = new \CAdminFilter($this->getListTableID() . '_filter', $this->arFilterOpts);
		$oFilter->Begin();
		foreach ($this->arFilterOpts as $code => $name)
		{
			$widget = $this->createWidgetForField($code);
			$widget->genFilterHTML();
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
	 * Производит проверку корректности данных (в массиве $_REQUEST), переданных в фильтр
	 * @TODO: нужно сделать вывод сообщений об ошибке фильтрации.
	 * @param $arFilter
	 * @return bool
	 */
	protected function checkFilter($arFilter)
	{
		$this->setContext(AdminListHelper::OP_CHECK_FILTER);
		$filterValidationErrors = array();
		foreach ($this->filterTypes as $code => $type)
		{
			$widget = $this->createWidgetForField($code);
			$value = $arFilter[$type . $code];
			if (!$widget->checkFilter($type, $value))
			{
				$filterValidationErrors = array_merge($filterValidationErrors,
					$widget->getValidationErrors());
			}
		}

		return empty($filterValidationErrors);
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

		if (!$this->hasWriteRights())
		{
			$this->addErrors(Loc::getMessage('DIGITALWAND_ADMIN_HELPER_LIST_WRITE_FORBIDDEN'));

			return;
		}

		$className = static::getModel();
		$el = $className::getById($id);
		if ($el->getSelectedRowsCount() == 0)
		{
			$this->list->AddGroupError(Loc::getMessage("DIGITALWAND_ADMIN_HELPER_SAVE_ERROR"), $id);

			return;
		}

		$allWidgets = array();
		foreach ($fields as $key => $value)
		{
			$widget = $this->createWidgetForField($key, $fields);

			$widget->processEditAction();
			$this->validationErrors = array_merge($this->validationErrors, $widget->getValidationErrors());
			$allWidgets[] = $widget;
		}
		//FIXME: может, надо добавить вывод ошибок ДО сохранения?..
		$this->addErrors($this->validationErrors);

		$result = $className::update($id, $fields);
		$errors = $result->getErrorMessages();
		if (empty($this->validationErrors) AND !empty($errors))
		{
			$fieldList = implode("\n", $errors);
			$this->list->AddGroupError(Loc::getMessage("DIGITALWAND_ADMIN_HELPER_SAVE_ERROR") . " " . $fieldList, $id);
		}

		if (!empty($errors))
		{
			foreach ($allWidgets as $widget)
			{
				/** @var \DigitalWand\AdminHelper\Widget\HelperWidget $widget */
				$widget->setData($fields);
				$widget->processAfterSaveAction();
			}
		}
	}

	/**
	 * Выводит сформированный список.
	 * Сохраняет обработанный GET-запрос в сессию
	 */
	public function show()
	{
		$this->showMessages();
		$this->list->DisplayList();

		if ($this->isPopup())
		{
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
		)
		{
			return;
		}

		$_GET = array_merge($_GET, $_SESSION['LAST_GET_QUERY'][get_called_class()]);
		$_REQUEST = array_merge($_REQUEST, $_SESSION['LAST_GET_QUERY'][get_called_class()]);
	}

	public static function getUrl($params = array())
	{
		return static::getViewURL(static::getViewName(), static::$listPageUrl, $params);
	}
}