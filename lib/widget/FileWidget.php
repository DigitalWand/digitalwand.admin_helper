<?php
namespace DigitalWand\AdminHelper\Widget;

use DigitalWand\AdminHelper\Helper\AdminBaseHelper;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Для множественного поля в таблице должен быть столбец FILE_ID
 * Настройки класса:
 * <ul>
 * <li><b>DESCRIPTION_FIELD</b> - bool нужно ли поле описания</li>
 * <li><b>MULTIPLE</b> - bool является ли поле множественным</li>
 * <li><b>SHOW_IMAGE</b> - может принимать значение - Y/N</li>
 * </ul>
 */
class FileWidget extends HelperWidget
{

	static protected $defaults = array(
		'IMAGE' => false,
		'DESCRIPTION_FIELD' => false
	);

	/**
	 * Генерирует HTML для редактирования поля
	 * @return mixed
	 */
	protected function genEditHTML()
	{
		if (class_exists('\Bitrix\Main\UI\FileInput', true) && $this->getSettings('IMAGE') === true)
		{
			$str = \Bitrix\Main\UI\FileInput::createInstance(array(
				"name" => $this->getEditInputName('_FILE'),
				"description" => $this->getSettings('DESCRIPTION_FIELD'),
				"upload" => true,
				"allowUpload" => "I",
				"medialib" => true,
				"fileDialog" => true,
				"cloud" => true,
				"delete" => true,
				"maxCount" => 1
			))->show($this->getValue());
		}
		else
		{
			$str = \CFileInput::Show($this->getEditInputName('_FILE'), ($this->getValue() > 0 ? $this->getValue() : 0),
				array(
					"IMAGE" => $this->getSettings('IMAGE') === true ? 'Y': 'N',
					"PATH" => "Y",
					"FILE_SIZE" => "Y",
					"ALLOW_UPLOAD" => "I",
				), array(
					'upload' => true,
					'medialib' => false,
					'file_dialog' => true,
					'cloud' => false,
					'del' => true,
					'description' => $this->getSettings('DESCRIPTION_FIELD'),
				)
			);
		}

		if($this->getValue())
		{
			$str .= '<input type="hidden" name="' .$this->getEditInputName(). '" value=' . $this->getValue() .'>';
		}

		return $str;
	}

	protected function genMultipleEditHTML()
	{
		$rsEntityData = null;
		if (!empty($this->data['ID']))
		{
			$entityName = $this->entityName;
			$rsEntityData = $entityName::getList([
				'select' => ['REFERENCE_' => $this->getCode() . '.*'],
				'filter' => ['=ID' => $this->data['ID']]
			]);
		}

		$inputName = array();

		$name = $this->code;

		if ($rsEntityData)
		{
			while($referenceData = $rsEntityData->fetch())
			{
				$inputName[$name."[".$referenceData['REFERENCE_ID']."]"] = $referenceData['REFERENCE_VALUE'];
				$inputHidden[$referenceData['REFERENCE_ID']] = $referenceData['REFERENCE_VALUE'];
			}
		}

		if (class_exists('\Bitrix\Main\UI\FileInput', true) && $this->getSettings('IMAGE') === true)
		{
			$str = \Bitrix\Main\UI\FileInput::createInstance(array(
				"name" => $name . "[n#IND#]",
				"description" => $this->getSettings('DESCRIPTION_FIELD'),
				"upload" => true,
				"allowUpload" => "I",
				"medialib" => true,
				"fileDialog" => true,
				"cloud" => true,
				"delete" => true,
			))->show($inputName);
		}
		else
		{
			$str = \CFileInput::ShowMultiple($inputName, $name . "[n#IND#]",
				array(
					"IMAGE" => $this->getSettings('IMAGE') === true ? 'Y': 'N',
					"PATH" => "Y",
					"FILE_SIZE" => "Y",
					"DIMENSIONS" => "Y",
					"IMAGE_POPUP" => "Y",
				), false, array(
					'upload' => true,
					'medialib' => true,
					'file_dialog' => true,
					'cloud' => true,
					'del' => true,
					'description' => $this->getSettings('DESCRIPTION_FIELD'),
				)
			);
		}

		foreach($inputHidden as $key => $input)
		{
			if(!empty($input))
			{
				$str .= '<input type="hidden" name="' .$name .'[' .$key. '][ID]" value=' . $key .'>
					<input type="hidden" name="' .$name .'[' .$key. '][VALUE]" value=' . $input .'>';
			}
		}

		return $str;
	}

	/**
	 * Генерирует HTML для поля в списке
	 * @see AdminListHelper::addRowCell();
	 * @param \CAdminListRow $row
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
			$file = \CFile::GetPath($data[$this->code]);
			$res = \CFile::GetByID($data[$this->code]);
			$fileInfo = $res->Fetch();

			if (!$file)
			{
				$html = "";
			}
			else
			{
				$html = '<a href="' . $file . '" >' . $fileInfo['FILE_NAME'] . ' (' . $fileInfo['FILE_DESCRIPTION'] . ')' . '</a>';
			}
			$row->AddViewField($this->code, $html);
		}
	}

	/**
	 * Генерирует HTML для поля фильтрации
	 * @see AdminListHelper::createFilterForm();
	 * @return mixed
	 */
	public function genFilterHTML()
	{
		// TODO: Implement genFilterHTML() method.
	}

	public function processEditAction()
	{
		parent::processEditAction();
		if ($this->getSettings('MULTIPLE'))
		{
			if (!empty($_FILES[$this->getCode()]))
			{
				foreach ($_FILES[$this->getCode()]['name'] as $key => $fileName)
				{
					if (empty($fileName)
						|| empty($_FILES[$this->getCode()]['tmp_name'][$key])
						|| !empty($_FILES[$this->getCode()]['error'][$key]))
					{
						if (isset($_REQUEST[$this->getCode().'_del'][$key]))
						{
							unset($this->data[$this->getCode()][$key]);
						}
						elseif($this->data[$this->getCode()][$key]['VALUE'])
						{
							\CFile::UpdateDesc($this->data[$this->getCode()][$key]['VALUE'],
								$_REQUEST[$this->getCode().'_descr'][$key]);
						}
						continue;
					}

					$description = null;

					if (isset($_REQUEST[$this->getCode().'_descr'][$key]))
					{
						$description = $_REQUEST[$this->getCode().'_descr'][$key];
					}

					if (empty($this->data[$this->getCode()][$key]))
					{
						unset($this->data[$this->getCode()][$key]);
					}

					$fileId = $this->saveFile($fileName, $_FILES[$this->getCode()]['tmp_name'][$key], false, $description);

					if ($fileId)
					{
						$this->data[$this->getCode()][] = ['VALUE' => $fileId];
					}
					else
					{
						ShowError('Не удалось добавить файл ' . $_FILES[$this->getCode()]['name'][$key]);
					}
				}
			}
		}
		else
		{
			if (empty($_FILES['FIELDS']['name'][$this->code . '_FILE'])
				|| empty($_FILES['FIELDS']['tmp_name'][$this->code . '_FILE'])
				|| !empty($_FILES['FIELDS']['error'][$this->code . '_FILE']))
			{
				if (isset($_REQUEST['FIELDS_del'][$this->code . '_FILE']) AND $_REQUEST['FIELDS_del'][$this->code . '_FILE'] == 'Y')
				{
					\CFile::Delete(intval($this->data[$this->code]));
					$this->data[$this->code] = 0;
				}
				elseif($this->data[$this->code] && isset($_REQUEST['FIELDS_descr'][$this->code . '_FILE']))
				{
					\CFile::UpdateDesc($this->data[$this->code],
						$_REQUEST['FIELDS_descr'][$this->code . '_FILE']);
				}
				return false;
			}

			$description = null;

			if (isset($_REQUEST['FIELDS_descr'][$this->code . '_FILE']))
			{
				$description = $_REQUEST['FIELDS_descr'][$this->code . '_FILE'];
			}

			$name = $_FILES['FIELDS']['name'][$this->code . '_FILE'];
			$path = $_FILES['FIELDS']['tmp_name'][$this->code . '_FILE'];
			$type = $_FILES['FIELDS']['type'][$this->code . '_FILE'];
			$this->saveFile($name, $path, $type, $description);
		}
	}

	protected function saveFile($name, $path, $type = false, $description = null)
	{
		if (!$path)
		{
			return false;
		}

		$fileInfo = \CFile::MakeFileArray(
			$path,
			$type
		);

		if (!$fileInfo) return false;

		if (!empty($description))
		{
			$fileInfo['description'] = $description;
		}

		if (stripos($fileInfo['type'], "image") === false)
		{
			$this->addError('FILE_FIELD_TYPE_ERROR');

			return false;
		}

		$fileInfo["name"] = $name;

		/** @var AdminBaseHelper $model */
		$helper = $this->helper;
		$fileInfo['MODULE_ID'] = $helper::$module;

		$fileId = \CFile::SaveFile($fileInfo, $helper::$module);

		if (!$this->getSettings('MULTIPLE'))
		{
			$code = $this->code;
			if (isset($this->data[$code]))
			{
				\CFile::Delete($this->data[$code]);
			}

			$this->data[$code] = $fileId;
		}

		return $fileId;
	}

}