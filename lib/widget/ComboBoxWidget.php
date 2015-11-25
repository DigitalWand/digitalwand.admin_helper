<?php

namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Выпадающий список.
 *
 * Доступные опции:
 * <ul>
 * <li> STYLE - inline-стили</li>
 * <li> VARIANTS - массив с вариантами значений или функция для их получения в формате ключ=>заголовок
 *        Например:
 *            [
 *                1=>'Первый пункт',
 *                2=>'Второй пункт'
 *            ]
 * </li>
 * <li> DEFAULT_VARIANT - ID варианта по-умолчанию</li>
 * </ul>
 */
class ComboBoxWidget extends HelperWidget
{
    static protected $defaults = array(
        'EDIT_IN_LIST' => true
    );

    /**
     * Генерирует HTML для редактирования поля
     *
     * @see AdminEditHelper::showField();
     *
     * @param bool $forFilter
     *
     * @return mixed
     */
    protected function genEditHTML($forFilter = false)
    {
        $style = $this->getSettings('STYLE');
        $multiple = $this->getSettings('MULTIPLE');
        $multipleSelected = array();

        if ($multiple) {
            $multipleSelected = $this->getMultipleValue();
        }

        $variants = $this->getVariants();

        if (empty($variants)) {
            $result = 'Не удалось получить данные для выбора';
        } else {
            $name = $forFilter ? $this->getFilterInputName() : $this->getEditInputName();
            $result = "<select name='" . $name . ($multiple ? '[]' : null) . "' "
                . ($multiple ? 'multiple="multiple"' : null) . " style='" . $style . "'>";

            if (!$multiple) {
                $variantEmpty = array(
                    '' => array(
                        'ID' => '',
                        'TITLE' => Loc::getMessage('COMBO_BOX_LIST_EMPTY')
                    )
                );
                $variants = $variantEmpty + $variants;
            }

            $default = $this->getValue();

            if (is_null($default)) {
                $default = $this->getSettings('DEFAULT_VARIANT');
            }

            foreach ($variants as $id => $data) {
                $name = strlen($data["TITLE"]) > 0 ? $data["TITLE"] : "";
                $selected = false;

                if ($multiple) {
                    if (in_array($id, $multipleSelected)) {
                        $selected = true;
                    }
                } else {
                    if ($id == $default) {
                        $selected = true;
                    }
                }

                $result .= "<option value='" .
                    static::prepareToTagAttr($id)
                    . "' " . ($selected ? "selected" : "") . ">" .
                    static::prepareToTagAttr($name)
                    . "</option>";
            }

            $result .= "</select>";
        }

        return $result;
    }

    public function processEditAction()
    {
        if ($this->getSettings('MULTIPLE')) {
            $sphere = $this->data[$this->getCode()];
            unset($this->data[$this->getCode()]);

            foreach ($sphere as $sphereKey) {
                $this->data[$this->getCode()][] = array('VALUE' => $sphereKey);
            }
        }

        parent::processEditAction();
    }

    protected function genMultipleEditHTML()
    {
        return $this->genEditHTML();
    }

    protected function getValueReadonly()
    {
        $variants = $this->getVariants();
        $value = $variants[$this->getValue()]['TITLE'];

        return static::prepareToOutput($value);
    }

    /**
     * Возвращает массив в формате
     * <code>
     * array(
     *      '123' => array('ID' => 123, 'TITLE' => 'ololo'),
     *      '456' => array('ID' => 456, 'TITLE' => 'blablabla'),
     *      '789' => array('ID' => 789, 'TITLE' => 'pish-pish'),
     * )
     * </code>
     * Результат будет выводиться в комбобоксе
     * @return array
     */
    protected function getVariants()
    {
        $variants = $this->getSettings('VARIANTS');
        if (is_array($variants) AND !empty($variants)) {
            return $this->formatVariants($variants);
        } else if (is_callable($variants)) {
            $var = $variants();
            if (is_array($var)) {
                return $this->formatVariants($var);
            }
        }

        return array();
    }

    /**
     * Приводит варианты к нужному формату, если они заданы в виде одномерного массива.
     *
     * @param $variants
     *
     * @return array
     */
    protected function formatVariants($variants)
    {
        $formatted = array();

        foreach ($variants as $id => $data) {
            if (!is_array($data)) {
                $formatted[$id] = array(
                    'ID' => $id,
                    'TITLE' => $data
                );
            }
        }

        return $formatted;
    }

    /**
     * Генерирует HTML для поля в списке
     *
     * @see AdminListHelper::addRowCell();
     *
     * @param CAdminListRow $row
     * @param array $data - данные текущей строки
     *
     * @return mixed
     */
    public function genListHTML(&$row, $data)
    {
        if ($this->settings['EDIT_IN_LIST'] AND !$this->settings['READONLY']) {
            $row->AddInputField($this->getCode(), array('style' => 'width:90%'));
        } else {
            $row->AddViewField($this->getCode(), $this->getValueReadonly());
        }
    }

    /**
     * Генерирует HTML для поля фильтрации
     * @see AdminListHelper::createFilterForm();
     * @return mixed
     */
    public function genFilterHTML()
    {
        print '<tr>';
        print '<td>' . $this->getSettings('TITLE') . '</td>';
        print '<td>' . $this->genEditHTML(true) . '</td>';
        print '</tr>';
    }

    /**
     * @todo Метод имеет устаревшую логику!
     *
     * @param $model Модель
     * @param string $field Название поля
     * @param array $variants Варианты значения (ключ => значение)
     *
     * @return null|array Формат ['key' => ключ, 'title' => название]
     */
    public static function getValueDetails(OrmModel $model, $field, array $variants)
    {
        $value = $model->{$field};
        $title = (empty($variants[$value]) ? 'error: название не найдено' : $variants[$value]);

        return array('key' => $value, 'title' => $title);
    }

    /**
     * {@inheritdoc}
     */
    protected function getMultipleValueReadonly()
    {
        $variants = $this->getVariants();
        $values = $this->getMultipleValue();
        $result = '';

        if (empty($variants)) {
            $result = 'Не удалось получить данные для выбора';
        } else {
            foreach ($variants as $id => $data) {
                $name = strlen($data["TITLE"]) > 0 ? $data["TITLE"] : "";

                if (in_array($id, $values)) {
                    $result .= static::prepareToOutput($name) . '<br/>';
                }
            }
        }

        return $result;
    }

    /*
     * TRICKY: При слиянии этот метод оказался дублем. Если точно знаешь, что он устарел, можно удалить
protected function getMultipleValueReadonly()
{
    $rsEntityData = null;
    if (!empty($this->data['ID']))
    {
        $entityName = $this->entityName;
        $rsEntityData = $entityName::getList([
            'select' => ['REFERENCE_' => $this->getCode() . '.*'],
            'filter' => ['=ID' => $this->data['ID']]
        ]);
    }

    while ($referenceData = $rsEntityData->fetch())
    {
        if (empty($referenceData['REFERENCE_VALUE']))
        {
            continue;
        }

        $multipleSelected[] = $referenceData['REFERENCE_VALUE'];
    }

    $variants = $this->getVariants();
    $result = '';
    if (empty($variants))
    {
        $result = 'Не удалось получить данные';
    }
    else
    {
        foreach($multipleSelected as $selectId)
        {
            if(isset($variants[$selectId]))
            {
                $result .= '<div class="wrap_text" style="margin-bottom: 5px">' . $variants[$selectId]['TITLE'] .
                    '</div>';
            }

        }
    }

    return $result;
}
*/

}