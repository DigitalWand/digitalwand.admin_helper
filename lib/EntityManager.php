<?php

namespace DigitalWand\AdminHelper;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity;
use DigitalWand\AdminHelper\Helper\AdminBaseHelper;
use DigitalWand\AdminHelper\Widget\HelperWidget;

/**
 * Менеджер для управления моделью, способный анализировать её связи и сохранять данные в связанные сущности на
 * основании полученных данных от виджетов.
 *
 * Пример создания сущности:
 * ```
 * $filmManager = new EntityManager('\Vendor\Module\FilmTable', array(
 *        // Данные сущности
 *        'TITLE' => 'Монстры на каникулах 2',
 *        'YEAR' => 2015,
 *        // У сущности FilmTable есть связь с RelatedLinksTable через поле RELATED_LINKS.
 *        // Если передать ей данные, то они будут обработаны
 *        // Представим, что у сущности RelatedLinksTable есть поля ID и VALUE (в этом поле хранится ссылка), FILM_ID
 *        // В большинстве случаев, данные передаваемые связям генерируются множественными виджетами
 *        'RELATED_LINKS' => array(
 *            // Переданный ниже массив будет обработан аналогично коду RelatedLinksTable::add(array('VALUE' =>
 * 'yandex.ru')); array('VALUE' => 'yandex.ru'),
 *            // Если в массив добавить ID, то запись обновится: RelatedLinksTable::update(3, array('ID' => 3, 'VALUE'
 * => 'google.com')); array('ID' => 3, 'VALUE' => 'google.com'),
 *            // ВНИМАНИЕ: данный класс реководствуется принципом: что передано для связи, то сохранится или обновится,
 * что не передано, будет удалено
 *            // То есть, если в поле связи RELATED_LINKS передать пустой массив, то все значения связи будут удалены
 *        )
 * ));
 * $filmManager->save();
 * ```
 *
 * Пример удаления сущности
 * ```
 * $articleManager = new EntityManager('\Vendor\Module\ArticlesTable', array(), 7, $adminHelper);
 * $articleManager->delete();
 * ```
 *
 * Как работает сохранение данных ? Дополнительный пример
 * Допустим, что есть модели NewsTable (новости) и NewsLinksTable (ссылки на дополнительную информацию о новости)
 *
 * У модели NewsTable есть связь с моделью NewsLinksTable через поле NEWS_LINKS:
 * ```
 * DataManager::getMap() {
 * ...
 * new Entity\ReferenceField(
 *        'NEWS_LINKS',
 *        '\Vendor\Module\NewsLinksTable',
 *        array('=this.ID' => 'ref.NEWS_ID'),
 *        'ref.FIELD' => new DB\SqlExpression('?s', 'NEWS_LINKS'),
 *        'ref.ENTITY' => new DB\SqlExpression('?s', 'news'),
 * ),
 * ...
 * }
 * ```
 *
 * Попробуем сохранить
 * ```
 * $newsManager = new EntityManager(
 *        '\Vendor\Module\NewsTable',
 *        array(
 *            'TITLE' => 'News title',
 *            'CONTENT' => 'News content',
 *            'NEWS_LINKS' => array(
 *                array('LINK' => 'test.ru'),
 *                array('LINK' => 'test2.ru'),
 *                array('ID' => 'id ссылки', 'LINK' => 'test3.ru'),
 *            )
 *        ),
 *        null,
 *        $adminHelper
 * );
 * $newsManager->save();
 * ```
 *
 * В данном примере передаются данные для новости (заголовок и содержимое) и данные для поля-связи NEWS_LINKS.
 *
 * Далее EntityManager:
 * 1. Вырезает данные, которые предназначены связям
 * 2. Подставляет в них данные из основной модели на основе условий связи
 * Например для связи с полем NEWS_LINKS подставятся данные:
 *
 * ```
 * NewsLinksTable::ENTITY_ID => NewsTable::ID,
 * NewsLinksTable::FIELD => 'NEWS_LINKS',
 * NewsLinksTable::ENTITY => 'news'
 * ```
 *
 * 3. После подстановки данных они будут переданы модели связи подобно коду ниже:
 *
 * ```
 * NewsLinksTable::add(array('ENTITY' => 'news', 'FIELD' => 'NEWS_LINKS', 'ENTITY_ID' => 'id сущности, например
 * новости', 'LINK' => 'test.ru')); NewsLinksTable::add(array('ENTITY' => 'news', 'FIELD' => 'NEWS_LINKS', 'ENTITY_ID'
 * => 'id сущности', 'LINK' => 'test2.ru')); NewsLinksTable::update('id ссылки', array('ENTITY' => 'news', 'FIELD' =>
 * 'NEWS_LINKS', 'ENTITY_ID' => 'id сущности', 'LINK' => 'test3.ru'));
 * ```
 *
 * Обратите внимание, что в метод EntityManager::save() были изначально передано только поле LINK, поля ENTITY,
 * ENTITY_ID и FIELD были подставлены классом EntityManager автоматически (предыдущий пункт) А так же важно, что для
 * третьей ссылки был передан идентификатор, поэтому выполнился NewsLinksTable::update, а не NewsLinksTable::add
 *
 * 4. Далее `EntityManager` удаляет данные связанной модели `NewsLinksTable`, которые не были добавлены или обновлены.
 *
 * <b>Как работает удаление?</b>
 *
 * 1. EntityManager получает из `NewsTable::getMap()` поля-связи
 * 2. Получает поля описанные в интерфейсе генератора админки
 * 3. Удаляет значения для полей-связей, которые объявлены в интерфейсе
 *
 * <i>Примечание.</i>
 * EntityManager управляет только данными, которые получает при помощи связи стандартными средставами битрикса.
 * Например, при удалении NewsTable будут удалены только NewsLinksTable, где:
 *
 * ```
 * NewsLinksTable::ENTITY_ID => NewsTable::ID,
 * NewsLinksTable::FIELD => 'NEWS_LINKS',
 * NewsLinksTable::ENTITY => 'news'
 * ```
 *
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 * @author Dmitriy Baibuhtin <dmitriy.baibuhtin@ya.ru>
 */
class EntityManager
{
	/**
	 * @var string Класс модели.
	 */
	protected $modelClass;
	/**
	 * @var Entity\Base Сущность модели.
	 */
	protected $model;
	/**
	 * @var array Данные для обработки.
	 */
	protected $data;
	/**
	 * @var integer Идентификатор записи.
	 */
	protected $itemId = null;
	/**
	 * @var string Поле модели, в котором хранится идентификатор записи.
	 */
	protected $modelPk = null;
	/**
	 * @var array Данные для связей.
	 */
	protected $referencesData;
	/**
	 * @var AdminBaseHelper Хелпер.
	 */
	protected $helper;
	/**
	 * @var array Предупреждения.
	 */
	protected $notes = array();

	/**
	 * @param string $modelClass Класс основной модели, наследника DataManager.
	 * @param array $data Массив с сохраняемыми данными.
	 * @param integer $itemId Идентификатор сохраняемой записи.
	 * @param AdminBaseHelper $helper Хелпер, инициирующий сохранение записи.
	 */
	public function __construct($modelClass, array $data = array(), $itemId = null, AdminBaseHelper $helper)
	{
		Loc::loadMessages(__FILE__);
		
		$this->modelClass = $modelClass;
		$this->model = $modelClass::getEntity();
		$this->data = $data;
		$this->modelPk = $this->model->getPrimary();
		$this->helper = $helper;

		if (!empty($itemId)) {
			$this->setItemId($itemId);
		}
	}

    /**
     * Сохранить запись и данные связей.
     *
     * @return Entity\AddResult|Entity\UpdateResult
     */
    public function save()
    {
        $this->collectReferencesData();

        /**
         * @var DataManager $modelClass
         */
        $modelClass = $this->modelClass;
		$db = $this->model->getConnection();
		$db->startTransaction(); // начало транзакции

		if (empty($this->itemId)) {
			$result = $modelClass::add($this->data);

			if ($result->isSuccess()) {
				$this->setItemId($result->getId());
			}
		}
		else {
			$result = $modelClass::update($this->itemId, $this->data);
		}

        if ($result->isSuccess()) {
			$referencesDataResult = $this->processReferencesData();
			if($referencesDataResult->isSuccess()){
				$db->commitTransaction(); // ошибок нет - применяем изменения
			}else{
				$result = $referencesDataResult; // возвращаем ReferencesResult что бы вернуть ошибку
				$db->rollbackTransaction(); // что-то пошло не так - возвращаем все как было
			}
		} else {
			$db->rollbackTransaction();
		}

		return $result;
	}

    /**
     * Удаление запись и данные связей.
     *
     * @return Entity\DeleteResult
     */
    public function delete()
    {
        // Удаление данных зависимостей
		$db = $this->model->getConnection();
		$db->startTransaction(); // начало транзакции

		$result = $this->deleteReferencesData(); // удаляем зависимые сущности

		if(!$result->isSuccess()){ // если хотя бы одна из них не удалилась
			$db->rollbackTransaction(); // то восстанавливаем все
			return $result; // возвращаем ошибку
		}

		$model = $this->modelClass;

        $result = $model::delete($this->itemId); // удаляем основную сущность
		if(!$result->isSuccess()){  // если не удалилась
			$db->rollbackTransaction(); // то восстанавливаем зависимые сущности
			return $result; // возвращаем ошибку
		}

		$db->commitTransaction(); // все прошло без ошибок применяем изменения
		return $result;
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
	 *
	 * @param $note
	 * @param string $key Ключ для избежания дублирования сообщений
	 *
	 * @return bool
	 */
	protected function addNote($note, $key = null)
	{
		if ($key) {
			$this->notes[$key] = $note;
		}
		else {
			$this->notes[] = $note;
		}

		return true;
	}

	/**
	 * Установка текущего идентификатора модели.
	 *
	 * @param integer $itemId Идентификатор записи.
	 */
	protected function setItemId($itemId)
	{
		$this->itemId = $itemId;
		$this->data[$this->modelPk] = $this->itemId;
	}

	/**
	 * Получение связей
	 *
	 * @return array
	 */
	protected function getReferences()
	{
		/**
		 * @var DataManager $modelClass
		 */
		$modelClass = $this->modelClass;
		$entity = $modelClass::getEntity();
		$fields = $entity->getFields();
		$references = array();

		foreach ($fields as $fieldName => $field) {
			if ($field instanceof Entity\ReferenceField) {
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
		$result = new Entity\Result();
		$references = $this->getReferences();
		// Извлечение данных управляемых связей
		foreach ($references as $fieldName => $reference) {
			if (array_key_exists($fieldName, $this->data)) {
				if (!is_array($this->data[$fieldName])) {
					$result->addError(new Entity\EntityError('Связь должна быть множественным полем'));

					return $result;
				}
				// Извлечение данных для связи
				$this->referencesData[$fieldName] = $this->data[$fieldName];
				unset($this->data[$fieldName]);
			}
		}

		return $result;
	}

    /**
     * Обработка данных для связей.
     *
     * @throws ArgumentException
     */
    protected function processReferencesData()
    {
        /**
         * @var DataManager $modelClass
         */
        $modelClass = $this->modelClass;
        $entity = $modelClass::getEntity();
        $fields = $entity->getFields();
		$result = new Entity\Result(); // пустой Result у которого isSuccess вернет true

		foreach ($this->referencesData as $fieldName => $referenceDataSet) {
			if (!is_array($referenceDataSet)) {
				continue;
			}

			/**
			 * @var Entity\ReferenceField $reference
			 */
			$reference = $fields[$fieldName];
			$referenceDataSet = $this->linkDataSet($reference, $referenceDataSet);
			$referenceStaleDataSet = $this->getReferenceDataSet($reference);
			$fieldWidget = $this->getFieldWidget($fieldName);

			// Создание и обновление привязанных данных
			$processedDataIds = array();
			foreach ($referenceDataSet as $referenceData) {
				if (empty($referenceData[$fieldWidget->getMultipleField('ID')])) {
					// Создание связанных данных
					if (!empty($referenceData[$fieldWidget->getMultipleField('VALUE')])) {
						$result = $this->createReferenceData($reference, $referenceData);

                        if ($result->isSuccess()) {
                            $processedDataIds[] = $result->getId();
                        } else {
							break; // ошибка, прерываем обработку данных
						}
                    }
                } else {
                    // Обновление связанных данных
					$result = $this->updateReferenceData($reference, $referenceData, $referenceStaleDataSet);

                    if ($result->isSuccess()) {
                        $processedDataIds[] = $referenceData[$fieldWidget->getMultipleField('ID')];
                    } else {
						break; // ошибка, прерываем обработку данных
					}
                }
            }

			if($result->isSuccess()){ // Удаление записей, которые не были созданы или обновлены
				foreach ($referenceStaleDataSet as $referenceData) {
					if (!in_array($referenceData[$fieldWidget->getMultipleField('ID')], $processedDataIds)) {
						$result = $this->deleteReferenceData($reference,
							$referenceData[$fieldWidget->getMultipleField('ID')]);
						if(!$result->isSuccess()) {
							break; // ошибка, прерываем удаление данных
						}
					}
				}
			}
        }

        $this->referencesData = array();
		return $result;
    }

    /**
     * Удаление данных всех связей, которые указаны в полях интерфейса раздела.
     */
    protected function deleteReferencesData()
    {
        $references = $this->getReferences();
        $fields = $this->helper->getFields();
		$result = new Entity\Result();
        /**
         * @var string $fieldName
         * @var Entity\ReferenceField $reference
         */
        foreach ($references as $fieldName => $reference) {
            // Удаляются только данные связей, которые объявлены в интерфейсе
            if (!isset($fields[$fieldName])) {
                continue;
            }

			$fieldWidget = $this->getFieldWidget($reference->getName());
			$referenceStaleDataSet = $this->getReferenceDataSet($reference);

            foreach ($referenceStaleDataSet as $referenceData) {
                $result = $this->deleteReferenceData($reference, $referenceData[$fieldWidget->getMultipleField('ID')]);
				if(!$result->isSuccess()){
					return $result;
				}
            }
        }
		return $result;
    }

	/**
	 * Создание связанной записи.
	 *
	 * @param Entity\ReferenceField $reference
	 * @param array $referenceData
	 *
	 * @return \Bitrix\Main\Entity\AddResult
	 * @throws ArgumentException
	 */
	protected function createReferenceData(Entity\ReferenceField $reference, array $referenceData)
	{
		$referenceName = $reference->getName();
		$fieldParams = $this->getFieldParams($referenceName);
		$fieldWidget = $this->getFieldWidget($referenceName);

		if (!empty($referenceData[$fieldWidget->getMultipleField('ID')])) {
			throw new ArgumentException('Аргумент data не может содержать идентификатор элемента', 'data');
		}

		$refClass = $reference->getRefEntity()->getDataClass();

		$createResult = $refClass::add($referenceData);

		if (!$createResult->isSuccess()) {
			$this->addNote(Loc::getMessage('DIGITALWAND_ADMIN_HELPER_RELATION_SAVE_ERROR',
				array('#FIELD#' => $fieldParams['TITLE'])), 'CREATE_' . $referenceName);
		}

		return $createResult;
	}

	/**
	 * Обновление связанной записи
	 *
	 * @param Entity\ReferenceField $reference
	 * @param array $referenceData
	 * @param array $referenceStaleDataSet
	 *
	 * @return Entity\UpdateResult|null
	 * @throws ArgumentException
	 */
	protected function updateReferenceData(
		Entity\ReferenceField $reference,
		array $referenceData,
		array $referenceStaleDataSet
	)
	{
		$referenceName = $reference->getName();
		$fieldParams = $this->getFieldParams($referenceName);
		$fieldWidget = $this->getFieldWidget($referenceName);

		if (empty($referenceData[$fieldWidget->getMultipleField('ID')])) {
			throw new ArgumentException('Аргумент data должен содержать идентификатор элемента', 'data');
		}

		// Сравнение старых данных и новых, обновляется только при различиях
		if ($this->isDifferentData($referenceStaleDataSet[$referenceData[$fieldWidget->getMultipleField('ID')]],
			$referenceData)
		) {
			$refClass = $reference->getRefEntity()->getDataClass();
			$updateResult = $refClass::update($referenceData[$fieldWidget->getMultipleField('ID')], $referenceData);

			if (!$updateResult->isSuccess()) {
				$this->addNote(Loc::getMessage('DIGITALWAND_ADMIN_HELPER_RELATION_SAVE_ERROR',
					array('#FIELD#' => $fieldParams['TITLE'])), 'UPDATE_' . $referenceName);
			}

            return $updateResult;
        } else {
            return new Entity\Result(); // пустой Result у которого isSuccess() вернет true
        }
    }

	/**
	 * Удаление данных связи.
	 *
	 * @param Entity\ReferenceField $reference
	 * @param $referenceId
	 *
	 * @return \Bitrix\Main\Entity\Result
	 * @throws ArgumentException
	 */
	protected function deleteReferenceData(Entity\ReferenceField $reference, $referenceId)
	{
		$fieldParams = $this->getFieldParams($reference->getName());
		$refClass = $reference->getRefEntity()->getDataClass();
		$deleteResult = $refClass::delete($referenceId);

		if (!$deleteResult->isSuccess()) {
			$this->addNote(Loc::getMessage('DIGITALWAND_ADMIN_HELPER_RELATION_DELETE_ERROR',
				array('#FIELD#' => $fieldParams['TITLE'])), 'DELETE_' . $reference->getName());
		}

		return $deleteResult;
	}

	/**
	 * Получение данных связи.
	 *
	 * @param $reference
	 *
	 * @return array
	 */
	protected function getReferenceDataSet(Entity\ReferenceField $reference)
	{
		/**
		 * @var DataManager $modelClass
		 */
		$modelClass = $this->modelClass;
		$dataSet = array();
		$fieldWidget = $this->getFieldWidget($reference->getName());

		$rsData = $modelClass::getList(array(
			'select' => array('REF_' => $reference->getName() . '.*'),
			'filter' => array('=' . $this->modelPk => $this->itemId)
		));

		while ($data = $rsData->fetch()) {
			if (empty($data['REF_' . $fieldWidget->getMultipleField('ID')])) {
				continue;
			}

			$row = array();
			foreach ($data as $key => $value) {
				$row[str_replace('REF_', '', $key)] = $value;
			}

			$dataSet[$data['REF_' . $fieldWidget->getMultipleField('ID')]] = $row;
		}

		return $dataSet;
	}

	/**
	 * В данные связи подставляются данные основной модели используя условия связи моделей из getMap().
	 *
	 * @param Entity\ReferenceField $reference
	 * @param array $referenceData Данные привязанной модели
	 *
	 * @return array
	 */
	protected function linkData(Entity\ReferenceField $reference, array $referenceData)
	{
		// Парсим условия связи двух моделей
		$referenceConditions = $this->getReferenceConditions($reference);

		foreach ($referenceConditions as $refField => $refValue) {
			// Так как в условиях связи между моделями в основном отношения типа this.field => ref.field или
			// ref.field => SqlExpression, мы можем использовать это для подстановки данных
			// this.field - это поле основной модели
			// ref.field - поле модели из связи
			// customValue - это строка полученная из new SqlExpression('%s', ...)
			if (empty($refValue['thisField'])) {
				$referenceData[$refField] = $refValue['customValue'];
			}
			else {
				$referenceData[$refField] = $this->data[$refValue['thisField']];
			}
		}

		return $referenceData;
	}

	/**
	 * Связывает набор связанных данных с основной моделю.
	 *
	 * @param Entity\ReferenceField $reference
	 * @param array $referenceDataSet
	 *
	 * @return array
	 */
	protected function linkDataSet(Entity\ReferenceField $reference, array $referenceDataSet)
	{
		foreach ($referenceDataSet as $key => $referenceData) {
			$referenceDataSet[$key] = $this->linkData($reference, $referenceData);
		}

		return $referenceDataSet;
	}

	/**
	 * Парсинг условий связи между моделями.
	 *
	 * Ничего сложного нет, просто определяются соответствия полей основной модели и модели из связи. Например:
	 *
	 * `FilmLinksTable::FILM_ID => FilmTable::ID (ref.FILM_ID => this.ID)`
	 *
	 * Или, например:
	 *
	 * `MediaTable::TYPE => 'FILM' (ref.TYPE => new DB\SqlExpression('?s', 'FILM'))`
	 *
	 * @param Entity\ReferenceField $reference Данные поля из getMap().
	 *
	 * @return array Условия связи преобразованные в массив вида $conditions[$refField]['thisField' => $thisField,
	 *     'customValue' => $customValue].
	 *      $customValue - это результат парсинга SqlExpression.
	 *      Если шаблон SqlExpression не равен %s, то условие исключается из результата.
	 */
	protected function getReferenceConditions(Entity\ReferenceField $reference)
	{
		$conditionsFields = array();

		foreach ($reference->getReference() as $leftCondition => $rightCondition) {
			$thisField = null;
			$refField = null;
			$customValue = null;

			// Поиск this.... в левом условии
			$thisFieldMatch = array();
			$refFieldMatch = array();
			if (preg_match('/=this\.([A-z]+)/', $leftCondition, $thisFieldMatch) == 1) {
				$thisField = $thisFieldMatch[1];
			} // Поиск ref.... в левом условии
			else {
				if (preg_match('/ref\.([A-z]+)/', $leftCondition, $refFieldMatch) == 1) {
					$refField = $refFieldMatch[1];
				}
			}

			// Поиск expression value... в правом условии
			$refFieldMatch = array();
			if ($rightCondition instanceof \Bitrix\Main\DB\SqlExpression) {
				$customValueDirty = $rightCondition->compile();
				$customValue = preg_replace('/^([\'"])(.+)\1$/', '$2', $customValueDirty);
				if ($customValueDirty == $customValue) {
					// Если значение выражения не обрамлено кавычками, значит оно не нужно нам
					$customValue = null;
				}
			} // Поиск ref.... в правом условии
			else {
				if (preg_match('/ref\.([A-z]+)/', $rightCondition, $refFieldMatch) > 0) {
					$refField = $refFieldMatch[1];
				}
			}

			// Если не указано поле, которое нужно заполнить или не найдено содержимое для него, то исключаем условие
			if (empty($refField) || (empty($thisField) && empty($customValue))) {
				continue;
			}
			else {
				$conditionsFields[$refField] = array(
					'thisField' => $thisField,
					'customValue' => $customValue,
				);
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
	 *
	 * @return bool
	 */
	protected function isDifferentData(array $data1 = null, array $data2 = null)
	{
		foreach ($data1 as $key => $value) {
			if (isset($data2[$key]) && $data2[$key] != $value) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $fieldName
	 *
	 * @return array|bool
	 */
	protected function getFieldParams($fieldName)
	{
		$fields = $this->helper->getFields();

		if (isset($fields[$fieldName]) && isset($fields[$fieldName]['WIDGET'])) {
			return $fields[$fieldName];
		}
		else {
			return false;
		}
	}

	/**
	 * Получение виджета привязанного к полю.
	 *
	 * @param $fieldName
	 *
	 * @return HelperWidget|bool
	 */
	protected function getFieldWidget($fieldName)
	{
		$field = $this->getFieldParams($fieldName);

		return isset($field['WIDGET']) ? $field['WIDGET'] : null;
	}
}