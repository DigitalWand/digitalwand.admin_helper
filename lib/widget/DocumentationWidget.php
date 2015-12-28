<?php

namespace DigitalWand\AdminHelper\Widget;

class DocumentationWidget
{
    /**
     * @var string
     */
    protected $buttonText;

    /**
     * @var string
     */
    protected $popupText;

    /**
     * @param $params
     * @return $this
     */
    public function init($params)
    {
        $this->buttonText = $params['TEXT'];
        $this->popupText = $params['POPUP_TEXT'];
        return $this;
    }

    public function show()
    {
        if(!empty($this->buttonText) && !empty($this->popupText)):?>
            <div style="float:right">
                <?=$this->buttonText?>
            </div>
            <div style="clear:both"></div>
            <style type="text/css">#adm-title{float:left}</style>
        <?endif;
    }

    /**
     * @return static
     */
    public static function widget()
    {
        return new static();
    }
}