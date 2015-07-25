<?php

namespace AdminHelper;

use AdminHelper\Widget\HelperWidget;
use Bitrix\Main\Entity\DataManager;

IncludeModuleLangFile(__FILE__);

abstract class AdminEditHelper extends AdminBaseHelper
{
    /** @var array */
    protected $data;

    protected $tabs = array();
    protected $menu = array();
    protected $tabControl;


    /**
     * Производится инициализация переменных, обработка запросов на редактирование
     */
    public function __construct($fields, $tabs = array())
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

        $this->tabControl = new \CAdminForm(str_replace("\\", "",
            get_called_class()), $this->tabs);

        if (isset($_REQUEST['apply']) OR isset($_REQUEST['save'])) {
            $this->data = $_REQUEST['FIELDS'];
            foreach($fields as $name => $settings){
                if(is_a($settings['WIDGET'], 'AdminHelper\Widget\HLIBlockFieldWidget')){
                    $this->data = array_merge($this->data, $_REQUEST);
                    break;
                }
            }

            if ($this->editAction()) {
                if (isset($_REQUEST['apply'])) {
                    $id = $this->data[$this->pk()];
                    $url = $this->app->GetCurPageParam($this->pk().'='.$id.'&form_key='.$this->getFormKey());
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
                    $url = $this->app->GetCurPageParam($this->pk().'='.$id.'&form_key='.$this->getFormKey());
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
            $select = array_keys($this->fields);
            foreach ($select as $key => $field) {
                if (isset($this->fields[$field]['VIRTUAL'])
                    AND $this->fields[$field]['VIRTUAL'] == true
                    AND (!isset($this->fields[$field]['FORCE_SELECT']) OR $this->fields[$field]['FORCE_SELECT'] = false)
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
     * @param array $select
     *
     * @return bool
     */
    protected function loadElement($select = array())
    {
        if (isset($_REQUEST[$this->pk()])) {
            $className = static::$model;
            $result = $className::getById($_REQUEST[$this->pk()]);

            return $result->fetch();
        }

        return false;
    }

    protected function addMenu($showDeleteButton = true)
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
     */
    public function show()
    {
        $this->addMenu();
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

    protected function showProlog()
    {

    }

    /**
     * По-умолчанию рисует все поля, которые не попали в вывод, как input hidden
     */
    protected function showEpilog()
    {
        $interfaceSettings = static::getInterfaceSettings();
        foreach ($interfaceSettings['FIELDS'] as $code => $settings) {
            if (!isset($settings['TAB']) AND
                isset($settings['FORCE_SELECT']) AND
                $settings['FORCE_SELECT'] == true
            ) {

                print '<input type="hidden" name="FIELDS['.$code.']" value="'.$this->data[$code].'" />';
            }
        }
    }

    private function showTabElements($tabSettings)
    {
        $this->tabControl->BeginNextFormTab();
        foreach ($this->fields as $code => $fieldSettings) {
            $fieldOnCurrentTab = ((isset($fieldSettings['TAB']) AND $fieldSettings['TAB'] == $tabSettings['DIV']) OR $tabSettings['DIV'] == 'DEFAULT_TAB');

            if (!$fieldOnCurrentTab) {
                continue;
            }

            if (isset($fieldSettings['VISIBLE']) && $fieldSettings['VISIBLE'] === false) {
                continue;
            }

            $pkField = $code == $this->pk();
            if (isset($fieldSettings['USE_BX_API']) AND $fieldSettings['USE_BX_API'] == true) {
                $this->showField($code, $fieldSettings, $pkField);

            } else {
                $this->tabControl->BeginCustomField($code,
                    $fieldSettings['TITLE']);
                $this->showField($code, $fieldSettings, $pkField);
                $this->tabControl->EndCustomField($code);
            }
        }
    }

    /**
     * Отрисовывает поле для редактирования.
     * Используюся либо виджеты, либо HTML, генерируемый в переопределенных функциях.
     *
     * @param string $code
     * @param array  $settings
     * @param        $isPKField
     */
    protected function showField($code, $settings, $isPKField)
    {
        $widget = $this->createWidgetForField($code, $this->data);
        if ($widget) {
            $widget->genBasicEditField($isPKField);

        }
    }

    protected function editAction()
    {
        if (!$this->hasRights()) {
            $this->addErrors('Недостаточно прав для редактирования данных');

            return false;
        }
        $allWidgets = array();
        foreach ($this->fields as $code => $settings) {
            $widget = $this->createWidgetForField($code, $this->data);
            if ($widget) {
                $widget->processEditAction();
                $this->validationErrors = array_merge($this->validationErrors,
                    $widget->getValidationErrors());
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

            if (isset($_SESSION[$this->getFormKey()."_values"])) {
                unset($_SESSION[$this->getFormKey()."_values"]);
            }

            return true;
        }

        return false;
    }

    protected function saveElement($id = false)
    {
        /** @var DataManager $className */
        $className = static::$model;

        if ($id) {
            $result = $className::update($id, $this->data);
        } else {
            $result = $className::add($this->data);
        }

        return $result;
    }

    protected function customActions($action, $id)
    {
        if ($action == 'delete') {
            /**@var DataManager $className */
            $className = static::$model;
            $className::delete($id);
            LocalRedirect($this->getListPageURL(array_merge($this->additionalUrlParams,
                array(
                    'restore_query' => 'Y'
                ))));
        }
    }

    /**
     * Устанавливает заголовок исходя из содержимого текущего елемента
     *
     * @see $element
     */
    protected function setElementTitle()
    {
        return;
    }

    /**
     * @return \CAdminForm
     */
    public function getTabControl()
    {
        return $this->tabControl;
    }

    private $currentFormKey = null;

    private function getFormKey()
    {
        $key = null;
        if (isset($_REQUEST['form_key'])) {
            $key = $_REQUEST['form_key'];
        } else {
            if (isset($this->currentFormKey)) {
                $key = $this->currentFormKey;
            } else {
                $key = $this->tabControl->unique_name."_";
                $suffix = 0;

                while (isset($_SESSION[md5($key.$suffix)])) {
                    ++$suffix;
                }

                $key = md5($key.$suffix);
            }
        }

        $_SESSION[$key] = $key;
        $this->currentFormKey = $key;

        return $key;
    }
}

