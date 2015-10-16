<?php
/**
 * Created by PhpStorm.
 * User: ASGAlex
 * Date: 17.10.2015
 * Time: 0:52
 */

namespace DigitalWand\AdminHelper;

use Bitrix\Main\Context;

/**
 * Class Loader
 * @package DigitalWand\AdminHelper
 *
 * Кастомный загрузчик.
 * Особенность этого класса в том, что он загружается в систему всегда,
 * даже когда модуль явно не подключен.
 */
class Loader
{
    /**
     * @param string $interface путь к Interface.php или полное наименование класса-интерфейса
     */
    static public function includeInterface($interface)
    {
        if (Context::getCurrent()->getRequest()->isAdminSection()) {
            if (class_exists($interface)) {
                $interface::register();
            } else {
                require_once $interface;
            }
        }
    }

}