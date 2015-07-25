<?php
namespace AdminHelper\Widget;

use AdminHelper\AdminBaseHelper;
use Bitrix\Highloadblock\DataManager;

class ImageWidget extends FileWidget
{
    static protected $defaults = array(
        'LIST_WIDTH' => 100,
        'LIST_HEIGHT' => 100,
        'LIST_FILTERS' => false,
        'LIST_QUALITY' => 80
    );
    /**
     * Генерирует HTML для редактирования поля
     * @see AdminEditHelper::showField();
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
     * @param CAdminListRow $row
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
            $html = '<img src="'.$image['src'].'" width="'.$this->getSettings('LIST_WIDTH').'" height="'.$this->getSettings('LIST_HEIGHT').'">';
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

}