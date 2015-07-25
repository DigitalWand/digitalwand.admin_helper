<?

IncludeModuleLangFile(__FILE__);

CModule::AddAutoloadClasses('digitalwand.admin_helper',
  array(
    'AdminHelper\AdminBaseHelper' => "lib/helper/AdminBaseHelper.php",
    'AdminHelper\AdminListHelper' => "lib/helper/AdminListHelper.php",
    'AdminHelper\AdminEditHelper' => "lib/helper/AdminEditHelper.php",

    'AdminHelper\Widget\HelperWidget' => "lib/helper/widget/HelperWidget.php",
    'AdminHelper\Widget\CheckboxWidget' => "lib/helper/widget/CheckboxWidget.php",
    'AdminHelper\Widget\StringWidget' => "lib/helper/widget/StringWidget.php",
    'AdminHelper\Widget\NumberWidget' => "lib/helper/widget/NumberWidget.php",
    'AdminHelper\Widget\ImageWidget' => "lib/helper/widget/ImageWidget.php",
    'AdminHelper\Widget\FileWidget' => "lib/helper/widget/FileWidget.php",
    'AdminHelper\Widget\TextAreaWidget' => "lib/helper/widget/TextAreaWidget.php",
    'AdminHelper\Widget\HLIBlockFieldWidget' => "lib/helper/widget/HLIBlockFieldWidget.php",

  )
);
