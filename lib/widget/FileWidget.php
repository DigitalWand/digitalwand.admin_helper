<?php
namespace DigitalWand\AdminHelper\Widget;

use DigitalWand\AdminHelper\Helper\AdminBaseHelper;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Для множественного поля в таблице должен быть столбец FILE_ID
 * Настройки класса:
 * <ul>
 * <li><b>IMAGES</b> - bool ограничивать ли загрузку только изображениями</li>
 * <li><b>DESCRIPTION</b> - bool нужно ли поле описания</li>
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
		$uniqueId = $this->getEditInputHtmlId();

		ob_start();
		?>

		<div id="<?= $uniqueId ?>-field-container" class="<?= $uniqueId ?>">
		</div>

		<script>
			var multiple = new MultipleWidgetHelper(
				'#<?= $uniqueId ?>-field-container',
				'<input type="file" name="<?= $this->getCode()?>[]" style="<?=$style?>" size="<?=$size?>">'
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
	 * @return mixed
	 */
	public function genListHTML(&$row, $data)
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
					$fileId = $this->saveFile($fileName, $_FILES[$this->getCode()]['tmp_name'][$key]);
					$this->data['IMAGES'][] = ['VALUE' => $fileId];
					// TODO Учитывание пустых VALUE в RelativeManager
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

	protected function saveFile($name, $path, $type = false)
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