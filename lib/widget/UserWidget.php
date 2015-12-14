<?php

namespace DigitalWand\AdminHelper\Widget;

use Bitrix\Main\UserTable;

/**
 * Виджет для вывода пользователя.
 *
 * Доступные опции:
 * <ul>
 * <li> STYLE - inline-стили
 * <li> SIZE - значение атрибута size для input
 * </ul>
 *
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 */
class UserWidget extends NumberWidget
{
    /**
     * @inheritdoc
     */
    public function getEditHtml()
    {
        $style = $this->getSettings('STYLE');
        $size = $this->getSettings('SIZE');

        $userId = $this->getValue();

        $htmlUser = '';

        if (!empty($userId) && $userId != 0) {
            $rsUser = UserTable::getById($userId);
            $user = $rsUser->fetch();

            $htmlUser = '[<a href="user_edit.php?lang=ru&ID=' . $user['ID'] . '">' . $user['ID'] . '</a>] ('
                . $user['EMAIL'] . ') ' . $user['NAME'] . '&nbsp;' . $user['LAST_NAME'];
        }

        return '<input type="text"
                       name="' . $this->getEditInputName() . '"
                       value="' . static::prepareToTagAttr($this->getValue()) . '"
                       size="' . $size . '"
                       style="' . $style . '"/>' . $htmlUser;
    }

    /**
     * @inheritdoc
     */
    public function getValueReadonly()
    {
        $userId = $this->getValue();
        $htmlUser = '';

        if (!empty($userId) && $userId != 0) {
            $rsUser = UserTable::getById($userId);
            $user = $rsUser->fetch();

            $htmlUser = '[<a href="user_edit.php?lang=ru&ID=' . $user['ID'] . '">' . $user['ID'] . '</a>]';

            if ($user['EMAIL']) {
                $htmlUser .= ' (' . $user['EMAIL'] . ')';
            }

            $htmlUser .= ' ' . static::prepareToOutput($user['NAME'])
                . '&nbsp;' . static::prepareToOutput($user['LAST_NAME']);
        }

        return $htmlUser;
    }

    /**
     * @inheritdoc
     */
    public function generateRow(&$row, $data)
    {
        $userId = $this->getValue();
        $strUser = '';

        if (!empty($userId) && $userId != 0) {
            $rsUser = UserTable::getById($userId);
            $user = $rsUser->fetch();

            $strUser = '[<a href="user_edit.php?lang=ru&ID=' . $user['ID'] . '">' . $user['ID'] . '</a>]';

            if ($user['EMAIL']) {
                $strUser .= ' (' . $user['EMAIL'] . ')';
            }

            $strUser .= ' ' . static::prepareToOutput($user['NAME'])
                . '&nbsp;' . static::prepareToOutput($user['LAST_NAME']);
        }

        if ($strUser) {
            $row->AddViewField($this->getCode(), $strUser);
        } else {
            $row->AddViewField($this->getCode(), '');
        }
    }
}