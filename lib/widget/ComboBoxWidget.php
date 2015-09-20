<?php

namespace DigitalWand\AdminHelper\Widget;

/**
 * Class ComboBoxWidget Выпадающий список
 * Доступные опции:
 * <ul>G
 * <li> STYLE - inline-стили</li>
 * <li> VARIANTS - массив с вариантами занчений или функция для их получения</li>
 * <li> DEFAULT_VARIANT - ID варианта по-умолчанию</li>
 * </ul>
 */
class ComboBoxWidget extends HelperWidget
{
	/**
	 * Генерирует HTML для редактирования поля
	 * @see AdminEditHelper::showField();
	 * @param bool $forFilter
	 * @return mixed
	 */
	protected function genEditHTML($forFilter = false)
	{
		$style = $this->getSettings('STYLE');
		$multiple = $this->getSettings('MULTIPLE');

		$multipleSelected = [];
		if ($multiple && !empty($this->data['ID']))
		{
			$entityName = $this->entityName;
			$rsEntityData = $entityName::getList(['select' => [$this->getCode()], 'filter' => ['=ID' => $this->data['ID']]]);
			while ($arData = $rsEntityData->fetch())
			{
				// TODO Сделать правильное получение связанных данных
				if (empty($prefix))
				{
					// Определение приставки для полей связанной сущности
					$prefix = str_replace('ID', '', reset(array_flip($arData)));
				}
				$multipleSelected[] = $arData[$prefix . 'VALUE'];
			}
		}

		$name = $forFilter ? $this->getFilterInputName() : $this->getEditInputName();
		$result = "<select name='" . $name . ($multiple ? '[]' : null) .
			"' " . ($multiple ? 'multiple="multiple"' : null) . " style='" . $style . "'>";
		$variants = $this->getVariants();
		$default = $this->getValue();
		if (is_null($default))
		{
			$default = $this->getSettings('DEFAULT_VARIANT');
		}

		foreach ($variants as $id => $data)
		{
			$name = strlen($data["TITLE"]) > 0 ? $data["TITLE"] : "";
			$selected = false;
			if ($multiple)
			{
				if (in_array($id, $multipleSelected))
				{
					$selected = true;
				}
			}
			else
			{
				if ($id == $default)
				{
					$selected = true;
				}
			}
			$result .= "<option value='" . $id . "' " . ($selected ? "selected" : "") . ">" . $name . "</option>";
		}

		$result .= "</select>";

		return $result;
	}

	public function processEditAction()
	{
		parent::processEditAction();

		$sphere = $this->data[$this->getCode()];
		unset($this->data[$this->getCode()]);

		foreach ($sphere as $sphereKey)
		{
			$this->data[$this->getCode()][] = ['VALUE' => $sphereKey];
		}
	}

	protected function genMultipleEditHTML()
	{
		return $this->genEditHTML();
	}

	protected function getValueReadonly()
	{
		$variants = $this->getVariants();
		$value = $variants[$this->getValue()]['TITLE'];

		return $value;
	}

	/**
	 * Возвращает массив в формате
	 * <code>
	 * array(
	 *      '123' => array('ID' => 123, 'TITLE' => 'ololo'),
	 *      '456' => array('ID' => 456, 'TITLE' => 'blablabla'),
	 *      '789' => array('ID' => 789, 'TITLE' => 'pish-pish'),
	 * )
	 * </code>
	 * Результат будет выводиться в комбобоксе
	 * @return array
	 */
	protected function getVariants()
	{
		$variants = $this->getSettings('VARIANTS');
		if (is_array($variants) AND !empty($variants))
		{
			return $this->formatVariants($variants);
		}
		else if (is_callable($variants))
		{
			$var = $variants();
			if (is_array($var))
			{
				return $this->formatVariants($var);
			}
		}

		return array();
	}

	/**
	 * Приводит варианты к нужному формату, если они заданы в виде одномерного массива.
	 * @param $variants
	 * @return array
	 */
	protected function formatVariants($variants)
	{
		$formatted = [];
		foreach ($variants as $id => $data)
		{
			if (!is_array($data))
			{
				$formatted[$id] = [
					'ID' => $id,
					'TITLE' => $data
				];
			}
		}

		return $formatted;
	}

	/**
	 * Генерирует HTML для поля в списке
	 * @see AdminListHelper::addRowCell();
	 * @param CAdminListRow $row
	 * @param array $data - данные текущей строки
	 * @return mixed
	 */
	public function genListHTML(&$row, $data)
	{
		if ($this->getSettings('MULTIPLE'))
		{
		}
		else
		{
			if ($this->settings['EDIT_IN_LIST'] AND !$this->settings['READONLY'])
			{
				$row->AddInputField($this->getCode(), ['style' => 'width:90%']);
			}
			else
			{
				$row->AddViewField($this->getCode(), $this->getValueReadonly());
			}
		}
	}

	/**
	 * Генерирует HTML для поля фильтрации
	 * @see AdminListHelper::createFilterForm();
	 * @return mixed
	 */
	public function genFilterHTML()
	{
		if ($this->getSettings('MULTIPLE'))
		{
		}
		else
		{
			print '<tr>';
			print '<td>' . $this->getSettings('TITLE') . '</td>';
			print '<td>' . $this->genEditHTML(true) . '</td>';
			print '</tr>';
		}
	}

	/**
	 * @param OrmModel $model Модель
	 * @param string $field Название поля
	 * @param array $variants Варианты значения (ключ => значение)
	 * @return null|array Формат ['key' => ключ, 'title' => название]
	 */
	public static function getValueDetails(OrmModel $model, $field, array $variants)
	{
		$value = $model->{$field};
		$title = (empty($variants[$value]) ? 'error: название не найдено' : $variants[$value]);

		return ['key' => $value, 'title' => $title];
	}
}