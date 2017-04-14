<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('digitalwand.admin_helper',
    array(
        'DigitalWand\AdminHelper\EventHandlers' => 'lib/EventHandlers.php',

        'DigitalWand\AdminHelper\Helper\Exception' => 'lib/helper/Exception.php',

        'DigitalWand\AdminHelper\Helper\AdminInterface' => 'lib/helper/AdminInterface.php',
        'DigitalWand\AdminHelper\Helper\AdminBaseHelper' => 'lib/helper/AdminBaseHelper.php',
        'DigitalWand\AdminHelper\Helper\AdminListHelper' => 'lib/helper/AdminListHelper.php',
        'DigitalWand\AdminHelper\Helper\AdminSectionListHelper' => 'lib/helper/AdminSectionListHelper.php',
        'DigitalWand\AdminHelper\Helper\AdminEditHelper' => 'lib/helper/AdminEditHelper.php',
        'DigitalWand\AdminHelper\Helper\AdminSectionEditHelper' => 'lib/helper/AdminSectionEditHelper.php',

        'DigitalWand\AdminHelper\EntityManager' => 'lib/EntityManager.php',
        'DigitalWand\AdminHelper\Sorting' => 'lib/Sorting.php',

        'DigitalWand\AdminHelper\Widget\HelperWidget' => 'lib/widget/HelperWidget.php',
        'DigitalWand\AdminHelper\Widget\CheckboxWidget' => 'lib/widget/CheckboxWidget.php',
        'DigitalWand\AdminHelper\Widget\ComboBoxWidget' => 'lib/widget/ComboBoxWidget.php',
        'DigitalWand\AdminHelper\Widget\StringWidget' => 'lib/widget/StringWidget.php',
        'DigitalWand\AdminHelper\Widget\NumberWidget' => 'lib/widget/NumberWidget.php',
        'DigitalWand\AdminHelper\Widget\FileWidget' => 'lib/widget/FileWidget.php',
        'DigitalWand\AdminHelper\Widget\TextAreaWidget' => 'lib/widget/TextAreaWidget.php',
        'DigitalWand\AdminHelper\Widget\HLIBlockFieldWidget' => 'lib/widget/HLIBlockFieldWidget.php',
        'DigitalWand\AdminHelper\Widget\DateTimeWidget' => 'lib/widget/DateTimeWidget.php',
        'DigitalWand\AdminHelper\Widget\IblockElementWidget' => 'lib/widget/IblockElementWidget.php',
        'DigitalWand\AdminHelper\Widget\UrlWidget' => 'lib/widget/UrlWidget.php',
        'DigitalWand\AdminHelper\Widget\VisualEditorWidget' => 'lib/widget/VisualEditorWidget.php',
        'DigitalWand\AdminHelper\Widget\UserWidget' => 'lib/widget/UserWidget.php',
        'DigitalWand\AdminHelper\Widget\OrmElementWidget' => 'lib/widget/OrmElementWidget.php',
    )
);
