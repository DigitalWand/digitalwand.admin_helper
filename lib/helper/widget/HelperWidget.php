<?php
namespace AdminHelper\Widget;

use AdminHelper\AdminBaseHelper;
use AdminHelper\AdminEditHelper;
use AdminHelper\AdminListHelper;
use Bitrix\Main\Entity\DataManager;

IncludeModuleLangFile(__FILE__);

abstract class HelperWidget
{
    const LIST_HELPER = 1;
    const EDIT_HELPER = 2;

    /**
     * @var string Название поля ("символьный код")
     */
    protected $code;

    /**
     * @var array $settings Настройки виджета для данной модели
     */
    protected $settings;
    static protected $defaults;

    /**
     * @var bool $editable Является ли поле редактируемым
     */
    protected $editable;

    /**
     * @var string Название класса модели
     */
    protected $entityName;

    protected $data;

    /** @var  AdminBaseHelper|AdminListHelper|AdminEditHelper $helper */
    protected $helper;

    /**
     * @var array $validationErrors Ошибки валидации поля
     */
    protected $validationErrors = array();

    protected $filterFieldPrefix = 'find_';

    /**
     * @var bool флаг, означающий, что интерфейс редактирования будет генерироваться с использованием API CAdminPage
     * В этом случае поля не нужно оборачивать в некоторые теги, некоторые функции не нужно запускать.
     * @see CAdminPage
     */
    static public $useBxAPI = false;

    public function __construct($settings = array())
    {
        $this->settings = $settings;
    }

    /**
     * Генерирует HTML для редактирования поля
     *
     * @see AdminEditHelper::showField();
     * @return mixed
     */
    abstract protected function genEditHTML();

    /**
     * Оборачивает поле в HTML код, который в большинстве случаев менять не придется.
     * Далее вызывается кастомизируемая часть.
     * Может выводить как единичные, так и множественные поля
     *
     * @param $isPKField - является ли поле первичным ключом модели
     *
     * @see AdminEditHelper::showField();
     * @see HelperWidget::genEditHTML();
     */
    public function genBasicEditField($isPKField)
    {
        if ($this->getSettings('HIDE_WHEN_CREATE') AND !isset($this->data['ID'])) {
            return;
        }

        if (static::$useBxAPI) {
            $this->genEditHTML();

        } else {
            print '<tr>';
            $title = $this->getSettings('TITLE');
            if ($this->getSettings('REQUIRED') === true) {
                $title = '<b>'.$title.'</b>';
            }
            print '<td width="40%" style="vertical-align: top;">'.$title.':</td>';

            $field = $this->getValue();
            if (is_null($field)) {
                $field = '';
            }

            $readOnly = $this->getSettings('READONLY');

            if (!$readOnly AND !$isPKField) {
                $field = $this->genEditHTML();

            } else {
                if ($readOnly) {
                    $field = $this->getValueReadonly();
                }
            }

            print '<td width="60%">'.$field.'</td>';
            print '</tr>';
        }
    }

    /**
     * Возвращает значение поля в форме "только для чтения".
     * Нужно для виджетов, у которых значение поля не совпадает с тем, как оно должно отображаться.
     *
     * @return mixed
     */
    protected function getValueReadonly()
    {
        return $this->getValue();
    }

    /**
     * Генерирует HTML для поля в списке
     *
     * @see AdminListHelper::addRowCell();
     *
     * @param CAdminListRow $row
     * @param array         $data - данные текущей строки
     *
     * @return mixed
     */
    abstract public function genListHTML(&$row, $data);

    /**
     * Генерирует HTML для поля фильтрации
     *
     * @see AdminListHelper::createFilterForm();
     * @return mixed
     */
    abstract public function genFilterHTML();

    /**
     * Возвращает массив настроек данного виджета или значение отдельного параметра
     *
     * @param string $name название конкретного параметра
     *
     * @return array|mixed
     */
    public function getSettings($name = '')
    {
        if (empty($name)) {
            return $this->settings;
        } else {
            if (isset($this->settings[$name])) {
                return $this->settings[$name];
            } else {
                if (isset(static::$defaults[$name])) {
                    return static::$defaults[$name];
                } else {
                    return false;
                }
            }
        }
    }

    /**
     * @param mixed $helper
     */
    public function setHelper(&$helper)
    {
        $this->helper = $helper;
    }

    /**
     * Возвращает текукщее значение поля фильтрации (спец. символы экранированы)
     *
     * @return bool|string
     */
    protected function getCurrentFilterValue()
    {
        if (isset($GLOBALS[$this->filterFieldPrefix.$this->code])) {
            return htmlspecialcharsbx($GLOBALS[$this->filterFieldPrefix.$this->code]);
        } else {
            return false;
        }
    }

    /**
     * Проверяет корректность введенных в фильтр значений
     *
     * @param string $operationType тип операции
     * @param mixed  $value         значение фильтра
     *
     * @see AdminListHelper::checkFilter();
     * @return bool
     */
    public function checkFilter($operationType, $value)
    {
        return true;
    }

    /**
     * Позволяет модифицировать опции, передаваемые в getList, непосредственно перед выборкой.
     *
     * @param array $filter $arFilter целиком
     * @param       $select
     * @param       $sort
     * @param array $raw    $arSelect, $arFilter, $arSort до примененных к ним преобразований.
     *
     * @see AdlinListHelper::getData();
     */
    public function changeGetListOptions(&$filter, &$select, &$sort, $raw)
    {
        if ($this->isFilterBetween()) {
            $field = $this->getCode();
            $from = $to = false;

            if (isset($_REQUEST['find_'.$field.'_from'])) {
                $from = $_REQUEST['find_'.$field.'_from'];
                if (is_a($this, 'DateWidget')) {
                    $from = date('Y-m-d H:i:s', strtotime($from));
                }
            }
            if (isset($_REQUEST['find_'.$field.'_to'])) {
                $to = $_REQUEST['find_'.$field.'_to'];
                if (is_a($this, 'DateWidget')) {
                    $to = date('Y-m-d 23:59:59', strtotime($to));
                }
            }

            if ($from !== false AND $to !== false) {
                $filter['><'.$field] = array($from, $to);
            } else {
                if ($from !== false) {
                    $filter['>'.$field] = $from;
                } else {
                    if ($to !== false) {
                        $filter['<'.$field] = $to;
                    }
                }
            }
        }
    }

    protected function isFilterBetween()
    {
        return $this->getSettings('FILTER') === '><' OR $this->getSettings('FILTER') === 'BETWEEN';
    }

    /**
     * Действия, выполняемые над полем в процессе редактирования элемента, до его сохранения.
     * По-умолчанию выполняется проверка обязательных полей.
     *
     * @see AdminEditHelper::editAction();
     * @see AdminListHelper::editAction();
     */
    public function processEditAction()
    {
        if (!$this->checkRequired()) {
            $this->addError('REQUIRED_FIELD_ERROR');
        }
        if ($this->getSettings('UNIQUE') && !$this->isUnique()) {
            $this->addError('DUPLICATE_FIELD_ERROR');
        }
    }

    public function processAfterSaveAction()
    {
    }

    protected function addError($messageId)
    {
        $this->validationErrors[$this->getCode()] = GetMessage($messageId,
            array(
                '#FIELD#' => $this->getSettings('TITLE')
            ));
    }

    /**
     * Проверка заполненности обязательных полей.
     *
     * @return bool
     */
    public function checkRequired()
    {
        if ($this->getSettings('REQUIRED') == true) {
            $value = $this->getValue();

            return !is_null($value) && !empty($value);
        } else {
            return true;
        }
    }

    /**
     * Устанавливает настройки интерфейса для текущего поля
     *
     * @see OrmModel::getInterfaceSettings();
     * @see AdminBaseHelper::setFields();
     *
     * @param string $code
     *
     * @return bool
     */
    public function loadSettings($code = null)
    {

        $interface = $this->helper->getInterfaceSettings();

        $code = is_null($code) ? $this->code : $code;

        if (!isset($interface['FIELDS'][$code])) {
            return false;
        }
        unset($interface['FIELDS'][$code]['WIDGET']);
        $this->settings = array_merge($this->settings,
            $interface['FIELDS'][$code]);

        if (isset($this->settings['DEFAULT']) && is_null($this->getValue())) {
            $this->setValue($this->settings['DEFAULT']);
        }

        return true;
    }

    /**
     * @param string $code
     */
    public function setCode($code)
    {
        $this->code = $code;
        $this->loadSettings();
    }

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getEntityName()
    {
        return $this->entityName;
    }

    /**
     * @param $entityName
     */
    public function setEntityName($entityName)
    {
        $this->entityName = $entityName;

        if (isset($this->settings['DEFAULT']) && is_null($this->getValue())) {
            $this->setValue($this->settings['DEFAULT']);
        }
    }

    public function setData(&$data)
    {
        $this->data = &$data;
    }

    /**
     * Возвращает текущее значение, хранимое в поле виджета
     * Если такого поля нет, возвращает null
     *
     * @return mixed
     */
    public function getValue()
    {
        $code = $this->getCode();

        return isset($this->data[$code]) ? $this->data[$code] : null;
    }

    protected function setValue($value)
    {
        $code = $this->getCode();
        $this->data[$code] = $value;

        return true;
    }

    public function setSetting($name, $value)
    {
        $this->settings[$name] = $value;
    }

    /**
     * @param boolean $editable
     */
    public function setEditable($editable)
    {
        $this->editable = $editable;
    }

    /**
     * @return boolean
     */
    public function getEditable()
    {
        return $this->editable;
    }

    /**
     * @return array
     */
    public function getValidationErrors()
    {
        return $this->validationErrors;
    }

    protected function getFilterInputName()
    {
        if ($this->isFilterBetween()) {
            $baseName = $this->filterFieldPrefix.$this->code;;
            $inputNameFrom = $baseName.'_from';
            $inputNameTo = $baseName.'_to';

            return array($inputNameFrom, $inputNameTo);

        } else {
            return $this->filterFieldPrefix.$this->code;
        }
    }

    protected function getEditInputName($suffix = null)
    {
        return 'FIELDS['.$this->getCode().$suffix.']';
    }

    protected function getEditableListInputName()
    {
        $id = $this->data['ID'];

        return 'FIELDS['.$id.']['.$this->getCode().']';
    }

    protected function randSuffix($max = 1000)
    {
        return '_'.rand(0, $max);
    }

    protected function getCurrentViewType()
    {
        if (is_a($this->helper, 'AdminListHelper')) {
            return self::LIST_HELPER;
        } else {
            if (is_a($this->helper, 'AdminEditHelper')) {
                return self::EDIT_HELPER;
            }
        }

        return false;
    }

    public function setRequired($required = true)
    {
        $this->settings['REQUIRED'] = $required;
    }

    private function isUnique()
    {
        if ($this->getSettings('VIRTUAL')) {
            return true;
        }

        $value = $this->getValue();
        if (empty($value)) {
            return true;
        }

        /** @var DataManager $class */
        $class = $this->entityName;
        $field = $this->getCode();
        $idField = 'ID';
        $id = $this->data[$idField];

        $filter = array(
            $field => $value,
        );

        if (!empty($id)) {
            $filter["!=".$idField] = $id;
        }

        $count = $class::getCount($filter);

        if (!$count) {
            return true;
        }

        return false;
    }

    protected function isExcelView()
    {
        if (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'excel') {
            return true;
        }

        return false;
    }
}