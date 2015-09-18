<?php

namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Class StringWidget
 * Виджет строки с текстом.
 *
 * Доступные опции:
 * <ul>
 * <li> <b>STYLE</b> - inline-стили для input </li>
 * <li> <b>SIZE</b> - значение атрибута size для input </li>
 * <li> <b>TRANSLIT</b> - true, если поле будет транслитерироваться в символьный код</li>
 * </ul>
 */
class StringWidget extends HelperWidget
{
	static protected $defaults = array(
		'FILTER' => '%', //Фильтрация по подстроке, а не по точному соответствию.
		'EDIT_IN_LIST' => true
	);

	/**
	 * Генерирует HTML для редактирования поля
	 * @return mixed
	 */
	protected function genEditHTML()
	{
		$style = $this->getSettings('STYLE');
		$size = $this->getSettings('SIZE');

		$link = '';
		if ($this->getSettings('TRANSLIT'))
		{

			//TODO: refactor this!
			$uniqId = get_class($this->entityName) . '_' . $this->getCode();
			$nameId = 'name_link_' . $uniqId;
			$linkedFunctionName = 'set_linked_' . get_class($this->entityName) . '_CODE';//FIXME: hardcode here!!!
			if (isset($this->entityName->{$this->entityName->pk()}))
			{
				$pkVal = $this->entityName->{$this->entityName->pk()};
			}
			else
			{
				$pkVal = '_new_';
			}
			$nameId .= $pkVal;
			$linkedFunctionName .= $pkVal;

			$link = '<image id="' . $nameId . '" title="' . Loc::getMessage("IBSEC_E_LINK_TIP") . '" class="linked" src="/bitrix/themes/.default/icons/iblock/link.gif" onclick="' . $linkedFunctionName . '()" />';
		}

		//FIXME: тут было htmlentities, на на этом проекте оно превращает кириллицу в квакозябры.
		return '<input type="text"
                       name="' . $this->getEditInputName() . '"
                       value="' . $this->getValue() . '"
                       size="' . $size . '"
                       style="' . $style . '"/>' . $link;
	}

	protected function genMultipleEditHTML()
	{
		$style = $this->getSettings('STYLE');
		$size = $this->getSettings('SIZE');
		$uniqueId = $this->getEditInputHtmlId();
		ob_start();
		?>

		<div id="<?= $uniqueId ?>-field-container" class="<?= $uniqueId ?>">
		</div>

		<script>
			var multiple = new MultipleWidgetHelper(
				'#<?= $uniqueId ?>-field-container',
				'<input type="text" name="<?= $this->getCode()?>[]" style="<?=$style?>" size="<?=$size?>">'
			);
			// TODO Добавление созданных полей
			multiple.addField();
		</script>
		<?
		return ob_get_clean();
	}

	/**
	 * Генерирует HTML для поля в списке
	 * @see AdminListHelper::addRowCell();
	 * @param \CAdminListRow $row
	 * @param array $data - данные текущей строки
	 */
	public function genListHTML(&$row, $data)
	{
		$value = $this->getValue();
		if ($this->getSettings('EDIT_IN_LIST') AND !$this->getSettings('READONLY'))
		{
			$row->AddInputField($this->getCode(), array('style' => 'width:90%'));
		}
		$row->AddViewField($this->getCode(), $value);
	}

	/**
	 * Генерирует HTML для поля фильтрации
	 * Если это BETWEEN, то выводит два поля для фильтрации
	 * @see AdminListHelper::createFilterForm();
	 * @return mixed
	 */
	public function genFilterHTML()
	{
		print '<tr>';
		print '<td>' . $this->getSettings('TITLE') . '</td>';

		if ($this->isFilterBetween())
		{
			list($from, $to) = $this->getFilterInputName();
			print '<td>
            <div class="adm-filter-box-sizing">
                <span style="display: inline-block; left: 11px; top: 5px; position: relative;">От:</span>
                <div class="adm-input-wrap" style="display: inline-block">
                    <input type="text" class="adm-input" name="' . $from . '" value="' . $$from . '">
                </div>
                <span style="display: inline-block; left: 11px; top: 5px; position: relative;">До:</span>
                <div class="adm-input-wrap" style="display: inline-block">
                    <input type="text" class="adm-input" name="' . $to . '" value="' . $$to . '">
                </div>
            </div>
            </td> ';
		}
		else
		{
			print '<td><input type="text" name="' . $this->getFilterInputName() . '" size="47" value="' . $this->getCurrentFilterValue() . '"></td>';
		}
		print '</tr>';
	}
}