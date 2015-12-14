<?php

namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Localization\Loc;
use DigitalWand\AdminHelper\Helper\AdminBaseHelper;
use DigitalWand\AdminHelper\Helper\AdminEditHelper;
use DigitalWand\AdminHelper\Helper\AdminListHelper;
use Bitrix\Main\Entity\DataManager;

/**
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
 * <li><b>TITLE</b> - название поля. Если не задано то возьмется значение title из DataManager::getMap()
 *       через getField($code)->getTitle(). Будет использовано в фильтре, заголовке таблицы и в качестве подписи поля
 *     на
 *     странице редактирования.</li>
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
 * <li><b>MULTIPLE</b> - bool является ли поле множественным</li>
 * <li><b>MULTIPLE_FIELDS</b> - bool поля используемые в хранилище множественных значений и их алиасы</li>
 * </ul>
 *
 * Как сделать виджет множественным?
 * <ul>
 * <li>Реализуйте метод genMultipleEditHTML(). Метод должен выводить множественную форму ввода. Для реализации формы
 * ввода есть JS хелпер HelperWidget::jsHelper()</li>
 * <li>Опишите поля, которые будут переданы связи в EntityManager. Поля описываются в настройке "MULTIPLE_FIELDS"
 *     виджета. По умолчанию множественный виджет использует поля ID, ENTITY_ID, VALUE</li>
 * <li>Полученные от виджета данные будут переданы в EntityManager и сохранены как связанные данные</li>
 * </ul>
 * Пример реализации можно увидеть в виджете StringWidget
 *
 * Как использовать множественный виджет?
 * <ul>
 * <li>
 * Создайте таблицу и модель, которая будет хранить данные поля
 * - Таблица обязательно должна иметь поля, которые требует виджет.
 * Обязательные поля виджета по умолчанию описаны в: HelperWidget::$settings['MULTIPLE_FIELDS']
 * Если у виджета нестандартный набор полей, то они хранятся в: SomeWidget::$settings['MULTIPLE_FIELDS']
 * - Если поля, которые требует виджет есть в вашей таблице, но они имеют другие названия,
 * можно настроить виджет для работы с вашими полями.
 * Для этого переопределите настройку MULTIPLE_FIELDS при объявлении поля в интерфейсе следующим способом:
 * ```
 * 'RELATED_LINKS' => array(
 *        'WIDGET' => new StringWidget(),
 *        'TITLE' => 'Ссылки',
 *        'MULTIPLE' => true,
 *        // Обратите внимание, именно тут переопределяются поля виджета
 *        'MULTIPLE_FIELDS' => array(
 *            'ID', // Должны быть прописаны все поля, даже те, которые не нужно переопределять
 *            'ENTITY_ID' => 'NEWS_ID', // ENTITY_ID - поле, которое требует виджет, NEWS_ID - пример поля, которое
 *     будет использоваться вместо ENTITY_ID
 *            'VALUE' => 'LINK', // VALUE - поле, которое требует виджет, LINK - пример поля, которое будет
 *     использоваться вместо VALUE
 *        )
 *    ),
 * ```
 * </li>
 *
 * <li>
 * Далее в основной модели (та, которая указана в AdminBaseHelper::$model) нужно прописать связь с моделью,
 * в которой вы хотите хранить данные поля
 * Пример объявления связи:
 * ```
 * new Entity\ReferenceField(
 *        'RELATED_LINKS',
 *        'namespace\NewsLinksTable',
 *        array('=this.ID' => 'ref.NEWS_ID'),
 *          // Условия FIELD и ENTITY не обязательны, подробности смотрите в комментариях к классу @see EntityManager
 *        'ref.FIELD' => new DB\SqlExpression('?s', 'NEWS_LINKS'),
 *        'ref.ENTITY' => new DB\SqlExpression('?s', 'news'),
 * ),
 * ```
 * </li>
 *
 * <li>
 * Что бы виджет работал во множественном режиме, нужно при описании интерфейса поля указать параметр MULTIPLE => true
 * ```
 * 'RELATED_LINKS' => array(
 *        'WIDGET' => new StringWidget(),
 *        'TITLE' => 'Ссылки',
 *        // Включаем режим множественного ввода
 *        'MULTIPLE' => true,
 * )
 * ```
 * </li>
 *
 * <li>
 * Готово :)
 * </li>
 * </ul>
 *
 * О том как сохраняются данные множественных виджетов можно узнать из комментариев 
 * класса \DigitalWand\AdminHelper\EntityManager.
 *
 * @see EntityManager
 * @see HelperWidget::getEditHtml()
 * @see HelperWidget::generateRow()
 * @see showFilterHtml::showFilterHTML()
 * @see HelperWidget::setSetting()
 * 
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 * @author Dmitriy Baibuhtin <dmitriy.baibuhtin@ya.ru>
 */
abstract class HelperWidget
{
    const LIST_HELPER = 1;
    const EDIT_HELPER = 2;

    /**
     * @var string Код поля.
     */
    protected $code;
    /**
     * @var array $settings Настройки виджета для данной модели.
     */
    protected $settings = array(
        // Поля множественного виджета по умолчанию (array('ОРИГИНАЛЬНОЕ НАЗВАНИЕ', 'ОРИГИНАЛЬНОЕ НАЗВАНИЕ' => 'АЛИАС'))
        'MULTIPLE_FIELDS' => array('ID', 'VALUE', 'ENTITY_ID')
    );
    /**
     * @var array Настройки "по-умолчанию" для модели.
     */
    static protected $defaults;
    /**
     * @var DataManager Название класса модели.
     */
    protected $entityName;
    /**
     * @var array Данные модели.
     */
    protected $data;
    /** @var  AdminBaseHelper|AdminListHelper|AdminEditHelper $helper Экземпляр хэлпера, вызывающий данный виджет.
     */
    protected $helper;
    /**
     * @var bool Статус отображения JS хелпера. Используется для исключения дублирования JS-кода.
     */
    protected $jsHelper = false;
    /**
     * @var array $validationErrors Ошибки валидации поля.
     */
    protected $validationErrors = array();
    /**
     * @var string Строка, добавляемая к полю name полей фильтра.
     */
    protected $filterFieldPrefix = 'find_';

    /**
     * Эксемпляр виджета создаётся всего один раз, при описании настроек интерфейса. При создании есть возможность
     * сразу указать для него необходимые настройки.
     * 
     * @param array $settings
     */
    public function __construct(array $settings = array())
    {
        Loc::loadMessages(__FILE__);
        
        $this->settings = $settings;
    }

    /**
     * Генерирует HTML для редактирования поля.
     *
     * @return string
     * 
     * @api
     */
    abstract protected function getEditHtml();

    /**
     * Генерирует HTML для редактирования поля в мульти-режиме.
     *
     * @return string
     * 
     * @api
     */
    protected function getMultipleEditHtml()
    {
        return Loc::getMessage('DIGITALWAND_AH_MULTI_NOT_SUPPORT');
    }

    /**
     * Оборачивает поле в HTML код, который в большинстве случаев менять не придется. Далее вызывается 
     * кастомизируемая часть.
     *
     * @param bool $isPKField Является ли поле первичным ключом модели.
     *
     * @see HelperWidget::getEditHtml();
     */
    public function showBasicEditField($isPKField)
    {
        if ($this->getSettings('HIDE_WHEN_CREATE') AND !isset($this->data['ID'])) {
            return;
        }

        // JS хелперы
        $this->jsHelper();

        if ($this->getSettings('USE_BX_API')) {
            $this->getEditHtml();
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
                if ($this->getSettings('MULTIPLE')) {
                    $field = $this->getMultipleEditHtml();
                } else {
                    $field = $this->getEditHtml();
                }
            } else {
                if ($readOnly) {
                    if ($this->getSettings('MULTIPLE')) {
                        $field = $this->getMultipleValueReadonly();
                    } else {
                        $field = $this->getValueReadonly();
                    }
                }
            }

            print '<td width="60%">' . $field . '</td>';
            print '</tr>';
        }
    }

    /**
     * Возвращает значение поля в форме "только для чтения" для не множественных свойств.
     *
     * @return mixed
     */
    protected function getValueReadonly()
    {
        return static::prepareToOutput($this->getValue());
    }

    /**
     * Возвращает значения множественного поля.
     * 
     * @return array
     */
    protected function getMultipleValue()
    {
        $rsEntityData = null;
        $values = array();
        if (!empty($this->data['ID'])) {
            $entityName = $this->entityName;
            $rsEntityData = $entityName::getList(array(
                'select' => array('REFERENCE_' => $this->getCode() . '.*'),
                'filter' => array('=ID' => $this->data['ID'])
            ));

            if ($rsEntityData) {
                while ($referenceData = $rsEntityData->fetch()) {
                    if (empty($referenceData['REFERENCE_' . $this->getMultipleField('ID')])) {
                        continue;
                    }
                    $values[] = $referenceData['REFERENCE_' . $this->getMultipleField('VALUE')];
                }
            }
        } else {
            if ($this->data[$this->code]) {
                $values = $this->data[$this->code];
            }
        }

        return $values;
    }

    /**
     * Возвращает значение поля в форме "только для чтения" для множественных свойств.
     *
     * @return string
     */
    protected function getMultipleValueReadonly()
    {
        $values = $this->getMultipleValue();

        foreach ($values as &$value) {
            $value = static::prepareToOutput($value);
        }

        return join('<br/>', $values);
    }

    /**
     * Обработка строки для безопасного отображения. Если нужно отобразить текст как аттрибут тега, 
     * используйте static::prepareToTag().
     *
     * @param string $string
     * @param bool $hideTags Скрыть теги:
     * 
     * - true - вырезать теги оставив содержимое. Результат обработки: <b>text</b> = text
     * 
     * - false - отобразаить теги в виде текста. Результат обработки: <b>text</b> = &lt;b&gt;text&lt;/b&gt;
     *
     * @return string
     */
    public static function prepareToOutput($string, $hideTags = true)
    {
        if ($hideTags) {
            return preg_replace('/<.+>/mU', '', $string);
        } else {
            return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Подготовка строки для использования в аттрибутах тегов. Например:
     * ```
     * <input name="test" value="<?= HelperWidget::prepareToTagAttr($value) ?>"/>
     * ```
     * 
     * @param string $string
     *
     * @return string
     */
    public static function prepareToTagAttr($string)
    {
        // Не используйте addcslashes в этом методе, иначе в тегах будут дубли обратных слешей
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Подготовка строки для использования в JS.
     *
     * @param string $string
     *
     * @return string
     */
    public static function prepareToJs($string)
    {
        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        $string = addcslashes($string, "\r\n\"\\");

        return $string;
    }

    /**
     * Генерирует HTML для поля в списке.
     *
     * @param \CAdminListRow $row
     * @param array $data Данные текущей строки.
     *
     * @return void
     *
     * @see AdminListHelper::addRowCell()
     * 
     * @api
     */
    abstract public function generateRow(&$row, $data);

    /**
     * Генерирует HTML для поля фильтрации.
     *
     * @return void
     *
     * @see AdminListHelper::createFilterForm()
     * 
     * @api
     */
    abstract public function showFilterHtml();

    /**
     * Возвращает массив настроек данного виджета, либо значение отдельного параметра, если указано его имя.
     *
     * @param string $name Название конкретного параметра.
     *
     * @return array|mixed
     * 
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
     *
     * @param AdminBaseHelper $helper
     */
    public function setHelper(&$helper)
    {
        $this->helper = $helper;
    }

    /**
     * Возвращает текукщее значение поля фильтрации (спец. символы экранированы).
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
        } else {
            if ($filterPrefix = $this->getSettings('FILTER') AND $filterPrefix !== true AND isset($filter[$this->getCode()])) {
                $filter[$filterPrefix . $this->getCode()] = $filter[$this->getCode()];
                unset($filter[$this->getCode()]);
            }
        }
    }

    /**
     * Проверяет оператор фильтрации.
     * 
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
            $this->addError('DIGITALWAND_AH_REQUIRED_FIELD_ERROR');
        }
        if ($this->getSettings('UNIQUE') && !$this->isUnique()) {
            $this->addError('DIGITALWAND_AH_DUPLICATE_FIELD_ERROR');
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
     *
     * @param string $messageId Код сообщения об ошибке из лэнг-файла. Плейсхолдер #FIELD# будет заменён на значение 
     * параметра TITLE.
     * @param array $replace Данные для замены.
     *
     * @see Loc::getMessage()
     */
    protected function addError($messageId, $replace = array())
    {
        $this->validationErrors[$this->getCode()] = Loc::getMessage(
            $messageId,
            array_merge(array('#FIELD#' => $this->getSettings('TITLE')), $replace)
        );
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
     * Выставляет код для данного виджета при инициализации. Перегружает настройки.
     * 
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
     * Устанавливает настройки интерфейса для текущего поля.
     *
     * @param string $code
     *
     * @return bool
     * 
     * @see AdminBaseHelper::getInterfaceSettings()
     * @see AdminBaseHelper::setFields()
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
     * Возвращает название сущности данной модели.
     * 
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
     *
     * @param $data
     */
    public function setData(&$data)
    {
        $this->data = &$data;
        //FIXME: нелепый оверхэд ради того, чтобы можно было централизованно преобразовывать значение при записи
        $this->setValue($data[$this->getCode()]);
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
     *
     * @param $value
     *
     * @return bool
     */
    protected function setValue($value)
    {
        $code = $this->getCode();
        $this->data[$code] = $value;

        return true;
    }

    /**
     * Получения названия поля таблицы, в которой хранятся множественные данные этого виджета
     *
     * @param string $fieldName Название поля
     *
     * @return bool|string
     */
    public function getMultipleField($fieldName)
    {
        $fields = $this->getSettings('MULTIPLE_FIELDS');
        if (empty($fields)) {
            return $fieldName;
        }

        // Поиск алиаса названия поля
        if (isset($fields[$fieldName])) {
            return $fields[$fieldName];
        }

        // Поиск оригинального названия поля
        $fieldsFlip = array_flip($fields);

        if (isset($fieldsFlip[$fieldName])) {
            return $fieldsFlip[$fieldName];
        }

        return $fieldName;
    }

    /**
     * Выставляет значение отдельной настройки
     *
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
     *
     * @param null $suffix опциональное дополнение к названию поля
     *
     * @return string
     */
    protected function getEditInputName($suffix = null)
    {
        return 'FIELDS[' . $this->getCode() . $suffix . ']';
    }

    /**
     * Уникальный ID для DOM HTML
     * @return string
     */
    protected function getEditInputHtmlId()
    {
        $htmlId = end(explode('\\', $this->entityName)) . '-' . $this->getCode();

        return strtolower(preg_replace('/[^A-z-]/', '-', $htmlId));
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
        if (is_a($this->helper, 'DigitalWand\AdminHelper\Helper\AdminListHelper')) {
            return self::LIST_HELPER;
        } else {
            if (is_a($this->helper, 'DigitalWand\AdminHelper\Helper\AdminEditHelper')) {
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

    /**
     * @todo Вынести в ресурс (\CJSCore::Init()).
     * @todo Описать.
     */
    protected function jsHelper()
    {
        if ($this->jsHelper == true) {
            return true;
        }

        $this->jsHelper = true;
        \CJSCore::Init(array("jquery"));
        ?>
        <script>
            /**
             * Менеджер множественных полей
             * Позволяет добавлять и удалять любой HTML код с возможность подстановки динамических данных
             * Инструкция:
             * - создайте контейнер, где будут хранится отображаться код
             * - создайте экземпляр MultipleWidgetHelper
             * Например: var multiple = MultipleWidgetHelper(селектор контейнера, шаблон)
             * шаблон - это HTML код, который можно будет добавлять и удалять в интерфейсе
             * В шаблон можно добавлять переменные, их нужно обрамлять фигурными скобками. Например {{entity_id}}
             * Если в шаблоне несколько полей, переменная {{field_id}} обязательна
             * Например <input type="text" name="image[{{field_id}}][SRC]"><input type="text" name="image[{{field_id}}][DESCRIPTION]">
             * Если добавляемые поле не новое, то обязательно передавайте в addField переменную field_id с ID записи,
             * для новосозданных полей переменная заполнится автоматически
             */
            function MultipleWidgetHelper(container, fieldTemplate) {
                this.$container = $(container);
                if (this.$container.size() == 0) {
                    throw 'Главный контейнер полей не найден (' + container + ')';
                }
                if (!fieldTemplate) {
                    throw 'Не передан обязательный параметр fieldTemplate';
                }
                this.fieldTemplate = fieldTemplate;
                this._init();
            }

            MultipleWidgetHelper.prototype = {
                /**
                 * Основной контейнер
                 */
                $container: null,
                /**
                 * Контейнер полей
                 */
                $fieldsContainer: null,
                /**
                 * Шаблон поля
                 */
                fieldTemplate: null,
                /**
                 * Счетчик добавлений полей
                 */
                fieldsCounter: 0,
                /**
                 * Добавления поля
                 * @param data object Данные для шаблона в виде ключ: значение
                 */
                addField: function (data) {
                    // console.log('Добавление поля');
                    this.addFieldHtml(this.fieldTemplate, data);
                },
                addFieldHtml: function (fieldTemplate, data) {
                    this.fieldsCounter++;
                    this.$fieldsContainer.append(this._generateFieldContent(fieldTemplate, data));
                },
                /**
                 * Удаление поля
                 * @param field string|object Селектор или jQuery объект
                 */
                deleteField: function (field) {
                    // console.log('Удаление поля');
                    $(field).remove();
                    if (this.$fieldsContainer.find('> *').size() == 0) {
                        this.addField();
                    }
                },
                _init: function () {
                    this.$container.append('<div class="fields-container"></div>');
                    this.$fieldsContainer = this.$container.find('.fields-container');
                    this.$container.append(this._getAddButton());

                    this._trackEvents();
                },
                /**
                 * Генерация контента контейнера поля
                 * @param data
                 * @returns {string}
                 * @private
                 */
                _generateFieldContent: function (fieldTemplate, data) {
                    return '<div class="field-container" style="margin-bottom: 5px;">'
                        + this._generateFieldTemplate(fieldTemplate, data) + this._getDeleteButton()
                        + '</div>';
                },
                /**
                 * Генерация шаблона поля
                 * @param data object Данные для подстановки
                 * @returns {null}
                 * @private
                 */
                _generateFieldTemplate: function (fieldTemplate, data) {
                    if (!data) {
                        data = {};
                    }

                    if (typeof data.field_id == 'undefined') {
                        data.field_id = 'new_' + this.fieldsCounter;
                    }

                    $.each(data, function (key, value) {
                        // Подставление значений переменных
                        fieldTemplate = fieldTemplate.replace(new RegExp('\{\{' + key + '\}\}', ['g']), value);
                    });

                    // Удаление из шаблона необработанных переменных
                    fieldTemplate = fieldTemplate.replace(/\{\{.+?\}\}/g, '');

                    return fieldTemplate;
                },
                /**
                 * Кнопка удаления
                 * @returns {string}
                 * @private
                 */
                _getDeleteButton: function () {
                    return '<input type="button" value="-" class="delete-field-button" style="margin-left: 5px;">';
                },
                /**
                 * Кнопка добавления
                 * @returns {string}
                 * @private
                 */
                _getAddButton: function () {
                    return '<input type="button" value="Добавить..." class="add-field-button">';
                },
                /**
                 * Отслеживание событий
                 * @private
                 */
                _trackEvents: function () {
                    var context = this;
                    // Добавление поля
                    this.$container.find('.add-field-button').on('click', function () {
                        context.addField();
                    });
                    // Удаление поля
                    this.$container.on('click', '.delete-field-button', function () {
                        context.deleteField($(this).parents('.field-container'));
                    });
                }
            };
        </script>
        <?
    }
}