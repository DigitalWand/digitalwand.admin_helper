<?php

namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Loader;

class VisualEditorWidget extends TextAreaWidget
{
    static protected $defaults = array(
        'WIDTH' => '100%',
        'HEIGHT' => 450,
        'EDITORS' => array(
            'EDITOR'
        ),
        'DEFAULT_EDITOR' => 'EDITOR',
    );

    protected function genEditHTML()
    {
        if (Loader::IncludeModule("fileman")) {
            \CJSCore::Init(array('jquery'));
            ob_start();

            $codeType = $this->getCode() . '_TEXT_TYPE';

            \CFileMan::AddHTMLEditorFrame(
                $this->getCode(),
                $this->getValue(),
                $codeType,
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

            if (count($editors) > 1) {
                foreach ($editors as &$editor) {
                    $editor = strtolower($editor);
                    if (isset($defaultEditors[$editor])) {
                        unset($defaultEditors[$editor]);
                    }
                }
            }


            $script = '<script type="text/javascript">';
            $script .= '$(document).ready(function() {';
            foreach ($defaultEditors as $editor) {
                $script .= '$("#bxed_' . $this->getCode() . '_' . $editor . '").parent().hide();';
            }

            $script .= '$("#bxed_' . $this->getCode() . '_' . $defaultEditor . '").click();';
            $script .= 'setTimeout(function() {$("#bxed_' . $this->getCode() . '_' . $defaultEditor . '").click(); }, 500);';

            $script .= "});";
            $script .= '</script>';

            echo $script;

            $html = ob_get_clean();
            return $html;

        } else {
            return parent::genEditHTML();
        }
    }

    public function genBasicEditField($isPKField)
    {
        if (!Loader::IncludeModule("fileman")) {
            parent::genBasicEditField($isPKField);

        } else {
            $title = $this->getSettings('TITLE');
            if ($this->getSettings('REQUIRED') === true) {
                $title = '<b>' . $title . '</b>';
            }

            print '<tr class="heading"><td colspan="2">' . $title . '</td></tr>';
            print '<tr><td colspan="2">';
            $readOnly = $this->getSettings('READONLY');
            if (!$readOnly) {
                print $this->genEditHTML();
            } else {
                print $this->getValueReadonly();
            }

            print '</td></tr>';
        }
    }

    public function processEditAction()
    {
        $currentView = $this->getCurrentViewType();
        switch ($currentView) {
            case HelperWidget::EDIT_HELPER:

                $codeType = $this->getCode() . '_TEXT_TYPE';

                $this->setValue($_REQUEST[$this->getCode()]);
                $this->data[$codeType] = $_REQUEST[$codeType];

                break;

            case HelperWidget::LIST_HELPER:
            default:
                parent::processEditAction();
                break;

        }
    }
}