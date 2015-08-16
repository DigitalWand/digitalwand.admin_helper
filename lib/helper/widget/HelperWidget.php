<?php
namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Localization\Loc;
use DigitalWand\AdminHelper\AdminBaseHelper;
use DigitalWand\AdminHelper\AdminEditHelper;
use DigitalWand\AdminHelper\AdminListHelper;
use Bitrix\Main\Entity\DataManager;


Loc::loadMessages(__FILE__);

/**
 * Class HelperWidget
 * @package AdminHelper\Widget
 *
 * Виджет - класс, отвечающий за внешний вид отдельно взятого поля сущности. Один виджет отвечает за:
 * <ul>
 * <li>Отображение поля на странице редактирования</li>
 * <li>Отображение ячейки поля в таблице списка - при просмотре и редактировании</li>
 * <li>Отображение фильтра по данному полю</li>
 * <li>Валидацию значения поля</li>
 * </ul>
 *
 * Также виджетами осуществляется предварительная обработка данных:
 * <ul>
 * <li>Перед сохранением значения поля в БД</li>
 * <li>После получения значения поля из БД</li>
 * <li>Модификация запроса перед фильтрацией</li>
 * <li>Модификация пуеДшые перед выборкой данных</li>
 * </ul>
 *
 * Для получения минимальной функциональности достаточно переопределить основные методы, отвечающие за отображение
 * виджета в списке и на детальной.
 *
 * Каждый виджет имеет ряд специфических настроек, некоторые из которых обязательны для заполнения. Подробную
 * документацию по настройкам стоит искать в документации к конкретному виджету. Настройки могут быть переданы в
 * виджет как при описании всего интерфейса в файле Interface.php, так и непосредственно во время исполнения,
 * внутри Helper-классов.
 *
 * При указании настроек типа "да"/"нет", нельзя использовать строковые обозначения "Y"/"N":
 * для этого есть булевы true и false.
 *
 * Настройки базового класса:
 * <ul>
 * <li><b>HIDE_WHEN_CREATE</b> - скрывает поле в форме редактирования, если создаётся новый элемент, а не открыт
 *     существующий на редактирование.</li>
 * <li><b>TITLE</b> - название поля. Будет использовано в фильтре, заголовке таблицы и в качестве подписи поля на
 *     странице редактирования</li>
 * <li><b>REQUIRED</b> - является ли поле обязательным.</li>
 * <li><b>READONLY</b> - поле нельзя редактировать, предназначено только для чтения</li>
 * <li><b>FILTER</b> - позволяет указать способ фильтрации по полю. В базовом классе возможен только вариант "BETWEEN"
 *     или "><". И в том и в другом случае это будет означать фильтрацию по диапазону значений. Количество возможных
 *     вариантов этого параметра может быть расширено в наследуемых классах</li>
 * <li><b>UNIQUE</b> - поле должно содержать только уникальные значения</li>
 * <li><b>VIRTUAL</b> - особая настройка, отражается как на поведении виджета, так и на поведении хэлперов. Поле,
 *     объявленное виртуальным, отображается в графическом интерфейче, однако не участвоует в запросах к БД. Опция
 *     может быть полезной при реализации нестандартной логики, когда, к примеру, под именем одного поля могут
 *     выводиться данные из нескольких полей сразу. </li>
 * <li><b>EDIT_IN_LIST</b> - параметр не обрабатывается непосредственно виджетом, однако используется хэлпером.
 *     Указывает, можно ли редактировать данное поле в спискке</li>
 * </ul>
 *
 * @see HelperWidget::genEditHTML()
 * @see HelperWidget::genListHTML()
 * @see HelperWidget::genFilterHTML()
 * @see HelperWidget::setSetting()
 */
abstract class HelperWidget
{
    const LIST_HELPER = 1;
    const EDIT_HELPER = 2;

    /**
     * @var string
     * Информация о том, во время выполнения какой операции вызываются функции виджета.
     */
    public $context;

    /**
     * @var string
     * Название поля ("символьный код")
     */
    protected $code;

    /**
     * @var array $settings
     * Настройки виджета для данной модели
     */
    protected $settings;

    /**
     * @var array
     * Настройки "по-умолчанию" для модели
     */
    static protected $defaults;

    /**
     * @var DataManager
     * Название класса модели
     */
    protected $entityName;

    /**
     * @var array
     * Данные модели
     */
    protected $data;

    /** @var  AdminBaseHelper|AdminListHelper|AdminEditHelper $helper
     * Экземпляр хэлпера, вызывающий данный виджет
     */
    protected $helper;

    /**
     * @var array $validationErrors
     * Ошибки валидации поля
     */
    protected $validationErrors = array();

    /**
     * @var string
     * Строка, добавляемая к полю name полей фильтра
     */
    protected $filterFieldPrefix = 'find_';

    /**
     * @param array $settings
     * Эксемпляр виджета создаётся всего один раз, при описании настроек интерфейса. При создании есть возможность
     * сразу указать для него необходимые настройки
     */
    public function __construct($settings = array())
    {
        $this->settings = $settings;
    }

    /**
     * Генерирует HTML для редактирования поля
     *
     * @return string
     * @api
     */
    abstract protected function genEditHTML();

    /**
     * Оборачивает поле в HTML код, который в большинстве случаев менять не придется.
     * Далее вызывается кастомизируемая часть.
     *
     * @param $isPKField - является ли поле первичным ключом модели
     *
     * @see HelperWidget::genEditHTML();
     */
    public function genBasicEditField($isPKField)
    {
        if ($this->getSettings('HIDE_WHEN_CREATE') AND !isset($this->data['ID'])) {
            return;
        }

        if ($this->getSettings('USE_BX_API')) {
            $this->genEditHTML();

        } else {
            print '<tr>';
            $title = $this->getSettings('TITLE');
            if ($this->getSettings('REQUIRED') === true) {
                $title = '<b>' . $title . '</b>';
            }
            print '<td width="40%" style="vertical-align: top;">' . $title . ':</td>';

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

            print '<td width="60%">' . $field . '</td>';
            print '</tr>';
        }
    }

    /**
     * Возвращает значение поля в форме "только для чтения".
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
     * @param \CAdminListRow $row
     * @param array $data - данные текущей строки
     *
     * @return void
     * @api
     */
    abstract public function genListHTML(&$row, $data);

    /**
     * Генерирует HTML для поля фильтрации
     *
     * @see AdminListHelper::createFilterForm();
     * @return void
     * @api
     */
    abstract public function genFilterHTML();

    /**
     * Возвращает массив настроек данного виджета, либо значение отдельного параметра, если указано его имя
     *
     * @param string $name название конкретного параметра
     *
     * @return array|mixed
     * @api
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
     * Передаёт в виджет ссылку на вызывающий его объект.
     * @param AdminBaseHelper $helper
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
        if (isset($GLOBALS[$this->filterFieldPrefix . $this->code])) {
            return htmlspecialcharsbx($GLOBALS[$this->filterFieldPrefix . $this->code]);
        } else {
            return false;
        }
    }

    /**
     * Проверяет корректность введенных в фильтр значений
     *
     * @param string $operationType тип операции
     * @param mixed $value значение фильтра
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
     * Если в настройках явно указан способ фильтрации, до добавляет соответствующий префикс в $arFilter.
     * Если фильтр BETWEEN, то формирует сложную логику фильтрации.
     *
     * @param array $filter $arFilter целиком
     * @param array $select
     * @param       $sort
     * @param array $raw $arSelect, $arFilter, $arSort до примененных к ним преобразований.
     *
     * @see AdlinListHelper::getData();
     */
    public function changeGetListOptions(&$filter, &$select, &$sort, $raw)
    {
        if ($this->isFilterBetween()) {
            $field = $this->getCode();
            $from = $to = false;

            if (isset($_REQUEST['find_' . $field . '_from'])) {
                $from = $_REQUEST['find_' . $field . '_from'];
                if (is_a($this, 'DateWidget')) {
                    $from = date('Y-m-d H:i:s', strtotime($from));
                }
            }
            if (isset($_REQUEST['find_' . $field . '_to'])) {
                $to = $_REQUEST['find_' . $field . '_to'];
                if (is_a($this, 'DateWidget')) {
                    $to = date('Y-m-d 23:59:59', strtotime($to));
                }
            }

            if ($from !== false AND $to !== false) {
                $filter['><' . $field] = array($from, $to);
            } else {
                if ($from !== false) {
                    $filter['>' . $field] = $from;
                } else {
                    if ($to !== false) {
                        $filter['<' . $field] = $to;
                    }
                }
            }

        } else if ($filterPrefix = $this->getSettings('FILTER') AND $filterPrefix !== true AND isset($filter[$this->getCode()])) {
            $filter[$filterPrefix . $this->getCode()] = $filter[$this->getCode()];
            unset($filter[$this->getCode()]);
        }
    }

    /**
     * Проверяет оператор фильтрации
     * @return bool
     */
    protected function isFilterBetween()
    {
        return $this->getSettings('FILTER') === '><' OR $this->getSettings('FILTER') === 'BETWEEN';
    }

    /**
     * Действия, выполняемые над полем в процессе редактирования элемента, до его сохранения.
     * По-умолчанию выполняется проверка обязательных полей и уникальности.
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

    /**
     * В совсем экзотических случаях может потребоваться моджифицировать значение поля уже после его сохраненния в БД -
     * для последующей обработки каким-либо другим классом.
     */
    public function processAfterSaveAction()
    {
    }

    /**
     * Добавляет строку ошибки в массив ошибок.
     * @param string $messageId сообщение об ошибке. Плейсхолдер #FIELD# будет заменён на значение параметра TITLE
     * @see Loc::getMessage()
     */
    protected function addError($messageId)
    {
        $this->validationErrors[$this->getCode()] = Loc::getMessage($messageId, array('#FIELD#' => $this->getSettings('TITLE')));
    }

    /**
     * Проверка заполненности обязательных полей.
     * Не должны быть null или содержать пустую строку.
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
     * @param string $code
     * Выставляет код для данного виджета при инициализации. Перегружает настройки.
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
     * Устанавливает настройки интерфейса для текущего поля
     *
     * @see AdminBaseHelper::getInterfaceSettings();
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
        $this->settings = array_merge($this->settings, $interface['FIELDS'][$code]);
        $this->setDefaultValue();

        return true;
    }

    /**
     * Возвращает название сущности данной модели
     * @return string|DataManager
     */
    public function getEntityName()
    {
        return $this->entityName;
    }

    /**
     * @param string $entityName
     */
    public function setEntityName($entityName)
    {
        $this->entityName = $entityName;
        $this->setDefaultValue();
    }

    /**
     * Устанавливает значение по-умолчанию для данного поля
     */
    public function setDefaultValue()
    {
        if (isset($this->settings['DEFAULT']) && is_null($this->getValue())) {
            $this->setValue($this->settings['DEFAULT']);
        }
    }

    /**
     * Передает ссылку на данные сущности в виджет
     * @param $data
     */
    public function setData(&$data)
    {
        $this->data = &$data;
    }

    /**
     * Возвращает текущее значение, хранимое в поле виджета
     * Если такого поля нет, возвращает null
     *
     * @return mixed|null
     */
    public function getValue()
    {
        $code = $this->getCode();

        return isset($this->data[$code]) ? $this->data[$code] : null;
    }

    /**
     * Устанавливает значение поля
     * @param $value
     * @return bool
     */
    protected function setValue($value)
    {
        $code = $this->getCode();
        $this->data[$code] = $value;

        return true;
    }

    /**
     * Выставляет значение отдельной настройки
     * @param string $name
     * @param mixed $value
     */
    public function setSetting($name, $value)
    {
        $this->settings[$name] = $value;
    }

    /**
     * Возвращает собранные ошибки валидации
     * @return array
     */
    public function getValidationErrors()
    {
        return $this->validationErrors;
    }

    /**
     * Возвращает имена для атрибута name полей фильтра.
     * Если это фильтр BETWEEN, то вернёт массив с вариантами from и to.
     *
     * @return array|string
     */
    protected function getFilterInputName()
    {
        if ($this->isFilterBetween()) {
            $baseName = $this->filterFieldPrefix . $this->code;;
            $inputNameFrom = $baseName . '_from';
            $inputNameTo = $baseName . '_to';

            return array($inputNameFrom, $inputNameTo);

        } else {
            return $this->filterFieldPrefix . $this->code;
        }
    }

    /**
     * Возвращает текст для атрибута name инпута редактирования.
     * @param null $suffix опциональное дополнение к названию поля
     *
     * @return string
     */
    protected function getEditInputName($suffix = null)
    {
        return 'FIELDS[' . $this->getCode() . $suffix . ']';
    }

    /**
     * Возвращает текст для атрибута name инпута редактирования поля в списке
     * @return string
     */
    protected function getEditableListInputName()
    {
        $id = $this->data['ID'];

        return 'FIELDS[' . $id . '][' . $this->getCode() . ']';
    }

    /**
     * Определяет тип вызывающего хэлпера, от чего может зависить поведение виджета.
     *
     * @return bool|int
     * @see HelperWidget::EDIT_HELPER
     * @see HelperWidget::LIST_HELPER
     */
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

    /**
     * Проверяет значение поля на уникальность
     * @return bool
     */
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
            $filter["!=" . $idField] = $id;
        }

        $count = $class::getCount($filter);

        if (!$count) {
            return true;
        }

        return false;
    }

    /**
     * Проверяет, не является ли текущий запрос попыткой выгрузить данные в Excel
     * @return bool
     */
    protected function isExcelView()
    {
        if (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'excel') {
            return true;
        }

        return false;
    }
}