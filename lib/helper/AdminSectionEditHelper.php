<?php

namespace DigitalWand\AdminHelper\Helper;

/**
 * Класс-обертка для хелпера редактирования разделов.
 * 
 * Все хелперы отвечающие за редактирование разделов должны наследовать от этого класса. Название класса используется 
 * для определения к какому типу принадлежит хелпер:
 * - список элементов,
 * - редактирования элементов,
 * - список разделов,
 * - редактирование раздела.
 * 
 * @see AdminBaseHelper::getHelperClass
 * 
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 * @author Artem Yarygin <artx19@yandex.ru>
 */
class AdminSectionEditHelper extends AdminEditHelper
{
}