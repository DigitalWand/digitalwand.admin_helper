<?php
namespace DigitalWand\AdminHelper\Widget;

use DigitalWand\AdminHelper\Helper\AdminBaseHelper;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

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
            $html = '<a href="'.$file.'" >'.$fileInfo['FILE_NAME'].' ('.$fileInfo['FILE_DESCRIPTION'].')'.'</a>';
        }
        $row->AddViewField($this->code,$html);

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
        if (isset($_REQUEST['FIELDS_del'][$this->code . '_FILE']) AND $_REQUEST['FIELDS_del'][$this->code . '_FILE'] == 'Y') {
            \CFile::Delete(intval($this->data[$this->code]));
            $this->data[$this->code] = 0;
        }
        else if (isset($_REQUEST['FIELDS']['IMAGE_ID_FILE']))
        {
            $name = $_FILES['FIELDS']['name'][$this->code.'_FILE'];
            $path = $_REQUEST['FIELDS']['IMAGE_ID_FILE'];
            $this->saveFile($name, $path);
        }
        else
        {
            $name = $_FILES['FIELDS']['name'][$this->code.'_FILE'];
            $path = $_FILES['FIELDS']['tmp_name'][$this->code.'_FILE'];
            $type = $_FILES['FIELDS']['type'][$this->code.'_FILE'];
            $this->saveFile($name, $path, $type);
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


        if(!$fileInfo) return false;
        
        if (stripos($fileInfo['type'], "image") === false)
        {
            $this->addError('FILE_FIELD_TYPE_ERROR');
            return false;
        }

        $fileInfo["name"] = $name;
        
        /** @var AdminBaseHelper $model */
        $helper = $this->helper;
        $fileId = \CFile::SaveFile($fileInfo, $helper::$module);

        $code = $this->code;
        if(isset($this->data[$code])) {
            \CFile::Delete($this->data[$code]);
        }

        if($this->getSettings('MULTIPLE')){

        }

        $this->data[$code] = $fileId;
        return true;
    }

}