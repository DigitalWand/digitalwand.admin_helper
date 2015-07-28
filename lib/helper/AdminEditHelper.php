<?php

namespace DigitalWand\AdminHelper;

use Bitrix\Main\Localization\Loc;
use DigitalWand\AdminHelper\Widget\HelperWidget;
use Bitrix\Main\Entity\DataManager;

Loc::loadMessages(__FILE__);

/**
 * Class AdminEditHelper
 *
 * Базовый класс для реализации детальной страницы админки.
 * При создании своего класса необходимо переопределить следующие переменные:
 * <ul>
 * <li> static protected $model</li>
 * <li> static public $module</li>
 * <li> static protected $listViewName</li>
 * <li> static protected $viewName</li>
 * </ul>
 *
 * Этого будет дастаточно для получения минимальной функциональности
 *
 * @see AdminBaseHelper::$model
 * @see AdminBaseHelper::$module
 * @see AdminBaseHelper::$listViewName
 * @see AdminBaseHelper::$viewName
 * @package AdminHelper
 */
abstract class AdminEditHelper extends AdminBaseHelper
{
    /**
     * @var array
     * Данные сущности, редактируемой в данный момент.
     * Ключи ассива - названия полей в БД.
     * @api
     */
    protected $data;

    /**
     * @var array
     * Вкладки страницы редактирования
     */
    protected $tabs = array();

    /**
     * @var array
     * Элементы верхнего меню страницы
     * @see AdminEditHelper::fillMenu()
     */
    protected $menu = array();

    /**
     * @var \CAdminForm
     */
    protected $tabControl;


    /**
     * Производится инициализация переменных, обработка запросов на редактирование
     *
     * @param array $fields
     * @param array $tabs
     *
     * @see AdminBaseHelper::setInterfaceSettings()
     */
    public function __construct(array $fields, array $tabs = array())
    {
        $this->tabs = $tabs;
        if (empty($this->tabs)) {
            $this->tabs = array(
                array(
                    'DIV' => 'DEFAULT_TAB',
                    'TAB' => GetMessage('DEFAULT_TAB'),
                    "ICON" => "main_user_edit",
                    'TITLE' => GetMessage('DEFAULT_TAB'),
                    'VISIBLE' => true,
                )
            );
        } else {
            if (!is_array(reset($this->tabs))) {
                $converted = array();
                foreach ($this->tabs as $tabCode => $tabName) {
                    $tabVisible = true;
                    if (is_array($tabName)) {
                        $tabVisible = isset($tabName['VISIBLE']) ? $tabName['VISIBLE'] : $tabVisible;
                        $tabName = $tabName['TITLE'];
                    }
                    $converted[] = array(
                        'DIV' => $tabCode,
                        'TAB' => $tabName,
                        'ICON' => '',
                        'TITLE' => $tabName,
                        'VISIBLE' => $tabVisible,
                    );
                }
                $this->tabs = $converted;
            }
        }

        parent::__construct($fields, $tabs);

        $this->tabControl = new \CAdminForm(str_replace("\\", "", get_called_class()), $this->tabs);

        if (isset($_REQUEST['apply']) OR isset($_REQUEST['save'])) {
            $this->data = $_REQUEST['FIELDS'];
            foreach ($fields as $name => $settings) {
                if (is_a($settings['WIDGET'], 'DigitalWand\AdminHelper\Widget\HLIBlockFieldWidget')) {
                    $this->data = array_merge($this->data, $_REQUEST);
                    break;
                }
            }

            if ($this->editAction()) {
                if (isset($_REQUEST['apply'])) {
                    $id = $this->data[$this->pk()];
                    $url = $this->app->GetCurPageParam($this->pk() . '=' . $id);

                } else {
                    if (isset($_REQUEST['save'])) {
                        $url = $this->getListPageURL(array_merge($this->additionalUrlParams,
                            array(
                                'restore_query' => 'Y'
                            )));
                    }
                }

            } else {
                if (isset($this->data[$this->pk()])) {
                    $id = $this->data[$this->pk()];
                    $url = $this->app->GetCurPageParam($this->pk() . '=' . $id);
                } else {
                    unset($this->data);
                    $this->data = $_REQUEST['FIELDS']; //Заполняем, чтобы в случае ошибки сохранения поля не были пустыми
                }
            }

            if (isset($url)) {
                $this->setAppException($this->app->GetException());
                LocalRedirect($url);
            }

        } else {
            $helperFields = $this->getFields();
            $select = array_keys($helperFields);

            foreach ($select as $key => $field) {

                if (isset($helperFields[$field]['VIRTUAL'])
                    AND $helperFields[$field]['VIRTUAL'] == true
                    AND (!isset($helperFields[$field]['FORCE_SELECT']) OR $helperFields[$field]['FORCE_SELECT'] = false)
                ) {
                    unset($select[$key]);
                }
            }

            $this->data = $this->loadElement($select);
            if (!$this->data) {
                //TODO: элемент не найден
            }

            if (isset($_REQUEST['action'])) {
                $this->customActions($_REQUEST['action'],
                    $this->data[$this->pk()]);
            }
        }

        $this->setElementTitle();
    }

    /**
     * Заполняет верхнее меню страницы
     * По-умолчанию добавляет две кнопки:
     * <ul>
     * <li> Возврат в список</li>
     * <li> Удаление элемента</li>
     * </ul>
     *
     * Добавляя новые кнопки, нужно указывать параметр URl "action", который будет обрабатываться в
     * AdminEditHelper::customActions()
     *
     * @param bool $showDeleteButton управляет видимостью кнопки удаления элемента
     * @see AdminEditHelper::$menu
     * @see AdminEditHelper::customActions()
     * @api
     */
    protected function fillMenu($showDeleteButton = true)
    {
        $returnToList = array(
            "TEXT" => GetMessage('RETURN_TO_LIST'),
            "TITLE" => GetMessage('RETURN_TO_LIST'),
            "LINK" => $this->getListPageURL(array_merge($this->additionalUrlParams,
                array(
                    'restore_query' => 'Y'
                ))),
            "ICON" => "btn_list",
        );

        if (!empty($this->menu)) {
            array_unshift($this->menu, $returnToList);
        } else {
            $this->menu[] = $returnToList;
        }

        if ($showDeleteButton && isset($this->data[$this->pk()]) && $this->hasRights()) {
            $this->menu[] = array(
                "TEXT" => GetMessage('DELETE'),
                "TITLE" => GetMessage('DELETE'),
                "LINK" => static::getEditPageURL(array_merge($this->additionalUrlParams,
                    array(
                        'ID' => $this->data[$this->pk()],
                        'action' => 'delete',
                        'lang' => LANGUAGE_ID,
                        'restore_query' => 'Y',
                    ))),
            );
        }
    }

    /**
     * Выводит детальную страницу
     * @internal
     */
    public function show()
    {
        $this->fillMenu();
        $context = new \CAdminContextMenu($this->menu);
        $context->Show();

        $this->tabControl->BeginPrologContent();
        $this->showMessages();
        $this->showProlog();
        $this->tabControl->EndPrologContent();

        $this->tabControl->BeginEpilogContent();
        $this->showEpilog();
        $this->tabControl->EndEpilogContent();


        $query = $this->additionalUrlParams;
        if (isset($_REQUEST[$this->pk()])) {
            $query[$this->pk()] = $_REQUEST[$this->pk()];
        }

        $this->tabControl->Begin(array(
            'FORM_ACTION' => static::getEditPageURL($query)
        ));

        foreach ($this->tabs as $tabSettings) {
            if ($tabSettings['VISIBLE']) {
                $this->showTabElements($tabSettings);
            }
        }

        $this->tabControl->Buttons(array(
            "back_url" => $this->getListPageURL(array_merge($this->additionalUrlParams,
                array(
                    'lang' => LANGUAGE_ID,
                    'restore_query' => 'Y',
                )))
        ));
        $this->tabControl->ShowWarnings('editform', array()); //TODO: дописать
        $this->tabControl->Show();
    }

    /**
     * Отрисовка верхней части страницы.
     * @api
     */
    protected function showProlog()
    {

    }

    /**
     * Отрисовка нижней части страницы.
     * По-умолчанию рисует все поля, которые не попали в вывод, как input hidden
     * @api
     */
    protected function showEpilog()
    {
        $interfaceSettings = static::getInterfaceSettings();
        foreach ($interfaceSettings['FIELDS'] as $code => $settings) {
            if (!isset($settings['TAB']) AND
                isset($settings['FORCE_SELECT']) AND
                $settings['FORCE_SELECT'] == true
            ) {

                print '<input type="hidden" name="FIELDS[' . $code . ']" value="' . $this->data[$code] . '" />';
            }
        }
    }

    /**
     * Отрисовывает вкладку со всеми привязанными к ней полями.
     *
     * @param $tabSettings
     * @see AdminEditHelper::showField()
     * @internal
     */
    private function showTabElements($tabSettings)
    {
        $this->tabControl->BeginNextFormTab();
        foreach ($this->getFields() as $code => $fieldSettings) {
            $fieldOnCurrentTab = ((isset($fieldSettings['TAB']) AND $fieldSettings['TAB'] == $tabSettings['DIV']) OR $tabSettings['DIV'] == 'DEFAULT_TAB');

            if (!$fieldOnCurrentTab) {
                continue;
            }

            if (isset($fieldSettings['VISIBLE']) && $fieldSettings['VISIBLE'] === false) {
                continue;
            }

            $pkField = $code == $this->pk();
            if (isset($fieldSettings['USE_BX_API']) AND $fieldSettings['USE_BX_API'] == true) {
                $this->showField($code, $pkField);

            } else {
                $this->tabControl->BeginCustomField($code, $fieldSettings['TITLE']);
                $this->showField($code, $pkField);
                $this->tabControl->EndCustomField($code);
            }
        }
    }

    /**
     * Отрисовывает поле для редактирования, используя для этого виджет, указанный в настройках
     *
     * @param string $code код поля (название колонки в БД)
     * @param bool $isPKField является ли поле первичным ключом
     * @internal
     */
    protected function showField($code, $isPKField)
    {
        $widget = $this->createWidgetForField($code, $this->data);
        if ($widget) {
            $widget->genBasicEditField($isPKField);
        }
    }

    /**
     * Обработка запроса редактирования страницы
     * Этапы:
     * <ul>
     * <li> Проверка прав пользователя</li>
     * <li> Создание виджетов для каждого поля</li>
     * <li> Изменение данных модели каждым виджетом (исходя из его внутренней логики)</li>
     * <li> Валидация значений каждого поля соответствующим виджетом</li>
     * <li> Проверка на ошибики валидации</li>
     * <li> В случае неудачи - выход из функции</li>
     * <li> В случае успеха - обновление или добавление элемента в БД</li>
     * <li> Постобработка данных модели каждым виджетом</li>
     * </ul>
     *
     * @return bool
     * @see HelperWidget::processEditAction();
     * @see HelperWidget::processAfterSaveAction();
     * @internal
     */
    protected function editAction()
    {
        if (!$this->hasRights()) {
            $this->addErrors('Недостаточно прав для редактирования данных');

            return false;
        }
        $allWidgets = array();
        foreach ($this->getFields() as $code => $settings) {
            $widget = $this->createWidgetForField($code, $this->data);
            if ($widget) {
                $widget->processEditAction();
                $this->validationErrors = array_merge($this->validationErrors, $widget->getValidationErrors());
                $allWidgets[] = $widget;
            }
        }

        $this->addErrors($this->validationErrors);

        $success = empty($this->validationErrors);
        if ($success) {

            $existing = false;
            $id = isset($_REQUEST['FIELDS'][$this->pk()]) ? $_REQUEST['FIELDS'][$this->pk()] : $_REQUEST[$this->pk()];
            if ($id) {

                /** @var DataManager $className */
                $className = static::$model;
                // Если имеется primary key, то модель уже существующая, пытаемся найти ее в БД
                $existing = $className::getById($id)->fetch();

            }
            if ($existing) {
                $result = $this->saveElement($id);
            } else {
                $result = $this->saveElement();
            }

            if (!$result->isSuccess()) {
                return false;
            }
            foreach ($allWidgets as $widget) {
                /** @var HelperWidget $widget */
                $widget->setData($this->data);
                $widget->processAfterSaveAction();
            }

            return true;
        }

        return false;
    }

    /**
     * Функция загрузки элемента из БД.
     * Можно переопределить, если требуется сложная логика и нет возможности определить её в модели.
     *
     * @param array $select
     *
     * @return bool
     * @api
     */
    protected function loadElement($select = array())
    {
        if (isset($_REQUEST[$this->pk()])) {
            $className = static::getModel();
            $result = $className::getById($_REQUEST[$this->pk()]);

            return $result->fetch();
        }

        return false;
    }

    /**
     * Сохранение элемента.
     * Можно переопределить, если требуется сложная логика и нет возможности определить её в модели.
     *
     * @param bool $id
     * @return \Bitrix\Main\Entity\AddResult|\Bitrix\Main\Entity\UpdateResult
     * @throws \Exception
     * @api
     */
    protected function saveElement($id = false)
    {
        $className = static::getModel();

        if ($id) {
            $result = $className::update($id, $this->data);
        } else {
            $result = $className::add($this->data);
        }

        return $result;
    }

    /**
     * Удаление элемента.
     * Можно переопределить, если требуется сложная логика и нет возможности определить её в модели.
     *
     * @param $id
     * @return \Bitrix\Main\Entity\DeleteResult
     * @throws \Exception
     * @api
     */
    protected function deleteElement($id)
    {
        $className = static::getModel();
        $result = $className::delete($id);

        return $result;
    }

    /**
     * Выполнение кастомных операций над объектом в процессе редактирования
     *
     * @param string $action название операции
     * @param int|null $id ID элемента
     * @see AdminEditHelper::fillMenu()
     * @api
     */
    protected function customActions($action, $id)
    {
        if ($action == 'delete' AND !is_null($id)) {
            $this->deleteElement($id);

            LocalRedirect($this->getListPageURL(array_merge($this->additionalUrlParams,
                array(
                    'restore_query' => 'Y'
                ))));
        }
    }

    /**
     * Устанавливает заголовок исходя из данных текущего элемента
     *
     * @see $data
     * @see AdminBaseHelper::setTitle()
     * @api
     */
    protected function setElementTitle()
    {
        if (!empty($this->data)) {
            $title = $this->data[$this->pk()];
        } else {
            $title = "New element"; //FIXME: обернуть в ланги
        }

        $this->setTitle($title);
    }

    /**
     * @return \CAdminForm
     */
    public function getTabControl()
    {
        return $this->tabControl;
    }

}

