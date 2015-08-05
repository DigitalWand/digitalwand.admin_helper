<?php
namespace DigitalWand\AdminHelper\Widget;

use DigitalWand\AdminHelper\AdminBaseHelper;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\Entity\EntityError;
use Bitrix\Main\Entity\Result;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Localization\Loc;


Loc::loadMessages(__FILE__);

class HLIBlockFieldWidget extends HelperWidget
{

    static public $useBxAPI = true;
    static protected $userFieldsCache = array();

    static public function getUserFields($iblockId, $data)
    {
        /** @var \CAllUserTypeManager $USER_FIELD_MANAGER */
        global $USER_FIELD_MANAGER;
        $iblockId = 'HLBLOCK_'.$iblockId;
        if (!isset(static::$userFieldsCache[$iblockId][$data['ID']])) {
            $fields = $USER_FIELD_MANAGER->getUserFieldsWithReadyData($iblockId, $data, LANGUAGE_ID, false, 'ID');
            self::$userFieldsCache[$iblockId][$data['ID']] = $fields;
        }

        return self::$userFieldsCache[$iblockId][$data['ID']];
    }

    /**
     * Генерирует HTML для редактирования поля
     *
     * @see \CAdminForm::ShowUserFieldsWithReadyData
     * @see AdminEditHelper::showField();
     * @return mixed
     */
    protected function genEditHTML()
    {
        $iblockId = $this->getSettings('HLIBLOCK_ID');
        $fields = self::getUserFields($iblockId, $this->data);
        if (isset($fields[$this->getCode()])) {

            /** @var \CAllUserTypeManager $USER_FIELD_MANAGER */
            global $USER_FIELD_MANAGER;
            $FIELD_NAME = $this->getCode();
            $GLOBALS[$FIELD_NAME] = isset($GLOBALS[$FIELD_NAME]) ? $GLOBALS[$FIELD_NAME] : $this->data[$this->getCode()];
            $arUserField = $fields[$this->getCode()];
            $bVarsFromForm = false;

            //Копипаст из битрикса

            $arUserField["VALUE_ID"] = intval($this->data['ID']);

            if (isset($_REQUEST['def_'.$FIELD_NAME])) {
                $arUserField['SETTINGS']['DEFAULT_VALUE'] = $_REQUEST['def_'.$FIELD_NAME];
            }
            print $USER_FIELD_MANAGER->GetEditFormHTML($bVarsFromForm, $GLOBALS[$FIELD_NAME], $arUserField);

        }
    }

    /**
     * @see Bitrix\Highloadblock\DataManager
     * @see /bitrix/modules/highloadblock/admin/highloadblock_row_edit.php
     *
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\SystemException
     *
     * FIXME: переписать так, чтобы не зависел от ID хайлоад-иблока
     */
    public function processEditAction()
    {
        /** @var \CAllUserTypeManager $USER_FIELD_MANAGER */
        global $USER_FIELD_MANAGER;
        global $APPLICATION;
        $iblockId = 'HLBLOCK_'.$this->getSettings('HLIBLOCK_ID');

        $data = array();
        $USER_FIELD_MANAGER->EditFormAddFields($iblockId, $data);

        $entity_data_class = AdminBaseHelper::getHLEntity($this->getSettings('HLIBLOCK_ID'));

        $oldData = $this->getOldFieldData($entity_data_class);
        $fields = $USER_FIELD_MANAGER->getUserFieldsWithReadyData($iblockId, $oldData, LANGUAGE_ID, false, 'ID');
        list($data, $multiValues) = $this->convertValuesBeforeSave($data, $fields);
        // use save modifiers
        foreach ($data as $fieldName => $value)
        {
            $field = $entity_data_class::getEntity()->getField($fieldName);
            $data[$fieldName] = $field->modifyValueBeforeSave($value, $data);
        }

        //Чтобы не терялись старые данные
        if(!isset($data[$this->getCode()]) AND isset($data[$this->getCode().'_old_id'])){
            $data[$this->getCode()] = $data[$this->getCode().'_old_id'];
        }

        if($unserialized = unserialize($data[$this->getCode()])){
            $this->data[$this->getCode()] = $unserialized;
        } else {
            $this->data[$this->getCode()] = $data[$this->getCode()];
        }
    }

    /**
     * Битриксу надо получить поля, кторые сохранены в базе для этого пользовательского свойства.
     * Иначе множественные свойства он затрёт.
     * Проблема в том, что пользовательские свойства могут браться из связанной сущности.
     * @param HL\DataManager $entity_data_class
     *
     * @return mixed
     */
    protected function getOldFieldData($entity_data_class)
    {
        return $entity_data_class::getByPrimary($this->data[$this->helper->pk()])->fetch();
    }

    /**
     * @see Bitrix\Highloadblock\DataManager::convertValuesBeforeSave
     * @param $data
     * @param $userfields
     *
     * @return array
     */
    protected function convertValuesBeforeSave($data, $userfields)
    {
        $multiValues = array();

        foreach ($data as $k => $v)
        {
            if ($k == 'ID')
            {
                continue;
            }

            $userfield = $userfields[$k];

            if ($userfield['MULTIPLE'] == 'N')
            {
                $inputValue = array($v);
            }
            else
            {
                $inputValue = $v;
            }

            $tmpValue = array();

            foreach ($inputValue as $singleValue)
            {
                $tmpValue[] = $this->convertSingleValueBeforeSave($singleValue, $userfield);
            }

            // write value back
            if ($userfield['MULTIPLE'] == 'N')
            {
                $data[$k] = $tmpValue[0];
            }
            else
            {
                // remove empty (false) values
                $tmpValue = array_filter($tmpValue, 'strlen');

                $data[$k] = $tmpValue;
                $multiValues[$k] = $tmpValue;
            }
        }

        return array($data, $multiValues);
    }

    /**
     * @see Bitrix\Highloadblock\DataManager::convertSingleValueBeforeSave
     * @param $value
     * @param $userfield
     *
     * @return bool|mixed
     */
    protected function convertSingleValueBeforeSave($value, $userfield)
    {
        if(is_callable(array($userfield["USER_TYPE"]["CLASS_NAME"], "onbeforesave")))
        {
            $value = call_user_func_array(
                array($userfield["USER_TYPE"]["CLASS_NAME"], "onbeforesave"), array($userfield, $value)
            );
        }

        if(strlen($value)<=0)
        {
            $value = false;
        }

        return $value;
    }

    /**
     * Генерирует HTML для поля в списке
     *
     * @see AdminListHelper::addRowCell();
     *
     * @param \CAdminListRow $row
     * @param array          $data - данные текущей строки
     *
     * @return mixed
     */
    public function genListHTML(&$row, $data)
    {
        // TODO: Implement genListHTML() method.
    }

    /**
     * Генерирует HTML для поля фильтрации
     *
     * @see AdminListHelper::createFilterForm();
     * @return mixed
     */
    public function genFilterHTML()
    {
        // TODO: Implement genFilterHTML() method.
    }

}