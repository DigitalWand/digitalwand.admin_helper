<?php

namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
\CModule::IncludeModule("iblock");

/**
 * Виджет для выбора элемента инфоблока
 * Доступные опции:
 * <ul>
 * <li> <b>IBLOCK_ID</b> - (int) ID инфоблока
 * <li> <b>INPUT_SIZE</b> - (int) значение атрибута size для input </li>
 * <li> <b>WINDOW_WIDTH</b> - (int) значение width для всплывающего окна выбора элемента </li>
 * <li> <b>WINDOW_HEIGHT</b> - (int) значение height для всплывающего окна выбора элемента </li>
 * </ul>
 */
class IblockElementWidget extends NumberWidget
{
	static protected $defaults = array(
		'FILTER' => '=',
		'INPUT_SIZE' => 5,
		'WINDOW_WIDTH' => 600,
		'WINDOW_HEIGHT' => 500,
	);

	public function genEditHtml()
	{
		$iblockId = (int)$this->getSettings('IBLOCK_ID');
		$iblockCode = $this->getSettings('IBLOCK_CODE');
		$inputSize = (int)$this->getSettings('INPUT_SIZE');
		$windowWidth = (int)$this->getSettings('WINDOW_WIDTH');
		$windowHeight = (int)$this->getSettings('WINDOW_HEIGHT');

		if (empty($iblockId) && !empty($iblockCode) && \CModule::IncludeModule('iblock'))
		{
			$iblock = \CIBlock::GetList([], ['CODE' => $iblockCode])->fetch();
			if (!empty($iblock))
			{
				$iblockId = $iblock['ID'];
			}
		}

		$name = 'FIELDS';
		$key = $this->getCode();

		$elementId = $this->getValue();

		$iblock['NAME'] = Loc::getMessage('IBLOCK_ELEMENT_NOT_FOUND');
		if ($elementId)
		{
			$dbRes = \CIBlockElement::GetByID($elementId);
			$iblock = $dbRes->GetNext();
		}

		return '<input name="' . $this->getEditInputName() . '"
                     id="' . $name . '[' . $key . ']"
                     value="' . $elementId . '"
                     size="' . $inputSize . '"
                     type="text">' .
		'<input type="button"
                    value="..."
                    onClick="jsUtils.OpenWindow(\'/bitrix/admin/iblock_element_search.php?lang=' . LANGUAGE_ID .
		'&amp;IBLOCK_ID=' . $iblockId . '&amp;n=' . $name . '&amp;k=' . $key . '\', ' . $windowWidth . ', ' . $windowHeight . ');">' .
		'&nbsp;<span id="sp_' . md5($name) . '_' . $key . '" >' . $iblock['NAME'] . '</span>';
	}

	public function getValueReadonly()
	{
		$elementId = $this->getValue();

		if ($elementId)
		{
			$dbRes = \CIBlockElement::GetByID($elementId);
			$arRes = $dbRes->GetNext();

			return '[' . $elementId . '] ' . $arRes['NAME'];
		}
	}

	public function genListHTML(&$row, $data)
	{
		$elementId = $this->getValue();

		if ($elementId)
		{
			$dbRes = \CIBlockElement::GetByID($elementId);
			$arRes = $dbRes->GetNext();

			$strElement = '[' . $elementId . '] ' . $arRes['NAME'];
		}
		else
		{
			$strElement = '';
		}

		$row->AddViewField($this->getCode(), $strElement);
	}
}