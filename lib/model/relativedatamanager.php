<?php

namespace DigitalWand\AdminHelper\Model;

use Bitrix\Main\Entity;
use Mos\Main\Db\DataManager;

// TODO Переименовать и переместить в генератор админки. Реализовать в виде трейта
// TODO Описать логику работы
// TODO Решить проблему удаления привязанных полей при удалении сущности
/**
 * Класс добавляет возможность автоматически управлять данными связанных сущностей
 * Для этого нужно определить связь. После при создании или обновлении можно передавать данные связей
 * в качестве свойства DataManager. Названия свойства = названию связи.
 * Данные могут быть как для одной привязанной сущности так и для нескольких
 * Пример создания одной связанной сущности: 'LYRICS' => ['TEXT' => 'Тестовый текст']
 * Пример создания нескольких связанных сущностей: 'LYRICS' => [['TEXT' => 'Тестовый текст'], ['TEXT' => 'Тестовый текст 2']]
 * Связи должны быть объявлены с помощью ReferenceField
 * Связь не должна быть по ref.ID, иначе записи будут всегда обновляться
 * :IMPORTANT При удалении родительской сущности, все дочернии так же будут удалены!!!
 */

/**
 * Работа со связанными моделям для хранения множественных полей
 * Класс автоматически создает/обновляет/удаляет данные связанных моделей на основе переданных в свойство этой связи данных
 * Возможности:
 * - создание
 * - обновление
 * - удаление неиспользуемых данных связей (TODO удалять, только если передан пустой параметр, если же он вообще не передан, не удалять)
 * - TODO полное удаление привязанных данных (опционально)
 */
abstract class RelativeDataManager extends DataManager
{
	/** @var array Данные для привязанных сущностей */
	protected static $referencesToSave = [];

	/**
	 * Текстовый идентификатор модели в lowercase. Например для модели NewsTable - news
	 * @return mixed
	 */
	abstract static function getModelCode();

	/**
	 * Извлечение данных переданных для обработки связанными моделями
	 * @param Entity\Event $event
	 * @return Entity\EventResult
	 */
	protected static function ejectReferencesData(Entity\Event $event)
	{
		$result = new Entity\EventResult;
		$entityData = $event->getParameter('fields');

		// Извлечение связей для которых переданы данные для последующей обработки
		static::$referencesToSave = [];
		foreach (static::getMap() as $fieldName => $fieldData)
		{
			if (is_array($fieldData) && isset($fieldData['reference']))
			{
				// Если для связи переданы данные, извлекаем их и удаляем из сущности, так как битриксу они не нужны
				// Массив переданный связи должен содержать массивы с данными для обработки
				// Обрабатываются только связи для которых переданы данные (пустой массив тоже считается)
				if (isset($entityData[$fieldName]))
				{
					// TODO Проверить не удаляются ли связи для которых не переданы данные
					if (!is_array(reset($entityData[$fieldName])))
					{
						$entityData[$fieldName] = [$entityData[$fieldName]];
					}

					$result->unsetField($fieldName);

					static::$referencesToSave[$fieldName] = ['data' => $entityData[$fieldName], 'reference' => $fieldData];
				}
			}
		}

		return $result;
	}

	/**
	 * Обработка данных переданных для обработки связанными моделями
	 * @param Entity\Event $event
	 */
	protected static function saveReferenceData(Entity\Event $event)
	{
		// TODO Транзакции
		/** @var array $entityData Все данные сущности (ключи учтены) */
		$entityData = array_merge($event->getParameter('fields'), $event->getParameter('primary'));

		// Сохранение данных для привязанных сущностей
		foreach (static::$referencesToSave as $fieldName => $fieldDetails)
		{
			/** @var string $referenceClass DataManager привязанной сущности */
			$referenceClass = $fieldDetails['reference']['data_type'];
			/** @var Base $referenceEntity Сущность */
			$referenceEntity = $referenceClass::getEntity();
			/** @var array[] $referenceNewDataSet Набор данных для привязанной сущности */
			$referenceNewDataSet = $fieldDetails['data'];
			/** @var array $linkingFields Поля по которым связаны сущности (this => ref) */
			$linkingFields = static::getReferenceConditions($fieldDetails['reference']);

			// Обработка данных связанной модели
			$processedDataIds = [];
			foreach ($referenceNewDataSet as $referenceNewData)
			{
				// Дополнение данных для реализации связи с моделью (в основном заполняет только ID)
				foreach ($linkingFields as $thisField => $referenceField)
				{
					$referenceNewData[$referenceField] = $entityData[$thisField];
				}
				// Дополнение данных для связей хранящих в себе данные разных полей и сущностей
				if ($referenceEntity->hasField('ENTITY'))
				{
					$referenceNewData['ENTITY'] = static::getModelCode();
				}
				if ($referenceEntity->hasField('FIELD'))
				{
					$referenceNewData['FIELD'] = $fieldName;
				}

				/* Обработка данных связей
				 * Если запись без идентификатора - создается
				 * Если с идентификатором - обновляется
				 * Если запись существует, но не передана, то удаляется
				 */
				if (empty($referenceNewData['ID']))
				{
					// Создание данных связи
					if (!empty($referenceNewData['VALUE']))
					{
						$referenceAddData = $referenceClass::add($referenceNewData);
						$processedDataIds[] = $referenceAddData->getId();
					}
				}
				else
				{
					// TODO Обновлять только при различии данных
					// Обновление данных связи
					// У записи есть ID, значит запись существует и нужно её обновить
					if (!empty($referenceNewData['VALUE']))
					{
						$referenceClass::update($referenceNewData['ID'], $referenceNewData);
					}
					$processedDataIds[] = $referenceNewData['ID'];
				}
			}
			// Поиск данных привязанных к текущему полю данной сущности.
			// Если данные найдены, но в сущность переданы не были, значит удаляем
			$dbReferenceData = static::getList([
				'select' => ['REFERENCE_' => $fieldName . '.*'],
				'filter' => ['=ID' => $entityData['ID']]
			]);
			while ($referenceData = $dbReferenceData->fetch())
			{
				if (empty($referenceData['REFERENCE_ID']))
				{
					continue;
				}

				if (!in_array($referenceData['REFERENCE_ID'], $processedDataIds))
				{
					$referenceClass::delete($referenceData['REFERENCE_ID']);
				}
			}
		}
	}

	protected static function deleteReferenceData(Entity\Event $event)
	{
		/** @var array $entityData Все данные сущности (ключи учтены) */
		$entityId = $event->getParameter('primary')['ID'];

		foreach (static::getMap() as $fieldName => $fieldDetails)
		{
			// Удаляются только связи с флагом referenceAutoDelete
			if (!is_array($fieldDetails)
				|| empty($fieldDetails['reference'])
				|| empty($fieldDetails['referenceAutoDelete'])
			)
			{
				continue;
			}

			/** @var string $referenceClass DataManager привязанной сущности */
			$referenceClass = $fieldDetails['data_type'];
			// Поиск данных для текущего поля. Если данные найдены, но в сущность переданы не были, значит удаляем
			$dbReferenceData = static::getList([
				'select' => ['REFERENCE_' => $fieldName . '.*'],
				'filter' => ['=ID' => $entityId]
			]);
			while ($referenceData = $dbReferenceData->fetch())
			{
				if (empty($referenceData['REFERENCE_ID']))
				{
					continue;
				}

				$referenceClass::delete($referenceData['REFERENCE_ID']);
			}
		}
	}

	/**
	 * Парсинг условий связи
	 * @param array $referenceField Данные поля из getMap()
	 * @return array
	 */
	protected static function getReferenceConditions($referenceField)
	{
		if (empty($referenceField['reference']))
		{
			return false;
		}

		$conditions = [];

		foreach ($referenceField['reference'] as $thisCondition => $refCondition)
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
				$conditions[$thisFieldMatch[1]] = $refFieldMatch[1];
			}
		}

		return $conditions;
	}

	public static function onBeforeAdd(Entity\Event $event)
	{
		parent::onBeforeAdd($event);

		// Сбор данных для обработки
		return static::ejectReferencesData($event);
	}

	public static function onAfterAdd(Entity\Event $event)
	{
		parent::onAfterAdd($event);
		// Обработка данных
		static::saveReferenceData($event);
	}

	public static function onBeforeUpdate(Entity\Event $event)
	{
		parent::onBeforeUpdate($event);

		// Сбор и обработка данных
		$result = static::ejectReferencesData($event);
		static::saveReferenceData($event);

		return $result;
	}

	public static function onBeforeDelete(Entity\Event $event)
	{
		parent::onBeforeDelete($event);

		// Сбор и обработка данных
		static::deleteReferenceData($event);
	}
}