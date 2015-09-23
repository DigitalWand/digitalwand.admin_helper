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
 * </ul>
 */
class FileWidget extends HelperWidget
{
	/**
	 * Генерирует HTML для редактирования поля
	 * @return mixed
	 */
	protected function genEditHTML()
	{
		return \CFileInput::Show($this->getEditInputName('_FILE'), ($this->getValue() > 0 ? $this->getValue() : 0),
			array(
				"IMAGE" => "N",
				"PATH" => "Y",
				"FILE_SIZE" => "Y",
				"ALLOW_UPLOAD" => "I",
			), array(
				'upload' => true,
				'medialib' => false,
				'file_dialog' => false,
				'cloud' => false,
				'del' => true,
				'description' => false,
			)
		);
	}

	protected function genMultipleEditHTML()
	{
		$style = $this->getSettings('STYLE');
		$size = $this->getSettings('SIZE');
		$descriptionField = $this->getSettings('DESCRIPTION_FIELD');
		$uniqueId = $this->getEditInputHtmlId();

		$rsEntityData = null;
		if (!empty($this->data['ID']))
		{
			$entityName = $this->entityName;
			$rsEntityData = $entityName::getList([
				'select' => ['REFERENCE_' => $this->getCode() . '.*'],
				'filter' => ['=ID' => $this->data['ID']]
			]);
		}

		ob_start();
		// TODO Рефакторинг
		?>

		<div id="<?= $uniqueId ?>-field-container" class="<?= $uniqueId ?>">
		</div>

		<script>
			var fileInputTemplate = '<span class="adm-input-file"><span>Выбрать файл</span>' +
				'<input type="file" name="<?= $this->getCode() ?>[#field_id#]" style="<?= $style ?>" size="<?= $size ?>"' +
				' class="adm-designed-file" onchange="BXHotKeys.OnFileInputChange(this);"></span>';

			<? if ($descriptionField) { ?>
			fileInputTemplate = fileInputTemplate + '<input type="text" name="<?= $this->getCode() ?>[#field_id#][DESCRIPTION]"' +
				' style="margin-left: 5px;" placeholder="Описание">';
			<? } ?>

			var multiple = new MultipleWidgetHelper(
				'#<?= $uniqueId ?>-field-container',
				fileInputTemplate);

			<?
			if ($rsEntityData)
			{
				while($referenceData = $rsEntityData->fetch())
				{
					if (empty($referenceData['REFERENCE_ID']))
					{
						continue;
					}
					$fileInfo = \CFile::GetByID($referenceData['REFERENCE_VALUE'])->fetch();
					if ($fileInfo)
					{
						$fileInfoHtml = $fileInfo['ORIGINAL_NAME'];
						if ($descriptionField && !empty($fileInfo['DESCRIPTION'])) {
							$fileInfoHtml .= ' - '.mb_substr($fileInfo['DESCRIPTION'], 0, 30, 'UTF-8').'...';
						}
					}
					else
					{
						$fileInfoHtml = 'Файл не найден';
					}

					?>
				multiple.addFieldHtml('<span style="display: inline-block; min-width: 139px;"><?= $fileInfoHtml ?></span>' +
				'<input type="hidden" name="<?= $this->getCode() ?>[#field_id#][ID]" value="#field_id#">',
				{field_id: <?= $referenceData['REFERENCE_ID'] ?>});
			<?
	   }
	}
	?>

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
						|| !empty($_FILES[$this->getCode()]['error'][$key])) {
						continue;
					}

					$description = null;

					if (isset($this->data[$this->getCode()][$key]['DESCRIPTION']))
					{
						$description = $this->data[$this->getCode()][$key]['DESCRIPTION'];
						unset($this->data[$this->getCode()][$key]['DESCRIPTION']);
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
			if (isset($_REQUEST['FIELDS_del'][$this->code . '_FILE']) AND $_REQUEST['FIELDS_del'][$this->code . '_FILE'] == 'Y')
			{
				\CFile::Delete(intval($this->data[$this->code]));
				$this->data[$this->code] = 0;
			}
			else if (isset($_REQUEST['FIELDS']['IMAGE_ID_FILE']))
			{
				$name = $_FILES['FIELDS']['name'][$this->code . '_FILE'];
				$path = $_REQUEST['FIELDS']['IMAGE_ID_FILE'];
				$this->saveFile($name, $path);
			}
			else
			{
				$name = $_FILES['FIELDS']['name'][$this->code . '_FILE'];
				$path = $_FILES['FIELDS']['tmp_name'][$this->code . '_FILE'];
				$type = $_FILES['FIELDS']['type'][$this->code . '_FILE'];
				$this->saveFile($name, $path, $type);
			}
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