<?php

namespace DigitalWand\AdminHelper;

use Bitrix\Main\Context;
use Bitrix\Main\Loader;

/**
 * Перехватчики событий.
 *
 * Для каждого события, возникающего в системе, которе необходимо отлавливать «Админ-хелпером», создаётся
 * в данном классе одноимённый метод. Метод должен быть зарегистрирован в системе через установщик модуля.
 *
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 */
class EventHandlers
{
    /**
     * Автоматическое подключение модуля в админке.
     *
     * Таки образом, исключаем необходимость прописывать в генераторах админки своих модулей
     * подключение «Админ-хелпера».
     *
     * @throws \Bitrix\Main\LoaderException
     */
    public static function onPageStart()
    {
        if (Context::getCurrent()->getRequest()->isAdminSection())
        {
            Loader::includeModule('digitalwand.admin_helper');
        }
    }
}