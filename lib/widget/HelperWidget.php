<?php
namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Localization\Loc;
use DigitalWand\AdminHelper\Helper\AdminBaseHelper;
use DigitalWand\AdminHelper\Helper\AdminEditHelper;
use DigitalWand\AdminHelper\Helper\AdminListHelper;
use Bitrix\Main\Entity\DataManager;

Loc::loadMessages(__FILE__);
// TODO В мультивиджетах сделать поддержку READONLY
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
 * <li><b>MULTIPLE</b> - bool является ли поле множественным</li>
 * <li><b>MULTIPLE_FIELDS</b> - bool поля используемые в хранилище множественных значений</li>
 * </ul>
 *
 * Инструкция по реализации множественных полей:
 * <ul>
 * <li>создайте поле и пометьте MULTIPLE true</li>
 * <li>создайте таблицу для сущности поля. Обязательные поля: ID (INT), ENTITY_ID (INT), VALUE (тип и размер поля на ваше усмотрение,
 * главное поставьте верный тип в модели поля)
 * Для каждого виджета есть своё обязательное поле, например IMAGE_ID или TEXT, смотрите комментарии к классам виджетов</li>
 * <li>создайте и опишите модель для созданной таблицы</li>
 * <li>укажите связь в главной сущности с сущностью поля по шаблону:
 * new Entity\ReferenceField(
 * 'НАЗВАНИЕ_ПОЛЯ',
 * 'МОДЕЛЬ_СУЩНОСТИ_С_НЕЙМСПЕЙСОМ',
 * ['=this.ID' => 'ref.ENTITY_ID']
 * ),
 * </li>
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
	 * Название поля ("символьный код")
	 */
	protected $code;

	/**
	 * @var array $settings
	 * Настройки виджета для данной модели
	 */
	protected $settings = [
		// Поля множественного виджета по умолчанию (array('ОРИГИНАЛЬНОЕ НАЗВАНИЕ', 'ОРИГИНАЛЬНОЕ НАЗВАНИЕ' => 'АЛИАС'))
		'MULTIPLE_FIELDS' => ['ID', 'VALUE'/* => 'ЕСЛИ В ВАШЕЙ ТАБЛИЦЕ НЕТ ПОЛЯ VALUE, ЗДЕСЬ МОЖНО ОБЪЯВИТЬ АЛИАС' */]
	];

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
		if ($this->getSettings('HIDE_WHEN_CREATE') AND !isset($this->data['ID']))
		{
			return;
		}

		// JS хелперы
		$this->jsHelper();

		if ($this->getSettings('USE_BX_API'))
		{
			$this->genEditHTML();
		}
		else
		{
			print '<tr>';
			$title = $this->getSettings('TITLE');
			if ($this->getSettings('REQUIRED') === true)
			{
				$title = '<b>' . $title . '</b>';
			}
			print '<td width="40%" style="vertical-align: top;">' . $title . ':</td>';

			$field = $this->getValue();
			if (is_null($field))
			{
				$field = '';
			}

			$readOnly = $this->getSettings('READONLY');

			if (!$readOnly AND !$isPKField)
			{
				if ($this->getSettings('MULTIPLE'))
				{
					$field = $this->genMultipleEditHTML();
				}
				else
				{
					$field = $this->genEditHTML();
				}
			}
			else
			{
				if ($readOnly)
				{
					if ($this->getSettings('MULTIPLE'))
					{
						$field = $this->getMultipleValueReadonly();
					}
					else
					{
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
		return $this->getValue();
	}

	/**
	 * Возвращает значения множественного поля
	 * @return array
	 */
	protected function getMultipleValue()
	{
		$rsEntityData = null;
		$values = array();
		if (!empty($this->data['ID']))
		{
			$entityName = $this->entityName;
			$rsEntityData = $entityName::getList([
				'select' => ['REFERENCE_' => $this->getCode() . '.*'],
				'filter' => ['=ID' => $this->data['ID']]
			]);
			if ($rsEntityData)
			{
				while ($referenceData = $rsEntityData->fetch())
				{
					if (empty($referenceData['REFERENCE_' . $this->getMultipleField('ID')]))
					{
						continue;
					}
					$values[] = $referenceData['REFERENCE_' . $this->getMultipleField('VALUE')];
				}
			}
		}
		else if ($this->data[$this->code])
		{
			$values = $this->data[$this->code];
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

		return join('<br/>', $values);
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
		if (empty($name))
		{
			return $this->settings;
		}
		else
		{
			if (isset($this->settings[$name]))
			{
				return $this->settings[$name];
			}
			else
			{
				if (isset(static::$defaults[$name]))
				{
					return static::$defaults[$name];
				}
				else
				{
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
		if (isset($GLOBALS[$this->filterFieldPrefix . $this->code]))
		{
			return htmlspecialcharsbx($GLOBALS[$this->filterFieldPrefix . $this->code]);
		}
		else
		{
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
		if ($this->isFilterBetween())
		{
			$field = $this->getCode();
			$from = $to = false;

			if (isset($_REQUEST['find_' . $field . '_from']))
			{
				$from = $_REQUEST['find_' . $field . '_from'];
				if (is_a($this, 'DateWidget'))
				{
					$from = date('Y-m-d H:i:s', strtotime($from));
				}
			}
			if (isset($_REQUEST['find_' . $field . '_to']))
			{
				$to = $_REQUEST['find_' . $field . '_to'];
				if (is_a($this, 'DateWidget'))
				{
					$to = date('Y-m-d 23:59:59', strtotime($to));
				}
			}

			if ($from !== false AND $to !== false)
			{
				$filter['><' . $field] = array($from, $to);
			}
			else
			{
				if ($from !== false)
				{
					$filter['>' . $field] = $from;
				}
				else
				{
					if ($to !== false)
					{
						$filter['<' . $field] = $to;
					}
				}
			}
		}
		else if ($filterPrefix = $this->getSettings('FILTER') AND $filterPrefix !== true AND isset($filter[$this->getCode()]))
		{
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
		if (!$this->checkRequired())
		{
			$this->addError('REQUIRED_FIELD_ERROR');
		}
		if ($this->getSettings('UNIQUE') && !$this->isUnique())
		{
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
		if ($this->getSettings('REQUIRED') == true)
		{
			$value = $this->getValue();

			return !is_null($value) && !empty($value);
		}
		else
		{
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

		if (!isset($interface['FIELDS'][$code]))
		{
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
		if (isset($this->settings['DEFAULT']) && is_null($this->getValue()))
		{
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
	 * Получения названия поля таблицы, в которой хранятся множественные данные этого виджета
	 * @param string $fieldName Название поля
	 * @return bool|string
	 */
	public function getMultipleField($fieldName)
	{
		$fields = $this->getSettings('MULTIPLE_FIELDS');
		if (empty($fields))
		{
			return $fieldName;
		}

		// Поиск алиаса названия поля
		if (isset($fields[$fieldName]))
		{
			return $fields[$fieldName];
		}

		// Поиск оригинального названия поля
		$fieldsFlip = array_flip($fields);

		if (isset($fieldsFlip[$fieldName]))
		{
			return $fieldsFlip[$fieldName];
		}

		return $fieldName;
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
		if ($this->isFilterBetween())
		{
			$baseName = $this->filterFieldPrefix . $this->code;;
			$inputNameFrom = $baseName . '_from';
			$inputNameTo = $baseName . '_to';

			return array($inputNameFrom, $inputNameTo);
		}
		else
		{
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
		if (is_a($this->helper, 'DigitalWand\AdminHelper\Helper\AdminListHelper'))
		{
			return self::LIST_HELPER;
		}
		else
		{
			if (is_a($this->helper, 'DigitalWand\AdminHelper\Helper\AdminEditHelper'))
			{
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
		if ($this->getSettings('VIRTUAL'))
		{
			return true;
		}

		$value = $this->getValue();
		if (empty($value))
		{
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

		if (!empty($id))
		{
			$filter["!=" . $idField] = $id;
		}

		$count = $class::getCount($filter);

		if (!$count)
		{
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
		if (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'excel')
		{
			return true;
		}

		return false;
	}

	protected function jsHelper()
	{
		\CJSCore::Init(array("jquery"));
		// TODO Вынести в ресурс
		?>
		<script>
			/**
			 * Менеджер мультполей
			 * Позволяет добавлять и удалять любой HTML код с возможность подстановки динамических данных
			 * Инстуркция:
			 * - создайте любой тег-контейнер
			 * - создайте экземпляр MultipleWidgetHelper
			 * Например: var multiple = MultipleWidgetHelper(селектор контейнера, шаблон)
			 * шаблон - это код, который будет подставлен в качестве поля
			 * В шаблон можно добавлять переменные обрамленные решетками. Например #entity_id#
			 * В шаблоне должна быть обязательно подставлена переменная #field_id#, если в контейнере несколько полей
			 * Например <input type="text" name="image[#field_id#][DESCRIPTION]">
			 * Если добавляемые поле не новое, то обязательно передавайте в data параметр field_id с ID записи
			 * В метод addField можно передать значения этих переменных
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
					console.log('Добавление поля');
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
					console.log('Удаление поля');
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
						fieldTemplate = fieldTemplate.replace(new RegExp('\#' + key + '\#', ['g']), value);
					});
					fieldTemplate = fieldTemplate.replace(/\#[^\#]+\#/g, '');

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