<?php

namespace DigitalWand\AdminHelper\Model;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity;
use DigitalWand\AdminHelper\Helper\AdminBaseHelper;
use DigitalWand\AdminHelper\Widget\HelperWidget;

/**
 * Управление данными связей через одну модель
 * Класс руководствуется принципом: что передано для связи, то и будет хранится, что не передано, будет удалено
 *
 * Пример:
 * Представим две модели: ArticlesTable (статьи) и ArticlesCommentsTable (комментарии к статьям)
 * Поля модели ArticlesTable: ID, ... неважно
 * Поля модели ArticlesCommentsTable: ARTICLE_ID (ID статьи), NAME (имя комментатора), COMMENT (комментарий)
 *
 *
 * Пример создания комментариев:
 * // В данном случае при создании статьи будет создано 3 комментария привязанных по ARTICLE_ID к статье
 * // Почему в примере не передается ARTICLE_ID? Это поле через которое идет связь, данные для него установятся автоматически
 * ArticlesTable::add([
 *        'СВЯЗЬ КОММЕНТАРИИ' => [
 *            [МАССИВ ДЛЯ СОЗДАНИЯ ЭЛЕМЕНТА 1],
 *            [МАССИВ ДЛЯ СОЗДАНИЯ ЭЛЕМЕНТА 2],
 *            [ПРИМЕР: 'EMAIL' => 'email', 'COMMENT' => 'Комментарий']
 *        ]
 * ]);
 *
 * // При обновлении статьи будет создано 3 комментария привязанных по ARTICLE_ID к статье
 * // Комментарии, которых нет в списке - будут удалены
 * ArticlesTable::update(7, [
 *        'COMMENTS' => [
 *            [МАССИВ ДЛЯ СОЗДАНИЯ ЭЛЕМЕНТА 1],
 *            [МАССИВ ДЛЯ СОЗДАНИЯ ЭЛЕМЕНТА 2],
 *            [ПРИМЕР: 'EMAIL' => 'email', 'COMMENT' => 'Комментарий']
 *        ]
 * ]);
 *
 *
 * Пример обновления:
 * // При обновлении статьи будет обновлено 3 комментария привязанных по ARTICLE_ID к статье
 * // Комментарии, которых нет в списке - будут удалены
 * ArticlesTable::update(7, [
 *        'COMMENTS' => [
 *            ['ID' => x, МАССИВ ДЛЯ ОБНОВЛЕНИЯ ЭЛЕМЕНТА 1],
 *            ['ID' => x, МАССИВ ДЛЯ ОБНОВЛЕНИЯ ЭЛЕМЕНТА 2],
 *            [ПРИМЕР: 'ID' => 5, 'EMAIL' => 'email', 'COMMENT' => 'Комментарий']
 *        ]
 * ]);
 *
 *
 * Пример удаления:
 * // Будут удалены все комментарии статьи с ID 7
 *  ArticlesTable::update(7, [
 *        'COMMENTS' => []
 * ]);
 *
 *
 * Пример полного удаления:
 * // Будут удалены все комментарии статьи с ID 7
 * ArticlesTable::delete(7);
 *
 *
 * Общие пример:
 * ArticlesTable::update(7, [
 *        'COMMENTS' => [
 *            // Комментарий будет создан
 *            [ПРИМЕР: 'EMAIL' => 'email', 'COMMENT' => 'Комментарий']
 *            // Комментарий будет обновлен
 *            [ПРИМЕР: 'ID' => 5, 'EMAIL' => 'email', 'COMMENT' => 'Комментарий']
 *            // Остальные комментарии будут удалены
 *        ]
 * ]);
 *
 *
 *
 * Инструкция:
 * # Создайте таблицу, в которой будут хранится привязанные данные
 * # Создайте модель для этой таблицы (например ArticlesCommentsTable)
 * # Укажите связь в основной модели в методе getMap() (например в ArticlesTable)
 * # В основной модели определите метод getMapParams()
 * Метод должен возвращать массив на подобии getMap(). В нем описываются дополнительные параметры полей
 * Вам нужно указать параметр manageable => []
 * Например:
 * return [
 *        'COMMENTS' => [
 *            'manageable' => [
 *                // Необязательные свойства
 *                // bool delete (по умолчанию false) - возможность удаления записи, поставьте false, если запись ни в коем случае не может быть удалена через основной класс
 *                // bool copy (по умолчанию false) - аналогично delete, только для копирования
 *                // bool create (по умолчанию false) - аналогично delete, только для создания
 *                // bool update (по умолчанию false) - аналогично delete, только для обновления
 *            ],
 *        ],
 * ];
 *
 * Дополнительные возможности:
 * # Если у вас нет возможности унаследовать данный класс, реализуйте такую же логику обработки событий в своём классе
 * и используйте трейт DataManagerTrait
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

	public function save()
	{
		ob_start();
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

		ShowMessage(ob_get_clean());

		return $result;
	}

	/**
	 * Удаление записи
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
	 * Изъятие данных для связей
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
					// Создание данных связи
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
	 * Удаление всех данных связей
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
		$fieldWidget = $this->getFieldWidget($reference->getName());
		if (!empty($referenceData[$fieldWidget->getMultipleField('ID')]))
		{
			throw new ArgumentException('Аргумент data не может содержать идентификатор элемента', 'data');
		}

		$refClass = $reference->getRefEntity()->getDataClass();

		return $refClass::add($referenceData);
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
		$fieldWidget = $this->getFieldWidget($reference->getName());
		if (empty($referenceData[$fieldWidget->getMultipleField('ID')]))
		{
			throw new ArgumentException('Аргумент data должен содержать идентификатор элемента', 'data');
		}

		// Сравнение старых данных и новых, обновляется только при различиях
		if ($this->isDifferentData($referenceStaleDataSet[$referenceData[$fieldWidget->getMultipleField('ID')]], $referenceData))
		{
			$refClass = $reference->getRefEntity()->getDataClass();
			$result = $refClass::update($referenceData[$fieldWidget->getMultipleField('ID')], $referenceData);

			return $result;
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
		$refClass = $reference->getRefEntity()->getDataClass();
		$result = $refClass::delete($referenceId);

		return $result;
	}

	/**
	 * Чтение связанной записи
	 *
	 * @param $reference
	 * @return array
	 */
	protected function getReferenceDataSet(Entity\ReferenceField $reference)
	{
		/** @var DataManager $modelClass */
		$modelClass = $this->modelClass;
		$dataSet = array();

		$rsData = $modelClass::getList(array('select' => array('REF_' => $reference->getName() . '.*'), 'filter' => array('=ID' => $this->modelId)));
		while ($data = $rsData->fetch())
		{
			if (empty($data['REF_ID']))
			{
				continue;
			}

			$row = array();
			foreach ($data as $key => $value)
			{
				$row[str_replace('REF_', '', $key)] = $value;
			}

			$dataSet[$data['REF_ID']] = $row;
		}

		return $dataSet;
	}

	/**
	 * Связывает данные связанной модели с основной сущностью
	 *
	 * @param Entity\ReferenceField $reference
	 * @param array $referenceData
	 * @return array
	 */
	protected function linkData(Entity\ReferenceField $reference, array $referenceData)
	{
		$referenceConditions = $this->getReferenceConditions($reference);

		foreach ($referenceConditions as $refField => $refValue)
		{
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
	 * Парсинг условий связи
	 *
	 * @param Entity\ReferenceField $reference Данные поля из getMap()
	 * @return array
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
	 * Получение виджета привязанного к полю
	 * @param $fieldName
	 * @return HelperWidget|bool
	 */
	protected function getFieldWidget($fieldName)
	{
		$fields = $this->adminHelper->getFields();
		if (isset($fields[$fieldName]) && isset($fields[$fieldName]['WIDGET']))
		{
			return $fields[$fieldName]['WIDGET'];
		}
		else
		{
			return false;
		}
	}
}