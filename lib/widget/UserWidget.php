<?php

namespace DigitalWand\AdminHelper\Widget;

use DigitalWand\AdminHelper\Widget\NumberWidget;

/**
 * Виджет для вывода пользователя
 * Доступные опции:
 * <ul>
 * <li> STYLE - inline-стили
 * <li> SIZE - значение атрибута size для input
 * </ul>
 */
class UserWidget extends NumberWidget
{
    public function genEditHtml()
    {
        $style = $this->getSettings('STYLE');
        $size = $this->getSettings('SIZE');

        $userId = $this->getValue();

        $strUser = '';
        if(!empty($userId) && $userId != 0)
        {
            $rsUser = \CUser::GetByID($userId);
            $arUser = $rsUser->Fetch();

            $strUser = '[<a href="user_edit.php?lang=ru&ID='.$arUser['ID'].'">' . $arUser['ID'] . '</a>] (' . $arUser['EMAIL']  . ') ' . $arUser['NAME'] . '&nbsp;' .$arUser['LAST_NAME'];
        }

        return '<input type="text"
                       name="' . $this->getEditInputName() . '"
                       value="' . htmlentities($this->getValue(), ENT_QUOTES) . '"
                       size="' . $size . '"
                       style="' . $style . '"/>' . $strUser;
    }

    public function getValueReadonly()
    {
        $userId = $this->getValue();
        $strUser = '';

        if(!empty($userId) && $userId != 0)
        {
            $rsUser = \CUser::GetByID($userId);
            $arUser = $rsUser->Fetch();

            $strUser = '[<a href="user_edit.php?lang=ru&ID='.$arUser['ID'].'">' . $arUser['ID'] . '</a>]';

            if($arUser['EMAIL'])
            {
                $strUser .= ' (' . $arUser['EMAIL']  . ')';
            }

            $strUser .=  ' '.$arUser['NAME'] . '&nbsp;' .$arUser['LAST_NAME'];
        }

        if($strUser)
        {
            return $strUser;
        }
        else
        {
            return '';
        }
    }


    public function genListHTML(&$row, $data)
    {
        $userId = $this->getValue();
        $strUser = '';

        if(!empty($userId) && $userId != 0)
        {
            $rsUser = \CUser::GetByID($userId);
            $arUser = $rsUser->Fetch();

            $strUser = '[<a href="user_edit.php?lang=ru&ID='.$arUser['ID'].'">' . $arUser['ID'] . '</a>]';

            if($arUser['EMAIL'])
            {
                $strUser .= ' (' . $arUser['EMAIL']  . ')';
            }

            $strUser .=  ' '.$arUser['NAME'] . '&nbsp;' .$arUser['LAST_NAME'];
        }

        if($strUser)
        {
            $row->AddViewField($this->getCode(), $strUser);
        }
        else
        {
            $row->AddViewField($this->getCode(), '');
        }
    }
}