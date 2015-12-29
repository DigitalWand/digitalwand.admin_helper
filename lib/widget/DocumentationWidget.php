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
        if (!empty($this->buttonText) && !empty($this->popupText)):
            \CUtil::InitJSCore(array('window'));
            $aContext = array(
                array(
                    "TEXT" => $this->buttonText,
                    "ONCLICK" => 'DocDialog.Show();',
                    "ICON" => "btn_default",
                ),
            );
            $oMenu = new \CAdminContextMenu($aContext); ?>
            <script type="text/javascript">
                DocDialog = new BX.CDialog({
                    title: "<?=$this->buttonText?>",
                    content: '<?=$this->popupText?>',
                    icon: 'head-block',
                    resizable: true,
                    draggable: true,
                    buttons: [BX.CDialog.btnClose]
                });
            </script>
            <div style="float:right;padding-top:2px;"><? $oMenu->Show(); ?></div>
            <div style="clear:both"></div>
            <style type="text/css">#adm-title { float: left }</style>
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