<?php

namespace AdminHelper;

IncludeModuleLangFile(__FILE__);


use AdminHelper\AdminBaseHelper;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\DB\Result;


abstract class AdminListHelper extends AdminBaseHelper
{
    /**
     * @var bool Если это всплывающее окно, то не должно быть операций удаления/перехода к редактированию.
     */
    protected $isPopup = false;
    protected $popupClickFunctionName = 'selectRow';
    protected $popupClickFunctionCode;

    protected $arHeader = array();
    protected $arFilter = array();
    protected $filterTypes = array();
    protected $arFilterFields = array();
    protected $arFilterOpts = array();
    protected $navParams = false;

    protected $list;
    protected $totalRowsCount;

    static protected $sectionFilter;
    static protected $sectionViewName;

    static protected $isPlainList = false;

    static protected $tablePrefix = "digitalwand.admin_helper_";

    /**
     * @var array Массив с настройками контекстного меню.
     * Пример:
     * <code>
     * $this->contextMenu = array(
     *   array(
     *       "TEXT" => GetMessage("BCL_BACKUP_DO_BACKUP"),
     *       "LINK" => "/bitrix/admin/dump.php?lang=".LANGUAGE_ID."&from=bitrixcloud",
     *       "TITLE" => "",
     *       "ICON" => "btn_new",
     *       ),
     *   );
     * </code>
     */
    protected $contextMenu = array();

    /**
     * @var array массив со списком групповых действий над таблицей.
     * Ключ - код действия. Знаение - перевод.
     */
    protected $groupActionsList = array();
    protected $groupActionsParams = array();

    protected $footer = array();

    public $prologHtml;
    public $epilogHtml;


    /**
     * Производится инициализация переменных, обработка запросов на редактирование
     */
    public function __construct($fields, $isPopup = false)
    {
        $this->isPopup = $isPopup;
        parent::__construct($fields);

        $this->restoreLastGetQuery();
        $this->prepareAdminVariables();
        $this->addContextMenu();
        $this->addGroupActions();

        if (isset($_REQUEST['action'])) {
            $id = isset($_REQUEST['ID']) ? $_REQUEST['ID'] : null;
            $this->customActions($_REQUEST['action'], $id);
        }

        /**@var DataManager $className */
        $className = static::$model;
        $oSort = new \CAdminSorting(static::$tablePrefix . $this->table(),
          static::pk(), "asc");
        $this->list = new \CAdminList(static::$tablePrefix . $this->table(),
          $oSort);
        $this->list->InitFilter($this->arFilterFields);

        if ($this->list->EditAction() AND $this->hasRights()) {
            global $FIELDS;
            foreach ($FIELDS as $id => $fields) {
                if (!$this->list->IsUpdated($id)) {
                    continue;
                }

                $id = intval($id);
                $this->editAction($id, $fields);
            }
        }

        if ($IDs = $this->list->GroupAction() AND $this->hasRights()) {

            if ($_REQUEST['action_target'] == 'selected') {
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
                    if ($widget) {
                        $widget->changeGetListOptions($this->arFilter,
                          $raw['SELECT'], $raw['SORT'], $raw);
                    }
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

        $navUniqSettings = array(
          'sNavID' => static::$tablePrefix . $this->table(),
        );;
        $this->navParams = array(
          'nPageSize' => \CAdminResult::GetNavSize($navUniqSettings),
          'navParams' => \CAdminResult::GetNavParams($navUniqSettings),
        );

        if ($this->isPopup()) {
            $this->genPopupActionJS();
        }
    }

    /**
     * Подготавливает переменные, используемые для инициализации списка
     */
    protected function prepareAdminVariables()
    {
        $this->arHeader = array();
        $this->arFilter = array();
        $this->arFilterFields = array();

        $arFilter = array();
        $this->filterTypes = array();

        $this->arFilterOpts = array();

        foreach ($this->fields as $code => $settings) {

            if (isset($settings['FILTER']) AND $settings['FILTER'] != false) {
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
                $this->arFilterOpts[$code] = $settings['TITLE'];
            }

            if (!isset($settings['HEADER']) OR $settings['HEADER'] != false) {
                $this->arHeader[] = array(
                  "id" => $code,
                  "content" => $settings['TITLE'],
                  "sort" => $code,
                  "default" => true
                );
            }
        }

        if ($this->checkFilter($arFilter)) {
            $this->arFilter = $arFilter;
        }

        $this->isPlainList = isset($_REQUEST['plain_list']) ? $_REQUEST['plain_list'] == "Y" : false;
        if (!$this->isPlainList) {
            if (isset(static::$sectionFilter) && (empty($_REQUEST[static::$sectionFilter]) || $_REQUEST[static::$sectionFilter] == 0) && !empty($this->arFilter)) {
                $this->isPlainList = true;
            } else {
                if (isset(static::$sectionFilter) && !isset($_REQUEST[static::$sectionFilter]) && !empty($this->arFilter)) {
                    $this->isPlainList = true;
                }
            }
        }
    }

    /**
     * Подготавливает массив с настройками футера таблицы Bitrix
     * @param CDatabase $res - результат выборки данных
     */
    protected function addFooter($res)
    {
        $this->footer = array(
          array(
            "title" => GetMessage("MAIN_ADMIN_LIST_SELECTED"),
            "value" => $res->SelectedRowsCount(),
          ),
          array(
            "counter" => true,
            "title" => GetMessage("MAIN_ADMIN_LIST_CHECKED"),
            "value" => "0",
          ),
        );
    }

    /**
     * Подготавливает массив с настройками контекстного меню.
     * @see $contextMenu
     */
    protected function addContextMenu()
    {
        $this->contextMenu = array();

        if (!$this->isPopup() && $this->hasRights()) {
            $this->contextMenu[] = array(
              'TEXT' => GetMessage('MAIN_ADMIN_LIST_CREATE_NEW'),
              'LINK' => static::getEditPageURL($this->additionalUrlParams),
              'TITLE' => GetMessage('MAIN_ADMIN_LIST_CREATE_NEW'),
              'ICON' => 'btn_new'
            );
        }
    }

    /**
     * Подготавливает массив с настройками групповых действий над списком
     * @see groupActionsList
     */
    protected function addGroupActions()
    {
        if (!$this->isPopup()) {
            $this->groupActionsList = array('delete' => GetMessage("MAIN_ADMIN_LIST_DELETE"));
        }
    }

    /**
     * @param array $IDs
     * @param string $action
     */
    protected function groupActions($IDs, $action)
    {
        if (!isset($_REQUEST['model'])) {
            /** @var DataManager $className */
            $className = static::$model;
        } else {
            $className = $_REQUEST['model'];
        }

        if ($action == 'delete') {
            foreach ($IDs as $id) {
                $className::delete($id);
            }

        } else {
            if (in_array($action, array(
                'activate',
                'deactivate'
              )) AND isset($this->fields['ACTIVE'])
            ) {
                $active = false;
                if ($action == 'activate') {
                    $active = 1;
                } else {
                    if ($action == 'deactivate') {
                        $active = 0;
                    }
                }

                if ($active !== false) {
                    //FIXME: переписать
                    foreach ($IDs as $id) {
                        $className::update($id,
                          array('ACTIVE' => ($active ? 'Y' : 'N')));
                    }
                }
            }
        }
    }

    /**
     * Выполняет сбор данных, формирует по ним таблицу и поля.
     * @param array $sort Настройки сортировки.
     */
    public function getData($sort)
    {
        $this->list->AddHeaders($this->arHeader);
        $visibleColumns = $this->list->GetVisibleHeaderColumns();

        /**@var DataManager $className */
        $className = static::$model;
        $visibleColumns[] = static::pk();

        $raw = array(
          'SELECT' => $visibleColumns,
          'FILTER' => $this->arFilter,
          'SORT' => $sort
        );


        foreach ($this->fields as $name => $settings) {
            if ((isset($settings['VIRTUAL']) AND $settings['VIRTUAL'] == true)) {
                $key = array_search($name, $visibleColumns);
                unset($visibleColumns[$key]);
                unset($this->arFilter[$name]);
                unset($sort[$name]);
            }
            if (isset($settings['FORCE_SELECT']) AND $settings['FORCE_SELECT'] == true) {
                $visibleColumns[] = $name;
            }
        }
        $visibleColumns = array_unique($visibleColumns);

        foreach ($this->fields as $code => $settings) {
            $widget = $this->createWidgetForField($code);
            if ($widget) {
                $widget->changeGetListOptions($this->arFilter, $visibleColumns,
                  $sort, $raw);
            }
        }

        $res = $this->getList($className, $this->arFilter,
          $visibleColumns, $sort, $raw);

        $res = new \CAdminResult($res, static::$tablePrefix . $this->table());
        $res->NavStart();

        $this->list->NavText($res->GetNavPrint(GetMessage("PAGES")));

        while ($data = $res->NavNext(false)) {
            $this->modifyRowData($data);
            list($link, $name) = $this->addRow($data);
            $row = $this->list->AddRow($data[$this->pk()], $data, $link, $name);
            foreach ($this->fields as $code => $settings) {
                $editableInputName = "FIELDS[" . $data[$this->pk()] . "][" . $code . "]";
                $this->addRowCell($row, $code, $data, $editableInputName);

            }
            $actions = $this->addRowActions($data);
            $row->AddActions($actions);
        }

        $this->addFooter($res);
        $this->list->AddFooter($this->footer);
        $this->list->AddGroupActionTable($this->groupActionsList,
          $this->groupActionsParams);
        $this->list->AddAdminContextMenu($this->contextMenu);

        $this->list->BeginPrologContent();
        echo $this->prologHtml;
        $this->list->EndPrologContent();

        $this->list->BeginEpilogContent();
        echo $this->epilogHtml;
        $this->list->EndEpilogContent();

        $this->list->CheckListMode();
    }

    /**
     * @param DataManager $className
     * @param array $filter
     * @param array $select
     * @param array $sort
     * @param array $raw
     * @return Result
     */
    protected function getList($className, $filter, $select, $sort, $raw)
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
     * @return boolean
     */
    public function isPopup()
    {
        return $this->isPopup;
    }

    /**
     * Настройки строки таблицы
     * @param array $data данные текущей строки БД
     * @return array возвращает ссылку на детальную страницу и её название
     */
    protected function addRow($data)
    {
        if ($this->isPopup()) {
            return array();

        } else {
            $query = array_merge($this->additionalUrlParams, array(
              'lang' => LANGUAGE_ID,
              static::pk() => $data[static::pk()]
            ));

            return array(static::getEditPageURL($query));
        }
    }

    /**
     * Преобразует данные строки, перед тем как добавлять их в список
     * @param $data
     */
    protected function modifyRowData(&$data){

    }

    /**
     * Определяет поля для отображения данных каждого типа
     *
     * @param CAdminListRow $row
     * @param $code - сивольный код поля
     * @param $data - данные текущей строки
     * @param $editableInputName - для режима редактирования  - атрибут "name" для input
     */
    protected function addRowCell($row, $code, $data, $editableInputName)
    {
        $widget = $this->createWidgetForField($code, $data);
        if ($widget) {
            $widget->genListHTML($row, $data);

        }
    }

    /**
     * Добавляет действия при клике правой клавишей мыши на строке таблицы
     * @see CAdminListRow::AddActions
     *
     * @param $data - данные текущей строки
     * @return array
     */
    protected function addRowActions($data)
    {
        $actions = array();

        if ($this->isPopup()) {
            $jsData = CUtil::PhpToJSObject($data);
            $actions[] = array(
              "ICON" => "select",
              "DEFAULT" => true,
              "TEXT" => GetMessage("MAIN_ADMIN_LIST_SELECT"),
              "ACTION" => 'javascript:' . $this->popupClickFunctionName . '(' . $jsData . ')'
            );

        } else {
            $viewQueryString = 'module=' . static::getModule() . '&view=' . static::$viewName;
            $query = array_merge($this->additionalUrlParams,
              array($this->pk() => $data[$this->pk()]));
            if ($this->hasRights()) {
                $actions[] = array(
                  "ICON" => "edit",
                  "DEFAULT" => true,
                  "TEXT" => GetMessage("MAIN_ADMIN_LIST_EDIT"),
                  "ACTION" => $this->list->ActionRedirect(static::getEditPageURL($query))
                );

                $actions[] = array(
                  "ICON" => "delete",
                  "TEXT" => GetMessage("MAIN_ADMIN_LIST_DELETE"),
                  "ACTION" => "if(confirm('" . GetMessage('MAIN_ADMIN_LIST_DELETE_CONFIRM') . "')) " . $this->list->ActionDoGroup($data[$this->pk()],
                      "delete", $viewQueryString)
                );
            }
        }

        return $actions;
    }

    /**
     * Функция определяет js-функцию для двойонго клика по строке.
     * Вызывается в том случае, елси окно открыто в режиме попапа.
     */
    protected function genPopupActionJS()
    {
        //Тестовый пример. Необходимо переопределить!
        $this->popupClickFunctionCode = '<script>
            function selectRow(data){
                console.log(data);
            }
        </script>';
    }

    /**
     * Выводит форму фильтрации списка
     */
    public function createFilterForm()
    {
        global $APPLICATION;
        print ' <form name="find_form" method="GET" action="' . static::getListPageURL($this->additionalUrlParams) . '?">';

        $oFilter = new \CAdminFilter(static::$tablePrefix . $this->table() . '_filter',
          $this->arFilterOpts);
        $oFilter->Begin();
        foreach ($this->arFilterOpts as $code => $name) {
            $widget = $this->createWidgetForField($code);
            if ($widget) {
                $widget->genFilterHTML();
            }
        }

        $oFilter->Buttons(array(
          "table_id" => static::$tablePrefix . $this->table(),
          "url" => static::getListPageURL($this->additionalUrlParams),
          "form" => "find_form",
        ));
        $oFilter->End();

        print '</form>';
    }

    /**
     * Производит проверку корректности данных (в массиве $_REQUEST), переданных в фильтр
     * TODO: нужно сделать вывод сообщений об ошибке фильтрации.
     */
    protected function checkFilter($arFilter)
    {
        $filterValidationErrors = array();
        foreach ($this->filterTypes as $code => $type) {
            $widget = $this->createWidgetForField($code);
            if ($widget) {
                $value = $arFilter[$type . $code];
                if (!$widget->checkFilter($type, $value)) {
                    $filterValidationErrors = array_merge($filterValidationErrors,
                      $widget->getValidationErrors());
                }
            }
        }

        return empty($filterValidationErrors);
    }


    /**
     * Сохранение полей, отредактированных в списке
     *
     * @param int $id ID записи в БД
     * @param array $fields Поля с изменениями
     */
    protected function editAction($id, $fields)
    {
        /** @var DataManager $className */
        $className = static::$model;
        $el = $className::getById($id);
        if ($el->getSelectedRowsCount() == 0) {
            $this->list->AddGroupError(GetMessage("MAIN_ADMIN_SAVE_ERROR"),
              $id);

            return;
        }

        $allWidgets = array();
        foreach ($fields as $key => $value) {
            $widget = $this->createWidgetForField($key);
            if (!$widget) {
                continue;
            }

            $widget->setEntityName($className);
            $widget->setData($fields);
            $widget->processEditAction();
            $this->validationErrors = array_merge($this->validationErrors,
              $widget->getValidationErrors());
            $allWidgets[] = $widget;
        }
        $this->addErrors($this->validationErrors);

        $result = $className::update($id, $fields);
        $errors = $result->getErrorMessages();
        if (empty($this->validationErrors) AND !empty($errors)) {
            $fieldList = implode("\n", $errors);
            $this->list->AddGroupError(GetMessage("MAIN_ADMIN_SAVE_ERROR") . " " . $fieldList,
              $id);
        }

        if (!empty($errors)) {
            foreach ($allWidgets as $widget) {
                /** @var \AdminHelper\Widget\HelperWidget $widget */
                $widget->setData($fields);
                $widget->processAfterSaveAction();
            }
        }
    }

    /**
     * Выводит сформированный список
     */
    public function show()
    {
        $this->showMessages();
        $this->list->DisplayList();

        if ($this->isPopup()) {
            print $this->popupClickFunctionCode;
        }

        $this->saveGetQuery();
    }

    private function saveGetQuery()
    {
        $_SESSION['LAST_GET_QUERY'][get_called_class()] = $_GET;
    }

    private function restoreLastGetQuery()
    {
        if (!isset($_SESSION['LAST_GET_QUERY'][get_called_class()])
          OR !isset($_REQUEST['restore_query'])
          OR $_REQUEST['restore_query'] != 'Y'
        ) {
            return;
        }

        $_GET = array_merge($_GET,
          $_SESSION['LAST_GET_QUERY'][get_called_class()]);
        $_REQUEST = array_merge($_REQUEST,
          $_SESSION['LAST_GET_QUERY'][get_called_class()]);

    }

}

