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
 * Работа со связанными моделям
 * Основная задача класса - автоматическое управление данными связей используя карту модели
 * Возможности:
 * - создание данных связей
 * - обновление данных связей
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

	protected static function ejectReferencesData(Entity\Event $event)
	{
		$result = new Entity\EventResult;
		$entityData = $event->getParameter('fields');

		// Получение связей
		$references = [];
		foreach (static::getMap() as $field)
		{
			if ($field instanceof Entity\ReferenceField)
			{
				$references[] = $field;
			}
		}

		// Извлечение данных для связей
		static::$referencesToSave = [];
		foreach ($references as $reference)
		{
			$referenceName = $reference->getName();
			// Если для связи переданы данные, извлекаем их и удаляем из сущности, так как битриксу они не нужны
			if (isset($entityData[$referenceName]))
			{
				// Должно быть массивом массивов
				if (!is_array(reset($entityData[$referenceName])))
				{
					$entityData[$referenceName] = [$entityData[$referenceName]];
				}

				$result->unsetField($referenceName);
				static::$referencesToSave[$referenceName] = ['data' => $entityData[$referenceName], 'reference' => $reference];
			}
		}

		return $result;
	}

	protected static function saveReferences(Entity\Event $event)
	{
		// TODO Транзакции
		/** @var array $entityData Все данные сущности (ключи учтены) */
		$entityData = array_merge($event->getParameter('fields'), $event->getParameter('primary'));

		// Сохранение данных для привязанных сущностей
		foreach (static::$referencesToSave as $fieldName => $field)
		{
			/** @var string $referenceClass DataManager привязанной сущности */
			$referenceClass = $field['reference']->getRefEntity()->getDataClass();
			/** @var array[] $referenceDataSet Набор данных для привязанной сущности */
			$referenceDataSet = $field['data'];
			/** @var array $referenceConditions Описание связи между сущностями */
			$referenceConditions = $field['reference']->getReference();

			/**
			 * @var string $thisFieldCondition Левая часть связи (this)
			 * @var string $referenceFieldCondition Правая часть связи (ref)
			 * @var array $referenceConditionFields Название поля текущей сущности => название поля связанной сущности
			 */
			$referenceConditionFields = [];
			// Сбор полей связывающих сущности
			foreach ($referenceConditions as $thisFieldCondition => $referenceFieldCondition)
			{
				// Извлечение названий полей для связи
				$thisFieldMatch = [];
				preg_match('/=this\.([A-z]+)/', $thisFieldCondition, $thisFieldMatch);
				if (empty($thisFieldMatch[1]))
				{
					continue;
				}
				$refFieldMatch = [];
				preg_match('/ref\.([A-z]+)/', $referenceFieldCondition, $refFieldMatch);
				if (empty($refFieldMatch[1]))
				{
					continue;
				}

				/** @var string $thisFieldName Название поля текущей сущности */
				$thisFieldName = $thisFieldMatch[1];
				/** @var string $referenceFieldName Название поля зависимой сущности */
				$referenceFieldName = $refFieldMatch[1];

				$referenceConditionFields[$thisFieldName] = $referenceFieldName;
			}

			// Создание записей на основе набора данных переданных связи
			foreach ($referenceDataSet as $referenceNewData)
			{
				// Дополнение данными текущей сущности для связи
				foreach ($referenceConditionFields as $thisField => $referenceField)
				{
					$referenceNewData[$referenceField] = $entityData[$thisField];
				}

				// TODO Удаление непереданных данных
				$referenceEntity = $referenceClass::getEntity();
				$referencePk = $referenceEntity->getPrimary();

				if ($referenceEntity->hasField('ENTITY'))
				{
					$referenceNewData['ENTITY'] = static::getModelCode();
				}
				if ($referenceEntity->hasField('FIELD'))
				{
					$referenceNewData['FIELD'] = $fieldName;
				}

				$processedDataIds = [];
				if (empty($referenceNewData[$referencePk]))
				{
					// Запись без ID, значит новая
					if (!empty($referenceNewData['VALUE']))
					{
						$addRefData = $referenceClass::add($referenceNewData);
						$processedDataIds[] = $addRefData->getId();
					}
				}
				else
				{
					// TODO Обновлять только при различии данных
					// У записи есть ID, значит запись существует и нужно её обновить
					if (!empty($referenceNewData['VALUE']))
					{
						$referenceClass::update($referenceNewData[$referencePk], $referenceNewData);
					}
					$processedDataIds[] = $referenceNewData[$referencePk];
				}

				$dbReferenceData = static::getList(['select' => [$fieldName], 'filter' => ['=ID' => $entityData['ID']]]);
				while ($referenceData = $dbReferenceData->fetch())
				{
					// TODO Написать свой метод получения (или обработки) результатов связанных сущностей и заменить это
					if (empty($prefix))
					{
						// Определение приставки для полей связанной сущности
						$prefix = str_replace('ID', '', array_keys($referenceData)[0]);
					}
					if (empty($arData[$prefix . 'ID']))
					{
						continue;
					}
				}
			}
		}
	}

	public static function onBeforeAdd(Entity\Event $event)
	{
		parent::onBeforeAdd($event);

		return static::ejectReferencesData($event);
	}

	public static function onAfterAdd(Entity\Event $event)
	{
		static::saveReferences($event);

		parent::onAfterAdd($event);
	}

	public static function onBeforeUpdate(Entity\Event $event)
	{
		$result = static::ejectReferencesData($event);
		static::saveReferences($event);

		parent::onBeforeUpdate($event);

		return $result;
	}
}