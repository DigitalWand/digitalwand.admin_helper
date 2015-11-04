<?php
namespace DigitalWand\AdminHelper\Widget;

use \DigitalWand\AdminHelper\Helper\AdminBaseHelper;
use Bitrix\Highloadblock\DataManager;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ImageWidget extends FileWidget
{
    static protected $defaults = array(
        'LIST_WIDTH' => 100,
        'LIST_HEIGHT' => 100,
        'LIST_FILTERS' => false,
        'LIST_QUALITY' => 80,
        'FILTER' => false
    );
    /**
     * Генерирует HTML для редактирования поля
     * @return mixed
     */
    protected function genEditHTML()
    {
        return \CFileInput::Show($this->getEditInputName('_FILE'), ($this->getValue() > 0 ? $this->getValue() : 0),
			array(
				"IMAGE" => "Y",
				"PATH" => "Y",
				"FILE_SIZE" => "Y",
				"DIMENSIONS" => "Y",
				"IMAGE_POPUP" => "Y",
				"MAX_SIZE" => array(
					"W" => 100,
					"H" => 100,
				),
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
        $image = $this->getImageByID($data[$this->code],'LIST');

        if (!$image)
        {
            $html = "";    
        }
        else
        {
            if ($_REQUEST['mode'] == 'excel') {
                $html = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $image['src'];
            } else {
                $html = '<img src="' . $image['src'] . '" width="' . $this->getSettings('LIST_WIDTH') . '" height="' . $this->getSettings('LIST_HEIGHT') . '">';
            }
        }
        $row->AddViewField($this->code,$html);

    }

    private function getImageByID($id, $resizeFor)
    {
        $size['width'] = intval($this->getSettings($resizeFor . '_WIDTH'));
        $size['height'] = intval($this->getSettings($resizeFor . '_HEIGHT'));

        $filters = $this->getSettings($resizeFor . '_FILTERS');
        $quality = $this->getSettings($resizeFor . '_QUALITY');

        return \CFile::ResizeImageGet($id, $size, BX_RESIZE_IMAGE_EXACT, true, $filters, false, $quality);

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