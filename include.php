<?

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

Loader::registerAutoLoadClasses('digitalwand.admin_helper',
    array(
        'DigitalWand\AdminHelper\AdminBaseHelper' => "lib/helper/AdminBaseHelper.php",
        'DigitalWand\AdminHelper\AdminListHelper' => "lib/helper/AdminListHelper.php",
        'DigitalWand\AdminHelper\AdminEditHelper' => "lib/helper/AdminEditHelper.php",

        'DigitalWand\AdminHelper\Widget\HelperWidget' => "lib/helper/widget/HelperWidget.php",
        'DigitalWand\AdminHelper\Widget\CheckboxWidget' => "lib/helper/widget/CheckboxWidget.php",
        'DigitalWand\AdminHelper\Widget\StringWidget' => "lib/helper/widget/StringWidget.php",
        'DigitalWand\AdminHelper\Widget\NumberWidget' => "lib/helper/widget/NumberWidget.php",
        'DigitalWand\AdminHelper\Widget\ImageWidget' => "lib/helper/widget/ImageWidget.php",
        'DigitalWand\AdminHelper\Widget\FileWidget' => "lib/helper/widget/FileWidget.php",
        'DigitalWand\AdminHelper\Widget\TextAreaWidget' => "lib/helper/widget/TextAreaWidget.php",
        'DigitalWand\AdminHelper\Widget\HLIBlockFieldWidget' => "lib/helper/widget/HLIBlockFieldWidget.php",

    )
);
