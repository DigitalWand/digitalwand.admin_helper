<?php

namespace DigitalWand\AdminHelper\Model;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity;

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
// TODO Использовать PK вместо ID
class EntityManager
{
	/**
	 * @var string Класс модели
	 */
	protected $modelClass;
	/**
	 * @var array Данные для обработки
	 */
	protected $modelData;
	/**
	 * @var integer ID модели
	 */
	protected $modelId = null;

	/**
	 * @var array Данные для связей
	 */
	protected $referencesData;

	public function __construct($modelClass, $modelData, $modelId = null)
	{
		$this->modelClass = $modelClass;
		$this->modelData = $modelData;
		$this->modelId = $modelId;
	}

	public function save()
	{
		echo "Save\n";
		$this->beforeSave();

		/** @var DataManager $modelClass */
		$modelClass = $this->modelClass;
		if (empty($this->modelId))
		{
			$result = $modelClass::add($this->modelData);
			if ($result->isSuccess())
			{
				$this->modelId = $result->getId();
			}
		}
		else
		{
			$result = $modelClass::update($this->modelId, $this->modelData);
		}

		return $result->isSuccess();
	}

	protected function beforeSave()
	{
		$this->collectReferencesData();
		$this->processReferencesData();
	}

	/**
	 * Получение связей
	 *
	 * @return array
	 */
	protected function getReferences()
	{
		echo "Сбор связей\n";
		$references = [];
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
		echo "Сбор данных связей\n";
		$references = $this->getReferences();

		// Извлечение данных управляемых связей
		foreach ($references as $fieldName => $reference)
		{
			if (!empty($entityData[$fieldName]))
			{
				// Извлечение данных для связи
				$this->referencesData[$fieldName] = $entityData[$fieldName];
				unset($this->modelData[$fieldName]);
			}
		}

		print_r($this->referencesData);
	}

	/**
	 * Обработка данных для связей
	 *
	 * @throws \ArgumentException
	 */
	protected function processReferencesData()
	{
		echo "Обработка данных связей\n";

		/** @var DataManager $modelClass */
		$modelClass = $this->modelClass;
		$entity = $modelClass::getEntity();
		$fields = $entity->getFields();

		foreach ($this->referencesData as $fieldName => $referenceDataSet)
		{
			/** @var Entity\ReferenceField $reference */
			$reference = $fields[$fieldName];
			$referenceDataSet = $this->linkDataSet($reference, $referenceDataSet, $this->modelData);
			$referenceStaleDataSet = $this->getReferenceDataSet($reference, $this->modelId);

			// Создание и обновление привязанных данных
			$processedDataIds = [];
			foreach ($referenceDataSet as $referenceData)
			{
				if (empty($referenceData['ID']))
				{
					$resultId = $this->createReferenceData($reference, $referenceData);

					if ($resultId)
					{
						$processedDataIds[] = $resultId;
					}
				}
				else
				{
					$this->updateReferenceData($reference, $referenceData, $referenceStaleDataSet, $this->modelData);

					if ($referenceData['ID'])
					{
						$processedDataIds[] = $referenceData['ID'];
					}
				}
			}

			// Удаление записей, которые не были созданы или обновлены
			foreach ($referenceStaleDataSet as $referenceData)
			{
				if (!in_array($referenceData['ID'], $processedDataIds))
				{
					$this->deleteReferenceData($reference, $referenceData['ID']);
				}
			}
		}

		$this->referencesData = [];
	}

	/**
	 * Удаление всех данных связей
	 *
	 * @param Entity\Event $event
	 */
	protected function deleteReferencesData(Entity\Event $event)
	{
		echo "Удаление данных связей\n";

		$references = $this->getReferences();
		$entityId = $event->getParameter('primary')['ID'];
		foreach ($references as $fieldName => $reference)
		{
			$referenceStaleDataSet = $this->getReferenceDataSet($reference, $entityId);
			foreach ($referenceStaleDataSet as $referenceData)
			{
				$this->deleteReferenceData($reference, $referenceData['ID']);
			}
		}
	}

	/**
	 * Создание связанной записи
	 *
	 * @param Entity\ReferenceField $reference
	 * @param array $referenceData
	 * @return bool|int
	 * @throws \ArgumentException
	 */
	protected function createReferenceData(Entity\ReferenceField $reference, array $referenceData)
	{
		echo "Создание данных связей\n";
		if (!empty($referenceData['ID']))
		{
			throw new \ArgumentException('Аргумент data не может содержать идентификатор элемента', 'data');
		}

		$refClass = $reference->getRefEntity()->getDataClass();
		$result = $refClass::add($referenceData);

		return ($result->isSuccess() ? $result->getId() : false);
	}

	/**
	 * Обновление связанной записи
	 *
	 * @param Entity\ReferenceField $reference
	 * @param array $referenceData
	 * @param array $entityData
	 * @param array $referenceStaleDataSet
	 * @return bool
	 * @throws \ArgumentException
	 */
	protected function updateReferenceData(
		Entity\ReferenceField $reference, array $referenceData, array $referenceStaleDataSet, array $entityData
	)
	{
		echo "Обновление данных связей\n";
		if (empty($referenceData['ID']))
		{
			throw new \ArgumentException('Аргумент data должен содержать идентификатор элемента', 'data');
		}

		// Сравнение старых данных и новых, обновляется только при различиях
		if ($this->isDifferentData($referenceStaleDataSet[$referenceData['ID']], $referenceData))
		{
			$refClass = $reference->getRefEntity()->getDataClass();
			$result = $refClass::update($entityData['ID'], $referenceData);

			return ($result->isSuccess() ? $referenceData['ID'] : false);
		}
		else
		{
			return $referenceData['ID'];
		}
	}

	/**
	 * Удаление данных связи
	 *
	 * @param Entity\ReferenceField $reference
	 * @param $referenceId
	 * @return bool|int
	 * @throws \ArgumentException
	 */
	protected function deleteReferenceData(Entity\ReferenceField $reference, $referenceId)
	{
		echo "Удаление данных связей\n";
		if (!empty($referenceData['ID']))
		{
			throw new \ArgumentException('Аргумент data не может содержать идентификатор элемента', 'data');
		}

		$refClass = $reference->getRefEntity()->getDataClass();
		$result = $refClass::delete($referenceId);

		return ($result->isSuccess() ? $referenceId : false);
	}

	/**
	 * Чтение связанной записи
	 *
	 * @param $reference
	 * @param $entityId
	 * @return array
	 */
	protected function getReferenceDataSet(Entity\ReferenceField $reference, $entityId)
	{
		echo "Чтение данных связи\n";
		$dataSet = [];
		$rsData = static::getList(['select' => ['REF_' => $reference->getName() . '.*'], 'filter' => ['=ID' => $entityId]]);
		while ($data = $rsData->fetch())
		{
			if (empty($data['REF_ID']))
			{
				continue;
			}

			$row = [];
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
	 * @param array $entityData
	 * @return array
	 */
	protected function linkData(Entity\ReferenceField $reference, array $referenceData, array $entityData)
	{
		echo "Связывание данных\n";
		$referenceConditions = $this->getReferenceConditions($reference);

		foreach ($referenceConditions as $thisField => $refField)
		{
			$referenceData[$refField] = $entityData[$thisField];
		}

		return $referenceData;
	}

	/**
	 * Связывает набор связанных данных с основной моделю
	 *
	 * @param Entity\ReferenceField $reference
	 * @param array $referenceDataSet
	 * @param array $entityData
	 * @return array
	 */
	protected function linkDataSet(Entity\ReferenceField $reference, array $referenceDataSet, array $entityData)
	{
		echo "Связывание набора данных\n";
		foreach ($referenceDataSet as $key => $referenceData)
		{
			$referenceDataSet[$key] = $this->linkData($reference, $referenceData, $entityData);
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
		echo "Парсинг условий\n";
		$conditionsFields = [];

		foreach ($reference->getReference() as $thisCondition => $refCondition)
		{
			$thisFieldMatch = [];
			$refFieldMatch = [];

			preg_match('/=this\.([A-z]+)/', $thisCondition, $thisFieldMatch);
			preg_match('/ref\.([A-z]+)/', $refCondition, $refFieldMatch);

			if (empty($thisFieldMatch[1]) || empty($refFieldMatch[1]))
			{
				continue;
			}
			else
			{
				$conditionsFields[$thisFieldMatch[1]] = $refFieldMatch[1];
			}
		}

		return $conditionsFields;
	}

	/**
	 * Обнаружение отличий массивов
	 * Метод не сранивает наличие аргументов, сравниваются только значения общих параметров
	 *
	 * @param $data1
	 * @param $data2
	 * @return bool
	 */
	protected function isDifferentData($data1, $data2)
	{
		echo "Проверка отличий данных\n";
		foreach ($data1 as $key => $value)
		{
			if (isset($data2[$key]) && $data2[$key] != $value)
			{
				return true;
			}
		}

		return false;
	}
}