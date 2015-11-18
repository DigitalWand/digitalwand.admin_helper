<?php

namespace DigitalWand\AdminHelper\Model;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity;
use DigitalWand\AdminHelper\Helper\AdminBaseHelper;
use DigitalWand\AdminHelper\Widget\HelperWidget;

Loc::loadMessages(__FILE__);

/**
 * Управление сущностью и привязанными к ней данными
 *
 * Пример создания сущности
 *
 * $filmManager = new EntityManager(FilmTable, [
 *        // Данные сущности
 *        'TITLE' => 'Монстры на каникулах 2',
 *        'YEAR' => 2015,
 *        // У сущности FilmTable есть связь с RelatedLinksTable через поле RELATED_LINKS.
 *        // Если передать ей данные, то они будут обработаны
 *        // Представим, что у сущности RelatedLinksTable есть поля ID и VALUE (в этом поле хранится ссылка), FILM_ID
 *        // В большинстве случаев, данные передаваемые связям генерируются множественными виджетами
 *        'RELATED_LINKS' => [
 *        // Переданный ниже массив будет обработан аналогично коду RelatedLinksTable::add(['VALUE' => 'yandex.ru']);
 *        ['VALUE' => 'yandex.ru'],
 *        // Если в массив добавить ID, то запись обновится: RelatedLinksTable::update(3, ['ID' => 3, 'VALUE' => 'google.com']);
 *        ['ID' => 3, 'VALUE' => 'google.com'],
 *        // ВНИМАНИЕ: данный класс реководствуется принципом: что передано для связи, то сохранится или обновится, что не передано, будет удалено
 *        // То есть, если в поле связи RELATED_LINKS передать пустой массив, то все значения связи будут удалены
 *        ]
 * ]);
 * $filmManager->save();
 *
 * Пример удаления сущности
 *
 * $articleManager = new EntityManager(ArticlesTable, [], 7, $adminHelper);
 * $articleManager->delete();
 */
class EntityManager
{
	/**
	 * @var string Класс модели
	 */
	protected $modelClass;
	/**
	 * @var Entity\Base Сущность модели
	 */
	protected $modelEntity;
	/**
	 * @var array Данные для обработки
	 */
	protected $modelData;
	/**
	 * @var integer ID модели
	 */
	protected $modelId = null;
	/**
	 * @var string Поле, в котором хранится идентификатор модели
	 */
	protected $modelPk = null;

	/**
	 * @var array Данные для связей
	 */
	protected $referencesData;
	/**
	 * @var AdminBaseHelper Хелпер
	 */
	protected $adminHelper;

	/**
	 * @var array Предупреждения
	 */
	protected $notes = array();

	/**
	 * @param DataManager $modelClass
	 * @param $modelData
	 * @param null $modelId
	 * @param AdminBaseHelper $adminHelper
	 */
	public function __construct($modelClass, $modelData, $modelId = null, AdminBaseHelper $adminHelper)
	{
		$this->modelClass = $modelClass;
		$this->modelEntity = $modelClass::getEntity();
		$this->modelData = $modelData;
		$this->modelPk = $this->modelEntity->getPrimary();
		$this->adminHelper = $adminHelper;

		if (!empty($modelId))
		{
			$this->setModelId($modelId);
		}
	}

	/**
	 * Сохранить запись и данные связей
	 * @return Entity\AddResult|Entity\UpdateResult
	 */
	public function save()
	{
		$this->collectReferencesData();

		/** @var DataManager $modelClass */
		$modelClass = $this->modelClass;
		if (empty($this->modelId))
		{
			$result = $modelClass::add($this->modelData);
			if ($result->isSuccess())
			{
				$this->setModelId($result->getId());
			}
		}
		else
		{
			$result = $modelClass::update($this->modelId, $this->modelData);
		}

		if ($result->isSuccess())
		{
			$this->processReferencesData();
		}

		return $result;
	}

	/**
	 * Удаление запись и данные связей
	 * @return Entity\DeleteResult
	 */
	public function delete()
	{
		// Удаление данных зависимостей
		$this->deleteReferencesData();

		$model = $this->modelClass;

		return $model::delete($this->modelId);
	}

	/**
	 * Получить список предупреждений
	 * @return array
	 */
	public function getNotes()
	{
		return $this->notes;
	}

	/**
	 * Добавить предупреждение
	 * @param $note
	 * @param string $key Ключ для избежания дублирования сообщений
	 * @return bool
	 */
	protected function addNote($note, $key = null)
	{
		if ($key)
		{
			$this->notes[$key] = $note;
		}
		else
		{
			$this->notes[] = $note;
		}

		return true;
	}

	/**
	 * Установка текущего идентификатора модели
	 * @param $modelId
	 */
	protected function setModelId($modelId)
	{
		$this->modelId = $modelId;
		$this->modelData[$this->modelPk] = $this->modelId;
	}

	/**
	 * Получение связей
	 *
	 * @return array
	 */
	protected function getReferences()
	{
		$references = array();
		/** @var DataManager $modelClass */
		$modelClass = $this->modelClass;
		$entity = $modelClass::getEntity();
		$fields = $entity->getFields();

		foreach ($fields as $fieldName => $field)
		{
			if ($field instanceof Entity\ReferenceField)
			{
				$references[$fieldName] = $field;
			}
		}

		return $references;
	}

	/**
	 * Извлечение данных для связей
	 */
	protected function collectReferencesData()
	{
		$references = $this->getReferences();

		// Извлечение данных управляемых связей
		foreach ($references as $fieldName => $reference)
		{
			if (isset($this->modelData[$fieldName]))
			{
				// Извлечение данных для связи
				$this->referencesData[$fieldName] = $this->modelData[$fieldName];
				unset($this->modelData[$fieldName]);
			}
		}
	}

	/**
	 * Обработка данных для связей
	 *
	 * @throws ArgumentException
	 */
	protected function processReferencesData()
	{
		/** @var DataManager $modelClass */
		$modelClass = $this->modelClass;
		$entity = $modelClass::getEntity();
		$fields = $entity->getFields();

		foreach ($this->referencesData as $fieldName => $referenceDataSet)
		{
			$fieldWidget = $this->getFieldWidget($fieldName);
			/** @var Entity\ReferenceField $reference */
			$reference = $fields[$fieldName];
			$referenceDataSet = $this->linkDataSet($reference, $referenceDataSet);
			$referenceStaleDataSet = $this->getReferenceDataSet($reference);

			// Создание и обновление привязанных данных
			$processedDataIds = array();
			foreach ($referenceDataSet as $referenceData)
			{
				if (empty($referenceData[$fieldWidget->getMultipleField('ID')]))
				{
					// Создание связанных данных
					if (!empty($referenceData[$fieldWidget->getMultipleField('VALUE')]))
					{
						$result = $this->createReferenceData($reference, $referenceData);
						if ($result->isSuccess())
						{
							$processedDataIds[] = $result->getId();
						}
					}
				}
				else
				{
					// Обновление связанных данных
					$updateResult = $this->updateReferenceData($reference, $referenceData, $referenceStaleDataSet);
					if ($updateResult !== false)
					{
						$processedDataIds[] = $referenceData[$fieldWidget->getMultipleField('ID')];
					}
				}
			}

			// Удаление записей, которые не были созданы или обновлены
			foreach ($referenceStaleDataSet as $referenceData)
			{
				if (!in_array($referenceData[$fieldWidget->getMultipleField('ID')], $processedDataIds))
				{
					$this->deleteReferenceData($reference, $referenceData[$fieldWidget->getMultipleField('ID')])->isSuccess();
				}
			}
		}

		$this->referencesData = array();
	}

	/**
	 * Удаление данных всех связей, которые указаны в полях интерфейса раздела
	 */
	protected function deleteReferencesData()
	{
		$references = $this->getReferences();
		$fields = $this->adminHelper->getFields();

		/**
		 * @var string $fieldName
		 * @var Entity\ReferenceField $reference
		 */
		foreach ($references as $fieldName => $reference)
		{
			// Удаляются только данные связей, которые объявлены в интерфейсе
			if (!isset($fields[$fieldName]))
			{
				continue;
			}

			$fieldWidget = $this->getFieldWidget($reference->getName());

			$referenceStaleDataSet = $this->getReferenceDataSet($reference);
			foreach ($referenceStaleDataSet as $referenceData)
			{
				$this->deleteReferenceData($reference, $referenceData[$fieldWidget->getMultipleField('ID')]);
			}
		}
	}

	/**
	 * Создание связанной записи
	 *
	 * @param Entity\ReferenceField $reference
	 * @param array $referenceData
	 * @return \Bitrix\Main\Entity\AddResult
	 * @throws ArgumentException
	 */
	protected function createReferenceData(Entity\ReferenceField $reference, array $referenceData)
	{
		$referenceName = $reference->getName();
		$fieldParams = $this->getFieldParams($referenceName);
		$fieldWidget = $this->getFieldWidget($referenceName);

		if (!empty($referenceData[$fieldWidget->getMultipleField('ID')]))
		{
			throw new ArgumentException('Аргумент data не может содержать идентификатор элемента', 'data');
		}

		$refClass = $reference->getRefEntity()->getDataClass();

		$createResult = $refClass::add($referenceData);

		if (!$createResult->isSuccess())
		{
			$this->addNote(
				Loc::getMessage('DIGITALWAND_ADMIN_HELPER_RELATION_SAVE_ERROR', array('#FIELD#' => $fieldParams['TITLE'])),
				'CREATE_' . $referenceName
			);
		}

		return $createResult;
	}

	/**
	 * Обновление связанной записи
	 *
	 * @param Entity\ReferenceField $reference
	 * @param array $referenceData
	 * @param array $referenceStaleDataSet
	 * @return Entity\UpdateResult|null
	 * @throws ArgumentException
	 */
	protected function updateReferenceData(Entity\ReferenceField $reference, array $referenceData, array $referenceStaleDataSet)
	{
		$referenceName = $reference->getName();
		$fieldParams = $this->getFieldParams($referenceName);
		$fieldWidget = $this->getFieldWidget($referenceName);

		if (empty($referenceData[$fieldWidget->getMultipleField('ID')]))
		{
			throw new ArgumentException('Аргумент data должен содержать идентификатор элемента', 'data');
		}

		// Сравнение старых данных и новых, обновляется только при различиях
		if ($this->isDifferentData($referenceStaleDataSet[$referenceData[$fieldWidget->getMultipleField('ID')]], $referenceData))
		{
			$refClass = $reference->getRefEntity()->getDataClass();
			$updateResult = $refClass::update($referenceData[$fieldWidget->getMultipleField('ID')], $referenceData);

			if (!$updateResult->isSuccess())
			{
				$this->addNote(
					Loc::getMessage('DIGITALWAND_ADMIN_HELPER_RELATION_SAVE_ERROR', array('#FIELD#' => $fieldParams['TITLE'])),
					'UPDATE_' . $referenceName
				);
			}

			return $updateResult;
		}
		else
		{
			return null;
		}
	}

	/**
	 * Удаление данных связи
	 *
	 * @param Entity\ReferenceField $reference
	 * @param $referenceId
	 * @return \Bitrix\Main\Entity\Result
	 * @throws ArgumentException
	 */
	protected function deleteReferenceData(Entity\ReferenceField $reference, $referenceId)
	{
		$fieldParams = $this->getFieldParams($reference->getName());
		$refClass = $reference->getRefEntity()->getDataClass();
		$deleteResult = $refClass::delete($referenceId);

		if (!$deleteResult->isSuccess())
		{
			$this->addNote(
				Loc::getMessage('DIGITALWAND_ADMIN_HELPER_RELATION_DELETE_ERROR', array('#FIELD#' => $fieldParams['TITLE'])),
				'DELETE_' . $reference->getName()
			);
		}

		return $deleteResult;
	}

	/**
	 * Получение данных связи
	 *
	 * @param $reference
	 * @return array
	 */
	protected function getReferenceDataSet(Entity\ReferenceField $reference)
	{
		/** @var DataManager $modelClass */
		$modelClass = $this->modelClass;
		$dataSet = array();
		$fieldWidget = $this->getFieldWidget($reference->getName());

		$rsData = $modelClass::getList(array('select' => array('REF_' => $reference->getName() . '.*'), 'filter' => array('=' . $this->modelPk => $this->modelId)));
		while ($data = $rsData->fetch())
		{
			if (empty($data['REF_' . $fieldWidget->getMultipleField('ID')]))
			{
				continue;
			}

			$row = array();
			foreach ($data as $key => $value)
			{
				$row[str_replace('REF_', '', $key)] = $value;
			}

			$dataSet[$data['REF_' . $fieldWidget->getMultipleField('ID')]] = $row;
		}

		return $dataSet;
	}

	/**
	 * В данные связи подставляются данные основной модели используя условия связи моделей из getMap()
	 *
	 * @param Entity\ReferenceField $reference
	 * @param array $referenceData Данные привязанной модели
	 * @return array
	 */
	protected function linkData(Entity\ReferenceField $reference, array $referenceData)
	{
		// Парсим условия связи двух моделей
		$referenceConditions = $this->getReferenceConditions($reference);

		foreach ($referenceConditions as $refField => $refValue)
		{
			// Так как в условиях связи между моделями в основном отношения типа this.field => ref.field или
			// ref.field => SqlExpression, мы можем использовать это для подстановки данных
			// this.field - это поле основной модели
			// ref.field - поле модели из связи
			// customValue - это строка полученная из new SqlExpression('%s', ...)
			if (empty($refValue['thisField']))
			{
				$referenceData[$refField] = $refValue['customValue'];
			}
			else
			{
				$referenceData[$refField] = $this->modelData[$refValue['thisField']];
			}
		}

		return $referenceData;
	}

	/**
	 * Связывает набор связанных данных с основной моделю
	 *
	 * @param Entity\ReferenceField $reference
	 * @param array $referenceDataSet
	 * @return array
	 */
	protected function linkDataSet(Entity\ReferenceField $reference, array $referenceDataSet)
	{
		foreach ($referenceDataSet as $key => $referenceData)
		{
			$referenceDataSet[$key] = $this->linkData($reference, $referenceData);
		}

		return $referenceDataSet;
	}

	/**
	 * Парсинг условий связи между моделями
	 * Ничего сложного нет, просто определяются соответствия полей основной модели и модели из связи
	 * Например FilmLinksTable::FILM_ID => FilmTable::ID (ref.FILM_ID => this.ID)
	 * Или например MediaTable::TYPE => 'FILM' (ref.TYPE => new DB\SqlExpression('?s', 'FILM'))
	 *
	 * @param Entity\ReferenceField $reference Данные поля из getMap()
	 * @return array Условия связи преобразованные в массив вида $conditions[$refField]['thisField' => $thisField, 'customValue' => $customValue]
	 * $customValue - это результат парсинга SqlExpression
	 * Если шаблон SqlExpression не равен %s, то условие исключается из результата
	 */
	protected function getReferenceConditions(Entity\ReferenceField $reference)
	{
		$conditionsFields = array();

		foreach ($reference->getReference() as $leftCondition => $rightCondition)
		{
			$thisField = null;
			$refField = null;
			$customValue = null;

			// Поиск this.... в левом условии
			$thisFieldMatch = array();
			$refFieldMatch = array();
			if (preg_match('/=this\.([A-z]+)/', $leftCondition, $thisFieldMatch) == 1)
			{
				$thisField = $thisFieldMatch[1];
			}
			// Поиск ref.... в левом условии
			else if (preg_match('/ref\.([A-z]+)/', $leftCondition, $refFieldMatch) == 1)
			{
				$refField = $refFieldMatch[1];
			}

			// Поиск expression value... в правом условии
			$refFieldMatch = array();
			if ($rightCondition instanceof \Bitrix\Main\DB\SqlExpression)
			{
				$customValueDirty = $rightCondition->compile();
				$customValue = preg_replace('/^([\'"])(.+)\1$/', '$2', $customValueDirty);
				if ($customValueDirty == $customValue)
				{
					// Если значение выражения не обрамлено кавычками, значит оно не нужно нам
					$customValue = null;
				}
			}
			// Поиск ref.... в правом условии
			else if (preg_match('/ref\.([A-z]+)/', $rightCondition, $refFieldMatch) > 0)
			{
				$refField = $refFieldMatch[1];
			}

			// Если не указано поле, которое нужно заполнить или не найдено содержимое для него, то исключаем условие
			if (empty($refField) || (empty($thisField) && empty($customValue)))
			{
				continue;
			}
			else
			{
				$conditionsFields[$refField] = [
					'thisField' => $thisField,
					'customValue' => $customValue,
				];
			}
		}

		return $conditionsFields;
	}

	/**
	 * Обнаружение отличий массивов
	 * Метод не сранивает наличие аргументов, сравниваются только значения общих параметров
	 *
	 * @param array $data1
	 * @param array $data2
	 * @return bool
	 */
	protected function isDifferentData(array $data1 = null, array $data2 = null)
	{
		foreach ($data1 as $key => $value)
		{
			if (isset($data2[$key]) && $data2[$key] != $value)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $fieldName
	 * @return array|bool
	 */
	protected function getFieldParams($fieldName)
	{
		$fields = $this->adminHelper->getFields();
		if (isset($fields[$fieldName]) && isset($fields[$fieldName]['WIDGET']))
		{
			return $fields[$fieldName];
		}
		else
		{
			return false;
		}
	}

	/**
	 * Получение виджета привязанного к полю
	 * @param $fieldName
	 * @return HelperWidget|bool
	 */
	protected function getFieldWidget($fieldName)
	{
		$field = $this->getFieldParams($fieldName);

		return isset($field['WIDGET']) ? $field['WIDGET'] : null;
	}
}