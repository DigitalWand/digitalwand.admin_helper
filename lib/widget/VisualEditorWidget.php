<?php

namespace DigitalWand\AdminHelper\Widget;

/**
 * Визуальный редактор.
 *
 * В отличии от виджета TextAreaWidget, кроме поля указанного в интерфейсе раздела (AdminInterface::fields()),
 * обязательно поле {НАЗВАНИЕ ПОЛЯ}_TEXT_TYPE, в котором будет хранится тип контента (text/html).
 */
class VisualEditorWidget extends TextAreaWidget
{
    /**
     * @const string Текст. Тип содержимого редактора
     */
    const CONTENT_TYPE_TEXT = 'text';
    /**
     * @const string HTML-текст. Тип содержимого редактора
     */
    const CONTENT_TYPE_HTML = 'html';

    protected static $defaults = array(
        'WIDTH' => '100%',
        'HEIGHT' => 450,
        'EDITORS' => array(
            'EDITOR'
        ),
        'DEFAULT_EDITOR' => 'EDITOR',
        'LIGHT_EDITOR_MODE' => 'N',
        'EDITOR_TOOLBAR_CONFIG_SET' => 'FULL', // SIMPLE
        'EDITOR_TOOLBAR_CONFIG' => false,
    );

    /**
     * @inheritdoc
     */
    protected function getEditHtml()
    {
        if (\CModule::IncludeModule('fileman')) {
            ob_start();
            $codeType = $this->getContentTypeCode();
            /** @var string $className Имя класса без неймспейса */
            $className = $this->getEntityShortName();
            $entityClass = $this->entityName;
            $modelPk = $entityClass::getEntity()->getPrimary();
            $id = isset($this->data[$modelPk]) ? $this->data[$modelPk] : false;
            $bxCode = $this->code . '_' . $className;
            $bxCodeType = $codeType . '_' . $className;

            if ($this->forceMultiple) {
                if ($id) {
                    $bxCode .= '_' . $id;
                    $bxCodeType .= '_' . $id;
                } else {
                    $bxCode .= '_new_';
                    $bxCodeType .= '_new_';
                }
            }

            // TODO Избавиться от данного костыля
            if ($_REQUEST[$bxCode]) {
                $this->data[$this->code] = $_REQUEST[$bxCode];
            }

            $editorToolbarSets = array(
                'FULL' => array(
                    'Bold', 'Italic', 'Underline', 'Strike', 'RemoveFormat',
                    'CreateLink', 'DeleteLink', 'Image', 'Video',
                    'BackColor', 'ForeColor',
                    'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyFull',
                    'InsertOrderedList', 'InsertUnorderedList', 'Outdent', 'Indent',
                    'StyleList', 'HeaderList',
                    'FontList', 'FontSizeList'
                ),
                'SIMPLE' => array(
                    'Bold', 'Italic', 'Underline', 'Strike', 'RemoveFormat',
                    'CreateLink', 'DeleteLink',
                    'Video',
                    'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyFull',
                    'InsertOrderedList', 'InsertUnorderedList', 'Outdent', 'Indent',
                    'FontList', 'FontSizeList',
                )
            );

            if ($this->getSettings('LIGHT_EDITOR_MODE') == 'Y') {
                // Облегченная версия редактора
                global $APPLICATION;
                
                $editorToolbarConfig = $this->getSettings('EDITOR_TOOLBAR_CONFIG');
                
                if (!is_array($editorToolbarConfig)) {
                    $editorToolbarSet = $this->getSettings('EDITOR_TOOLBAR_CONFIG_SET');
                    if (isset($editorToolbarSets[$editorToolbarSet])) {
                        $editorToolbarConfig = $editorToolbarSets[$editorToolbarSet];
                    } else {
                        $editorToolbarConfig = $editorToolbarSets['FULL'];
                    }
                }
                
                $APPLICATION->IncludeComponent('bitrix:fileman.light_editor', '', array(
                        'CONTENT' => $this->data[$this->code],
                        'INPUT_NAME' => $bxCode,
                        'INPUT_ID' => $bxCode,
                        'WIDTH' => $this->getSettings('WIDTH'),
                        'HEIGHT' => $this->getSettings('HEIGHT'),
                        'RESIZABLE' => 'N',
                        'AUTO_RESIZE' => 'N',
                        'VIDEO_ALLOW_VIDEO' => 'Y',
                        'VIDEO_MAX_WIDTH' => $this->getSettings('WIDTH'),
                        'VIDEO_MAX_HEIGHT' => $this->getSettings('HEIGHT'),
                        'VIDEO_BUFFER' => '20',
                        'VIDEO_LOGO' => '',
                        'VIDEO_WMODE' => 'transparent',
                        'VIDEO_WINDOWLESS' => 'Y',
                        'VIDEO_SKIN' => '/bitrix/components/bitrix/player/mediaplayer/skins/bitrix.swf',
                        'USE_FILE_DIALOGS' => 'Y',
                        'ID' => 'LIGHT_EDITOR_' . $bxCode,
                        'JS_OBJ_NAME' => $bxCode,
                        'TOOLBAR_CONFIG' => $editorToolbarConfig
                    )
                );
            } else {
                // Полная версия редактора
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
                
                $defaultEditors = array(
                    static::CONTENT_TYPE_TEXT => static::CONTENT_TYPE_TEXT,
                    static::CONTENT_TYPE_HTML => static::CONTENT_TYPE_HTML,
                    'editor' => 'editor'
                );
                $editors = $this->getSettings('EDITORS');
                $defaultEditor = strtolower($this->getSettings('DEFAULT_EDITOR'));
                $contentType = $this->getContentType();
                $defaultEditor = isset($contentType) && $contentType == static::CONTENT_TYPE_TEXT ? static::CONTENT_TYPE_TEXT : $defaultEditor;
                $defaultEditor = isset($contentType) && $contentType == static::CONTENT_TYPE_HTML ? "editor" : $defaultEditor;

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
                    $script .= '$("#bxed_' . $bxCode . '_' . $editor . '").parent().hide();';
                }

                $script .= '$("#bxed_' . $bxCode . '_' . $defaultEditor . '").click();';
                $script .= 'setTimeout(function() {$("#bxed_' . $bxCode . '_' . $defaultEditor . '").click(); }, 500);';
                $script .= "});";
                $script .= '</script>';

                echo $script;
            }
            $html = ob_get_clean();

            return $html;
        } else {
            return parent::getEditHtml();
        }
    }

    /**
     * @inheritdoc
     */
    public function showBasicEditField($isPKField)
    {
        if (!\CModule::IncludeModule('fileman')) {
            parent::showBasicEditField($isPKField);
        } else {
            $title = $this->getSettings('TITLE');
            if ($this->getSettings('REQUIRED') === true) {
                $title = '<b>' . $title . '</b>';
            }
            print '<tr class="heading"><td colspan="2">' . $title . '</td></tr>';
            print '<tr><td colspan="2">';
            $readOnly = $this->getSettings('READONLY');
            if (!$readOnly) {
                print $this->getEditHtml();
            } else {
                print $this->getValueReadonly();
            }
            print '</td></tr>';
        }
    }

    /**
     * @inheritdoc
     */
    public function processEditAction()
    {
        $entityClass = $this->entityName;
        $modelPk = $entityClass::getEntity()->getPrimary();
        $className = $this->getEntityShortName();
        $currentView = $this->getCurrentViewType();

        switch ($currentView) {
            case HelperWidget::EDIT_HELPER:
                $id = isset($this->data[$modelPk]) ? $this->data[$modelPk] : false;
                $codeType = $this->getContentTypeCode();
                $bxCode = $this->getCode() . '_' . $className;
                $bxCodeType = $codeType . '_' . $className;
                
                if ($this->forceMultiple AND $id) {
                    $bxCode .= '_' . $id;
                    $bxCodeType .= '_' . $id;
                }
                
                if (!$_REQUEST[$bxCode] && $this->getSettings('REQUIRED') == true) {
                    $this->addError('DIGITALWAND_AH_REQUIRED_FIELD_ERROR');
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

    /**
     * @inheritdoc
     */
    protected function getValueReadonly()
    {
        return $this->getContentType() == static::CONTENT_TYPE_HTML ? $this->data[$this->code] : parent::getValueReadOnly();
    }

    /**
     * @inheritdoc
     */
    public function generateRow(&$row, $data)
    {
        $text = trim(strip_tags($data[$this->code]));

        if (strlen($text) > self::LIST_TEXT_SIZE && !$this->isExcelView()) {
            $pos = false;
            $pos = $pos === false ? stripos($text, " ", self::LIST_TEXT_SIZE) : $pos;
            $pos = $pos === false ? stripos($text, "\n", self::LIST_TEXT_SIZE) : $pos;
            $pos = $pos === false ? stripos($text, "</", self::LIST_TEXT_SIZE) : $pos;
            $pos = $pos === false ? 300 : $pos;
            $text = substr($text, 0, $pos) . " ...";
        }

        $text = static::prepareToOutput($text);
        $row->AddViewField($this->code, $text);
    }

    /**
     * Тип текста (text/html). По умолчанию html.
     * 
     * @return string
     */
    public function getContentType()
    {
        $contentType = $this->data[$this->getContentTypeCode()];

        return empty($contentType) ? static::CONTENT_TYPE_HTML : $contentType;
    }

    /**
     * Поле, в котором хранится тип текста.
     * 
     * @return string
     */
    public function getContentTypeCode()
    {
        return $this->code . '_TEXT_TYPE';
    }

    /**
     * Название класса без неймспейса.
     *
     * @return string
     */
    protected function getEntityShortName()
    {
        return end(explode('\\', $this->entityName));
    }
}