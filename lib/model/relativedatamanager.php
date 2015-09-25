<?php

namespace DigitalWand\AdminHelper\Model;

use Bitrix\Main\Entity;
use Mos\Main\Db\DataManager;

// TODO Описать логику работы
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
 * Работа со связанными моделямя для хранения множественных полей
 * Класс автоматически создает/обновляет/удаляет данные связанных моделей на основе переданных связи данных (через свойство)
 * Возможности:
 * - создание
 * - обновление
 * - удаление (данные связи, для которых не были переданы данные будут удалятся)
 * - удаление связанных данных при удалении основной сущности
 * Инструкция:
 * - создайте таблицу для хранения данных множественных полей
 * Структура таблицы:
 * ID, ENTITY (str), ENTITY_ID (int), FIELD (str), VALUE
 * Поля ENTITY обязательно только если в одной таблице хранятся данные разных сущностей
 * Поле FIELD обязательно если в одной таблице хранятся данные разных полей
 * - создайте модель для данной таблицы
 * - создайте, в классе наследующем RelativeDataManager, связи с преждесозданной моделью
 * Связь должна быть объявлена по шаблону:
'FIELD_NAME' => [
'data_type' => '\Mos\NewsOiv\FieldsTable',
'reference' => [
'=this.ID' => 'ref.ENTITY_ID',
'ref.ENTITY' => new DB\SqlExpression('?s', static::getModelCode()), // Не обязательно, если в таблице хранятся данные одной сущности
'ref.FIELD' => new DB\SqlExpression('?s', 'FIELD_NAME'), // Не обязательно, если в таблице хранятся данные одного поля
],
// 'referenceAutoDelete' => true // Важно: если поставить данный параметр в true, то данные связи будут удалены, если их идентификаторы не переданы
// Так же этот параметр позволяет удалить все данные этой связи при удалении основной сущности
// Используйте с осторожностью
 * - для создания данных связи используйте RelativeDataManager::add([FIELD_NAME => [['VALUE' => 'test'], ['VALUE' => 'test']]]);
 * - для изменения данных связи используйте RelativeDataManager::add([FIELD_NAME => [['ID' => 'VALUE' => 'test'], ['ID' => 'VALUE' => 'test']]]);
 * Важно: если вы передали данных для связанного поля, то все связанные записи, ID которых там отсутствовал будут удалены
 */
// TODO Изменить логику удаления данных. Сделать удаление только
// если для FIELD_NAME передан __DELETE__ в содержании (для этого нужно доработать \DigitalWand\AdminHelper\Widget\HelperWidget::jsHelper)
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
					// Автоматически удаляются только связи с параметром referenceAutoDelete
					if (!empty($fieldDetails['reference']['referenceAutoDelete']))
					{
						$referenceClass::delete($referenceData['REFERENCE_ID']);
					}
				}
			}
		}
	}

	/**
	 * Удаление связанных моделей с параметром referenceAutoDelete
	 * @param Entity\Event $event
	 */
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

		// Удаление связанных данных
		static::deleteReferenceData($event);
	}
}