<?php

namespace DigitalWand\AdminHelper\Widget;

class VisualEditorWidget extends TextAreaWidget
{
	static protected $defaults = [
		'WIDTH' => '100%',
		'HEIGHT' => 450,
		'EDITORS' => array(
			'EDITOR'
		),
		'DEFAULT_EDITOR' => 'EDITOR',
	];

	protected function genEditHTML()
	{
		if (\CModule::IncludeModule("fileman"))
		{
			ob_start();

			$codeType = $this->code . '_TEXT_TYPE';
			/** @var string $className Имя класса без неймспейса */
			$className = $this->getEntityShortName();

			$entityClass = $this->entityName;
			$modelPk = $entityClass::getEntity()->getPrimary();
			$id = isset($this->data[$modelPk]) ? $this->data[$modelPk] : false;

			$bxCode = $this->code . '_' . $className;
			$bxCodeType = $codeType . '_' . $className;

			if ($this->forceMultiple)
			{
				if ($id)
				{
					$bxCode .= '_' . $id;
					$bxCodeType .= '_' . $id;
				}
				else
				{
					$bxCode .= '_new_';
					$bxCodeType .= '_new_';
				}
			}

			\CFileMan::AddHTMLEditorFrame(
				$bxCode,
				$this->data[$this->code],
				$bxCodeType,
				$this->data[$codeType],
				array(
					'width' => $this->getSettings('WIDTH'),
					'height' => $this->getSettings('HEIGHT'),
				)
			);

			$defaultEditors = array("text" => "text", "html" => "html", "editor" => "editor");
			$editors = $this->getSettings('EDITORS');
			$defaultEditor = strtolower($this->getSettings('DEFAULT_EDITOR'));

			$contentType = $this->data[$codeType];
			$defaultEditor = isset($contentType) && $contentType == "text" ? "text" : $defaultEditor;
			$defaultEditor = isset($contentType) && $contentType == "html" ? "editor" : $defaultEditor;


			if (count($editors) > 1)
			{
				foreach ($editors as &$editor)
				{
					$editor = strtolower($editor);
					if (isset($defaultEditors[$editor]))
					{
						unset($defaultEditors[$editor]);
					}
				}
			}

			$script = '<script type="text/javascript">';
			$script .= '$(document).ready(function() {';
			foreach ($defaultEditors as $editor)
			{
				$script .= '$("#bxed_' . $bxCode . '_' . $editor . '").parent().hide();';
			}

			$script .= '$("#bxed_' . $bxCode . '_' . $defaultEditor . '").click();';
			$script .= 'setTimeout(function() {$("#bxed_' . $bxCode . '_' . $defaultEditor . '").click(); }, 500);';

			$script .= "});";
			$script .= '</script>';

			echo $script;

			$html = ob_get_clean();

			return $html;
		}
		else
		{
			return parent::genEditHTML();
		}
	}

	public function genBasicEditField($isPKField)
	{
		if (!\CModule::IncludeModule("fileman"))
		{
			parent::genBasicEditField($isPKField);
		}
		else
		{
			$title = $this->getSettings('TITLE');
			if ($this->getSettings('REQUIRED') === true)
			{
				$title = '<b>' . $title . '</b>';
			}

			print '<tr class="heading"><td colspan="2">' . $title . '</td></tr>';
			print '<tr><td colspan="2">';
			$readOnly = $this->getSettings('READONLY');
			if (!$readOnly)
			{
				print $this->genEditHTML();
			}
			else
			{
				print $this->getValueReadonly();
			}

			print '</td></tr>';
		}
	}

	public function processEditAction()
	{
		$entityClass = $this->entityName;
		$modelPk = $entityClass::getEntity()->getPrimary();
		$className = $this->getEntityShortName();
		$currentView = $this->getCurrentViewType();
		switch ($currentView)
		{
			case HelperWidget::EDIT_HELPER:

				$id = isset($this->data[$modelPk]) ? $this->data[$modelPk] : false;
				$codeType = $this->getCode() . '_TEXT_TYPE';
				$bxCode = $this->getCode() . '_' . $className;
				$bxCodeType = $codeType . '_' . $className;

				if ($this->forceMultiple AND $id)
				{
					$bxCode .= '_' . $id;
					$bxCodeType .= '_' . $id;
				}

				$this->data[$this->code] = $_REQUEST[$bxCode];
				$this->data[$codeType] = $_REQUEST[$bxCodeType];
				break;

			case HelperWidget::LIST_HELPER:
			default:
				parent::processEditAction();
				break;
		}
	}

	protected function getValueReadonly()
	{
		return $this->data[$this->code];
	}

	/**
	 * Название класса без неймспейса
	 * @return string
	 */
	protected function getEntityShortName()
	{
		return end(explode('\\', $this->entityName));
	}
}