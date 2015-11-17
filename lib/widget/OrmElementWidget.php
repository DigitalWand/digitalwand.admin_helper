<?php

namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Виджет выбора записей из ORM.
 *
 * Доступные опции:
 * <ul>
 * <li> <b>MODEL</b> - (string) название модели с неймспейсом, к элементам которой осуществляется привязка </li>
 * <li> <b>TITLE_FIELD_NAME</b> - (string) название поля, из которого выводить имя элемента </li>
 * <li> <b>MODULE_NAME</b> - (string) название модуля, которому принадлежит модель </li>
 * <li> <b>LIST_VIEW_NAME</b> - (string) название представления страницы списка элементов, к которым осуществлятеся привязка</li>
 * <li> <b>INPUT_SIZE</b> - (int) значение атрибута size для input </li>
 * <li> <b>WINDOW_WIDTH</b> - (int) значение width для всплывающего окна выбора элемента </li>
 * <li> <b>WINDOW_HEIGHT</b> - (int) значение height для всплывающего окна выбора элемента </li>
 * <li> <b>TEMPLATE</b> - (string) шаблон отображения виджета, может принимать значения select|radio,  по умолчанию - select </li>
 *
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 * @author Dmitry Savchenkov <bitrixdev@gmail.com>
 */
class OrmElementWidget extends NumberWidget
{
    static protected $defaults = array(
        'FILTER' => '=',
        'INPUT_SIZE' => 5,
        'WINDOW_WIDTH' => 600,
        'WINDOW_HEIGHT' => 500,
        'TITLE_FIELD_NAME' => 'TITLE',
        'LIST_VIEW_NAME' => 'list',
        'TEMPLATE' => 'select'
    );

    /**
     * @inheritdoc
     */
    public function genEditHtml()
    {
        if($this->getSettings('TEMPLATE') == 'radio')
        {
            $html = $this->genEditHtmlInputs();
        }
        else
        {
            $html = $this->genEditHtmlSelect();
        }

        return $html;
    }

    /**
     * Генерирует HTML с выбором элемента во вcплывающем окне, шаблон select
     * @return string
     */
    public function genEditHtmlSelect()
    {
        $inputSize = (int) $this->getSettings('INPUT_SIZE');
        $windowWidth = (int) $this->getSettings('WINDOW_WIDTH');
        $windowHeight = (int) $this->getSettings('WINDOW_HEIGHT');
        $module = $this->getSettings('MODULE_NAME');
        $view = $this->getSettings('LIST_VIEW_NAME');
        $additionalUrlParams = htmlentities($this->getSettings('ADDITIONAL_URL_PARAMS'));

        $name = 'FIELDS';
        $key = $this->getCode();

        $entityData = $this->getOrmElementData();

        if (!empty($entityData)) {
            $elementId = $entityData['ID'];
            $elementName = $entityData[$this->getSettings('TITLE_FIELD_NAME')] ?
                $entityData[$this->getSettings('TITLE_FIELD_NAME')] :
                Loc::getMessage('IBLOCK_ELEMENT_NOT_FOUND');
        } else {
            $elementId = '';
        }

        return '<input name="' . $this->getEditInputName() . '"
                     id="' . $name . '[' . $key . ']"
                     value="' . $elementId . '"
                     size="' . $inputSize . '"
                     type="text">' .
                '<input type="button"
                    value="..." onClick="jsUtils.OpenWindow(\'/bitrix/admin/admin_helper_route.php?lang=' . LANGUAGE_ID
                    . '&amp;module=' . $module . '&amp;view=' . $view . '&amp;popup=Y'
                    . '&amp;eltitle=' . $this->getSettings('TITLE_FIELD_NAME')
                    . '&amp;n=' . $name . '&amp;k=' . $key . $additionalUrlParams . '\', ' . $windowWidth . ', '
                    . $windowHeight . ');">' . '&nbsp;<span id="sp_' . md5($name) . '_' . $key . '" >' . $elementName
                    . '</span>';
    }

    /**
     * Генерирует HTML с выбором элемента в виде радио инпутов
     * @return string
     */
    public function genEditHtmlInputs()
    {
        $return = '';

        $elementList = $this->getOrmElementList();

        if(!is_null($elementList))
        {
            foreach($elementList as $key => $element)
            {
                $return .= InputType("radio", $this->getEditInputName(), $element['ID'], $this->getValue(), false, $element['TITLE']);
            }
        }

        return $return;
    }

    /**
     * @inheritdoc
     */
    public function genMultipleEditHTML()
    {
        $inputSize = (int) $this->getSettings('INPUT_SIZE');
        $windowWidth = (int) $this->getSettings('WINDOW_WIDTH');
        $windowHeight = (int) $this->getSettings('WINDOW_HEIGHT');
        $module = $this->getSettings('MODULE_NAME');
        $view = $this->getSettings('LIST_VIEW_NAME');

        $name = 'FIELDS';
        $key = $this->getCode();

        $uniqueId = $this->getEditInputHtmlId();

        $entityListData = $this->getOrmElementData();

        ob_start();
        ?>

        <div id="<?= $uniqueId ?>-field-container" class="<?= $uniqueId ?>"></div>

        <script>
            var multiple = new MultipleWidgetHelper(
                '#<?= $uniqueId ?>-field-container',
                '<input name="<?=$key?>[#field_id#][VALUE]"' +
                'id="<?=$name?>[#field_id#]"' +
                'value="#value#"' +
                'size="<?=$inputSize?>"' +
                'type="text">' +
                '<input type="button"' +
                'value="..."' +
                'onClick="jsUtils.OpenWindow(\'/bitrix/admin/admin_helper_route.php?lang=<?=LANGUAGE_ID?>' +
                '&amp;module=<?=$module?>&amp;view=<?=$view?>&amp;popup=Y' +
                '&amp;eltitle=<?=$this->getSettings('TITLE_FIELD_NAME')?>' +
                '&amp;n=<?=$name?>&amp;k=#field_id#\', <?=$windowWidth?>, <?=$windowHeight?>);">' +
                '&nbsp;<span id="sp_<?=md5($name)?>_#field_id#" >#element_title#</span>'
            );
            <?
            if (!empty($entityListData))
            {
                foreach($entityListData as $referenceData)
                {
                    $elementId = $referenceData['ID'];
                    $elementName = $referenceData[$this->getSettings('TITLE_FIELD_NAME')] ?
                            $referenceData[$this->getSettings('TITLE_FIELD_NAME')] :
                            Loc::getMessage('IBLOCK_ELEMENT_NOT_FOUND');

                    ?>
            multiple.addField({
                value: '<?= $elementId ?>',
                field_id: <?= $elementId ?>,
                element_title: '<?= $elementName?>'
            });
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
     * @inheritdoc
     */
    public function getValueReadonly()
    {
        $entityData = $this->getOrmElementData();

        if (!empty($entityData)) {
            $entityName = $entityData[$this->getSettings('TITLE_FIELD_NAME')] ?
                $entityData[$this->getSettings('TITLE_FIELD_NAME')] :
                Loc::getMessage('IBLOCK_ELEMENT_NOT_FOUND');

            return '[' . $entityData['ID'] . ']' . $entityName;
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function getMultipleValueReadonly()
    {
        $entityListData = $this->getOrmElementData();

        if (!empty($entityListData)) {
            $multipleData = array();

            foreach ($entityListData as $entityData) {
                $entityName = $entityData[$this->getSettings('TITLE_FIELD_NAME')] ?
                    $entityData[$this->getSettings('TITLE_FIELD_NAME')] :
                    Loc::getMessage('IBLOCK_ELEMENT_NOT_FOUND');

                $multipleData[] = '[' . $entityData['ID'] . ']' . $entityName;
            }

            return implode('<br />', $multipleData);
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function genListHTML(&$row, $data)
    {
        if ($this->getSettings('MULTIPLE')) {
            $strElement = static::getMultipleValueReadonly();
        } else {
            $strElement = static::getValueReadonly();
        }
        $row->AddViewField($this->getCode(), $strElement);
    }

    /**
     * @inheritdoc
     */
    public function genFilterHTML()
    {
        if ($this->getSettings('MULTIPLE')) {
            print '';
        } else {
            $inputSize = (int)$this->getSettings('INPUT_SIZE');
            $windowWidth = (int)$this->getSettings('WINDOW_WIDTH');
            $windowHeight = (int)$this->getSettings('WINDOW_HEIGHT');
            $module = $this->getSettings('MODULE_NAME');
            $view = $this->getSettings('LIST_VIEW_NAME');

            $name = 'FIND';
            $key = $this->getCode();

            print '<tr>';
            print '<td>' . $this->getSettings('TITLE') . '</td>';

            $editStr = '<input name="' . $this->getFilterInputName() . '"
                     id="' . $name . '[' . $key . ']"
                     value="' . $this->getCurrentFilterValue() . '"
                     size="' . $inputSize . '"
                     type="text">' .
                '<input type="button"
                    value="..."
                    onClick="jsUtils.OpenWindow(\'/bitrix/admin/admin_helper_route.php?lang=' . LANGUAGE_ID
                . '&amp;module=' . $module . '&amp;view=' . $view . '&amp;popup=Y'
                . '&amp;n=' . $name . '&amp;k=' . $key . '\', ' . $windowWidth . ', ' . $windowHeight . ');">';

            print '<td>' . $editStr . '</td>';

            print '</tr>';
        }
    }

    /**
     * Получает информацию о записях, к которым осуществлена привязка.
     *
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     */
    protected function getOrmElementData()
    {
        $refInfo = array();
        $valueList = null;

        if ($this->getSettings('MULTIPLE')) {
            $entityName = $this->entityName;

            $rsMultEntity = $entityName::getList(array(
                'select' => array('REFERENCE_' => $this->getCode() . '.*'),
                'filter' => array('=ID' => $this->data['ID'])
            ));

            while ($multEntity = $rsMultEntity->fetch()) {
                $valueList[$multEntity['REFERENCE_VALUE']] = $multEntity['REFERENCE_VALUE'];
            }
        } else {
            $value = $this->getValue();
            if (!empty($value)) {
                $valueList[$value] = $value;
            }
        }

        if ($valueList) {
            $model = $this->getSettings('MODEL');

            $rsEntity = $model::getList(array(
                'filter' => array('ID' => $valueList)
            ));

            while ($entity = $rsEntity->fetch()) {
                if (in_array($entity['ID'], $valueList)) {
                    unset($valueList[$entity['ID']]);
                }

                if ($this->getSettings('MULTIPLE')) {
                    $refInfo[] = $entity;
                } else {
                    $refInfo = $entity;
                    break;
                }
            }

            foreach ($valueList as $entityId) {
                if ($this->getSettings('MULTIPLE')) {
                    $refInfo[] = array('ID' => $entityId);
                } else {
                    $refInfo = array('ID' => $entityId);
                    break;
                }
            }
        }

        return $refInfo;
    }

    /**
     * Получает информацию о всех активных элементах для выбора в input
     *
     * @return array
     *
     * @throws \Bitrix\Main\ArgumentException
     */
    protected function getOrmElementList()
    {
        $valueList = null;

        $model = $this->getSettings('MODEL');

        $rsEntity = $model::getList([
            'filter' => [
                'ACTIVE' => 1
            ],
            'select' => [
                'ID',
                'TITLE'
            ]
        ]);

        while ($entity = $rsEntity->fetch())
        {
            $valueList[] = $entity;
        }

        return $valueList;
    }
}