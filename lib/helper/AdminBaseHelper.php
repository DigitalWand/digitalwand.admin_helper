<?php

namespace DigitalWand\AdminHelper;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use DigitalWand\AdminHelper\Widget\HelperWidget;
use Bitrix\Main\Entity\DataManager;
use Bitrix\Highloadblock as HL;

Loader::includeModule('highloadblock');
/**
 * Class AdminBaseHelper
 * Данный модуль реализует подход MVC для создания административного интерфейса.
 *
 * Возможность построения административного интерфейса появляется благодаря наличию единого API для CRUD-операциями над
 * сущностями. Поэтому построение админ. интерфейса средствами данного модуля возможно только для классов, реализующих
 * API ORM Битрикс. При желании использовать данный модуль для сущностей, не использующих ORM Битрикс, можно
 * подготовить для таких сущностей класс-обёртку, реализующий необходимые функции.
 *
 * Основные понятия модуля:
 * <ul>
 * <li>Мдель: "model" в терминах MVC. Класс, унаследованный от DataManager или реализующий аналогичный API.</li>
 * <li>Хэлпер: "view" в терминах MVC. Класс, реализующий отрисовку интерфейса списка или детальной страницы.</li>
 * <li>Роутер: "controller" в терминах MVC. Файл, принимающий все запросы к админке данного модуля, создающий нужные
 * хэлперы с нужными настройками. С ним напрямую работать не придётся.</li>
 * <li>Виджеты: "delegate" в терминах MVC. Классы, отвечающие за отрисовку элементов управления для отдельных полей
 * сущностей. В списке и на детальной.</li>
 * </ul>
 *
 * Схема работы с модулем следующя:
 * <ul>
 * <li>Реализация класса AdminListHelper - для управления страницей списка элементов</li>
 * <li>Реализация класса AdminEditHelper - для управления страницей просмотра/редактирования элемента</li>
 * <li>Создание файла Interface.php с вызовом AdminBaseHelper::setInterfaceSettings(), в которую передается
 * конфигурация
 * полей админки и классы, используемые для её построения.</li>
 * <li>Если не хватает возможностей виджетов, идущих с модулем, можно реализовать свой виджет, унаследованный от любого
 * другого готового виджета или от абстрактного класса HelperWidget</li>
 * </ul>
 *
 * Рекомендуемая файловая структура для модулей, использующих данный функционал:
 * <ul>
 * <li>Каталог <b>admin</b>. Достаточно поместить в него файл menu.php, отдельные файлы для списка и детальной
 * создавать не надо благодаря единому роутингу.</li>
 * <li>Каталог <b>classes</b> (или lib): содержит классы модли, представлений и делегатов.</li>
 * <li> -- <b>classes/helper</b>: каталог, содержащий классы "view", унаследованные от AdminListHelper и
 * AdminEditHelper.</li>
 * <li> -- <b>classes/helper/widget</b>: каталог, содержащий виджеты ("delegate"), если для модуля пришлось создавать
 * свои.</li>
 * <li> -- <b>classes/model</b>: каталог с моделями, если пришлось переопределять поведение стандартынх функций getList
 * и т.д.</li>
 * </ul>
 *
 * Использовать данную структуру не обязательно, это лишь рекомендация, основанная на успешном опыте применения модуля
 * в ряде проектов.
 *
 * @see AdminBaseHelper::setInterfaceSettings()
 * @package AdminHelper
 * @FIXME: Упростить обработку сообщений об ошибках: слишком запутанно.
 */
abstract class AdminBaseHelper
{
    /**
     * @internal
     * @var string адрес обработчика запросов к админ. интерфейсу.
     */
    static protected $routerUrl = '/bitrix/admin/admin_helper_route.php';

    /**
     * @var string
     * Имя класса используемой модели. Используется для выполнения CRUD-операций.
     * При наследовании класса необходимо переопределить эту переменную, указав полное имя класса модели.
     *
     * @see DataManager
     * @api
     */
    static protected $model;

    /**
     * @var string
     * Назвние модуля данной модели.
     * При наследовании класса необходимо указать нзвание модуля, в котором он находится.
     * Используется для избежания конфликтов между именами представлений.
     *
     * @api
     */
    static public $module = '';

    /**
     * @var string
     * Название представления.
     * При наследовании класса необходимо указать название представления. Оно будет использовано при построении URL к
     * данному разделу админки. Не должно содержать пробелов и других символов, требующих преобразований для
     * адресной строки браузера.
     *
     * @api
     */
    static protected $viewName;

    /**
     * @var array
     * Настройки интерфейса
     * @see AdminBaseHelper::setInterfaceSettings()
     * @internal
     */
    static protected $interfaceSettings = array();

    /**
     * @var array
     * Хранит список отображаемых полей и настройки их отображения
     * @see AdminBaseHelper::setInterfaceSettings()
     */
    protected $fields = array();

    /**
     * @var CMain
     * Замена global $APPLICATION;
     */
    protected $app;
    protected $validationErrors = array();

    /**
     * @var string
     * Позволяет непосредственно указать адрес страницы списка. Полезно, в случае, если такая станица реализована без
     * использования данного модуля. В случае, если поле определено для класса, роутинг не используется.
     *
     * @see AdminBaseHelper::getListPageUrl
     * @api
     */
    static protected $listPageUrl;

    /**
     * @var string
     * $viewName представления, отвечающего за страницу списка. Необходимо указывать только для классов, уналедованных
     * от AdminEditHelper.
     *
     * @see AdminBaseHelper::$viewName
     * @see AdminBaseHelper::getListPageUrl
     * @see AdminEditHelper
     * @api
     */
    static protected $listViewName;

    /**
     * @var string
     * Позволяет непосредственно указать адрес страницы просмотра/редактирования элемента. Полезно, в случае, если
     * такая станица реализована без использования данного модуля. В случае, если поле определено для класса,
     * роутинг не используется.
     *
     * @see AdminBaseHelper::getEditPageUrl
     * @api
     */
    static protected $editPageUrl;


    /**
     * @var string
     * $viewName представления, отвечающего за страницу редактирования/просмотра элемента. Необходимо указывать только
     *     для классов, уналедованных от AdminListHelper.
     *
     * @see AdminBaseHelper::$viewName
     * @see AdminBaseHelper::getEditPageUrl
     * @see AdminListHelper
     * @api
     */
    static protected $editViewName;

    /**
     * @var array
     * Дополнительные параметры URL, которые будут добавлены к параметрам по-умолчанию, генерируемым автоматически
     * @api
     */
    protected $additionalUrlParams = array();

    /**
     * @param array $fields список используемых полей и виджетов для них
     * @param array $tabs список вкладок для детальной страницы
     * @param string $module название модуля
     */
    public function __construct(array $fields, array $tabs = array(), $module = "")
    {
        global $APPLICATION;
        $this->app = $APPLICATION;

        $settings = array(
            'FIELDS' => $fields,
            'TABS' => $tabs
        );
        if (static::setInterfaceSettings($settings)) {
            $this->fields = $fields;
        } else {
            $settings = static::getInterfaceSettings();
            $this->fields = $settings['FIELDS'];
        }
    }

    /**
     * @return array
     * Возвращает настройки интерфейса для данного класса.
     * @see AdminBaseHelper::setInterfaceSettings()
     * @api
     */
    static public function getInterfaceSettings()
    {
        return static::$interfaceSettings;
    }

    /**
     * Основная функция для конфигурации всего административного интерфейса.
     *
     * @param array $settings настройки полей и вкладок
     * @param array $helpers список классов-хэлперов, используемых для отрисовки админки
     * @param string $module название модуля
     *
     * @return bool false, если для данного класса уже были утановлены настройки
     *
     * @api
     */
    static public function setInterfaceSettings(array $settings, array $helpers = array(), $module = "")
    {
        if (empty(static::$interfaceSettings)) {
            static::$interfaceSettings = $settings;
            if (!empty($helpers)) {
                static::registerHelpersInterfaceSettings($module, $helpers);
            }

            return true;

        } else {
            return false;
        }
    }

    /**
     * Регистрирует настройки интерфейса для всех переданных хэлперов
     *
     * @param string $module имя текущего модуля
     * @param array $helperModels массив хелперов, настройки интерфейса для которых нужно зарегистрировать.
     * @internal
     */
    static public function registerHelpersInterfaceSettings($module, $helperModels = array())
    {
        foreach ($helperModels as $helper/**@var AdminBaseHelper $helper */) {
            $helper::registerInterfaceSettings($module);
        }
    }

    /**
     * Регистрирует настройки интерфейса для текущего хелпера
     *
     * @param string $module имя текущего модуля
     * @return bool
     * @internal
     */
    static public function registerInterfaceSettings($module)
    {
        if (empty($module)) {
            return false;
        }
        self::$module = $module;

        $interface = static::getInterfaceSettings();
        if (empty($interface)) {
            return false;
        }

        global $admin_helperInterface;
        if (isset($admin_helperInterface[$module][static::$viewName])) {
            return false;
        }

        $admin_helperInterface[$module][static::$viewName] = array(
            'helper' => get_called_class(),
            'interface' => $interface
        );

        return true;
    }

    /**
     * Получает настройки интерфейса для данного модуля и представления
     * Используется при роутинге.
     * Возвращается массив со следующими ключами:
     *
     * <ul>
     * <li> helper - название класса-хэлпера, который будет рисовать страницу</li>
     * <li> interface - настройки интерфейса для хелпера</li>
     * </ul>
     *
     * @param string $module Модуль, для которого нужно получить настройки
     * @param string $view Название представления
     * @return array
     * @internal
     */
    static public function getGlobalInterfaceSettings($module, $view)
    {
        global $admin_helperInterface;
        if (!isset($admin_helperInterface[$module][$view])) {
            return false;
        }

        return array(
            $admin_helperInterface[$module][$view]['helper'],
            $admin_helperInterface[$module][$view]['interface'],
        );
    }

    /**
     * @return string
     * Возвращает имя текущего представления
     * @api
     */
    public static function getViewName()
    {
        return static::$viewName;
    }

    /**
     * @return \Bitrix\Main\Entity\DataManager|string Возвращает имя класса используемой модели
     * Возвращает имя класса используемой модели
     *
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\SystemException
     * @throws \Exception
     * @api
     */
    public static function getModel()
    {
        return static::getHLEntity(static::$model);
    }

    /**
     * Возвращает имя модуля
     * @return string
     * @api
     */
    static public function getModule()
    {
        return static::$module;
    }

    /**
     * Возвращает список полей, переданных через AdminBaseHelper::setInterfaceSettings()
     * @see AdminBaseHelper::setInterfaceSettings()
     * @return array
     * @api
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Окончательно выводит админисстративную страницу
     * @internal
     */
    abstract public function show();

    /**
     * Получает название таблицы используемой модели
     * @return mixed
     */
    public function table()
    {
        /**@var DataManager $className */
        $className = static::getModel();

        return $className::getTableName();
    }

    /**
     * Возвращает первичный ключ таблицы используемой модели
     * Для HL-инфоблоков битрикс - всегда ID. Но может поменяться для какой-либо другой сущности.
     * @return string
     * @api
     */
    public function pk()
    {
        return 'ID';
    }

    /**
     * Устанавливает заголовок раздела в админке
     * @param $title
     * @api
     */
    public function setTitle($title)
    {
        $this->app->SetTitle($title);
    }

    /**
     * Функция для обработки дополнительных операций над элементами в админке.
     * Как правило должно оканчиваться LocalRedirect после внесения изменений.
     *
     * @param string $action Название действия
     * @param null|int $id ID элемента
     * @api
     */
    protected function customActions($action, $id = null)
    {
        return;
    }

    /**
     * Выполняется проверка прав на выполнение опреаций редактирования элементов
     * @return bool
     * @api
     */
    protected function hasRights()
    {
        return true;
    }

    /**
     * Выводит сообщения об ошибках
     * @internal
     */
    protected function showMessages()
    {
        $allErrors = $this->getErrors();
        $notes = $this->getNotes();

        if (!empty($allErrors)) {
            $errorList[] = implode("\n", $allErrors);
        }
        if ($e = $this->getLastException()) {
            $errorList[] = trim($e->GetString());
        }


        if (!empty($errorList)) {
            $errorText = implode("\n\n", $errorList);
            \CAdminMessage::ShowOldStyleError($errorText);

        } else {
            if (!empty($notes)) {
                $noteText = implode("\n\n", $notes);
                \CAdminMessage::ShowNote($noteText);
            }
        }
    }

    /**
     * @return bool|\CApplicationException
     * @internal
     */
    protected function getLastException()
    {
        if (isset($_SESSION['APPLICATION_EXCEPTION']) AND !empty($_SESSION['APPLICATION_EXCEPTION'])) {
            /** @var CApplicationException $e */
            $e = $_SESSION['APPLICATION_EXCEPTION'];
            unset($_SESSION['APPLICATION_EXCEPTION']);

            return $e;
        } else {
            return false;
        }

    }

    /**
     * @param $e
     */
    protected function setAppException($e)
    {
        $_SESSION['APPLICATION_EXCEPTION'] = $e;
    }

    /**
     * Добавляет ошибку или массив ошибок для показа пользователю
     * @param array|string $errors
     * @api
     */
    public function addErrors($errors)
    {
        if (!is_array($errors)) {
            $errors = array($errors);
        }

        if (isset($_SESSION['ELEMENT_SAVE_ERRORS']) AND !empty($_SESSION['ELEMENT_SAVE_ERRORS'])) {
            $_SESSION['ELEMENT_SAVE_ERRORS'] = array_merge($_SESSION['ELEMENT_SAVE_ERRORS'],
                $errors);
        } else {
            $_SESSION['ELEMENT_SAVE_ERRORS'] = $errors;
        }
    }

    /**
     * Добавляет уведомление или список уведомлений для показа пользователю
     * @param array|string $notes
     * @api
     */
    public function addNotes($notes)
    {
        if (!is_array($notes)) {
            $notes = array($notes);
        }

        if (isset($_SESSION['ELEMENT_SAVE_NOTES']) AND !empty($_SESSION['ELEMENT_SAVE_NOTES'])) {
            $_SESSION['ELEMENT_SAVE_NOTES'] = array_merge($_SESSION['ELEMENT_SAVE_NOTES'],
                $notes);
        } else {
            $_SESSION['ELEMENT_SAVE_NOTES'] = $notes;
        }
    }

    /**
     * @return bool
     * @api
     */
    protected function getErrors()
    {
        if (isset($_SESSION['ELEMENT_SAVE_ERRORS']) AND !empty($_SESSION['ELEMENT_SAVE_ERRORS'])) {
            $errors = $_SESSION['ELEMENT_SAVE_ERRORS'];
            unset($_SESSION['ELEMENT_SAVE_ERRORS']);

            return $errors;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     * @api
     */
    protected function getNotes()
    {
        if (isset($_SESSION['ELEMENT_SAVE_NOTES']) AND !empty($_SESSION['ELEMENT_SAVE_NOTES'])) {
            $notes = $_SESSION['ELEMENT_SAVE_NOTES'];
            unset($_SESSION['ELEMENT_SAVE_NOTES']);

            return $notes;
        } else {
            return false;
        }
    }

    /**
     * Возвращает URL страницы редактирования класса данного представления
     * @param array $params
     * @return string
     * @api
     */
    static public function getEditPageURL($params = array())
    {
        $viewName = isset(static::$editViewName) ? static::$editViewName : static::$viewName;
        if (!isset($viewName)) {
            $query = "?lang=" . LANGUAGE_ID . '&' . http_build_query($params);
            if (is_subclass_of(get_called_class(), 'AdminEditHelper')) {
                return $query;
            } else {
                return static::$editPageUrl . $query;
            }
        }

        return static::getViewURL($viewName, static::$editPageUrl, $params);
    }


    /**
     * Возвращает URL страницы списка класса данного представления
     * @param array $params
     * @return string
     * @api
     */
    static public function getListPageURL($params = array())
    {
        $viewName = isset(static::$listViewName) ? static::$listViewName : static::$viewName;

        return static::getViewURL($viewName, static::$listPageUrl, $params);
    }

    /**
     * Получает URL для указанного представления
     *
     * @param string $viewName название представления
     * @param string $defaultURL позволяет указать URL напрямую. Если указано, то будет использовано это значение
     * @param array $params - дополнительные query-параметры в URL
     * @return string
     * @internal
     */
    static public function getViewURL($viewName, $defaultURL, $params = array())
    {
        if (isset($defaultURL)) {
            $url = $defaultURL . "?lang=" . LANGUAGE_ID;
        } else {
            $url = static::getRouterURL() . '?lang=' . LANGUAGE_ID . '&module=' . static::getModule() . '&view=' . $viewName;
        }

        if (!empty($params)) {
            unset($params['lang']);
            unset($params['module']);
            unset($params['view']);

            $query = http_build_query($params);
            $url .= '&' . $query;
        }

        return $url;
    }

    /**
     * Возвращает адрес обработчика запросов к админ. интерфейсу.
     * @return string
     * @api
     */
    static public function getRouterURL()
    {
        return static::$routerUrl;
    }

    /**
     * Получает виджет для текущего поля, выполняет базовую инициализацию.
     *
     * @param string $code ключ поля для данного виджета (должен быть в массиве $data)
     * @param array $data - данные объекта в виде массива
     * @return bool|HelperWidget
     * @internal
     */
    public function createWidgetForField($code, &$data = array())
    {
        if (!isset($this->fields[$code]['WIDGET'])) {
            return false;
        }

        /** @var HelperWidget $widget */
        $widget = $this->fields[$code]['WIDGET'];
        $modelObject = isset($this->element) ? $this->element : $data;

        $widget->setHelper($this);
        $widget->setCode($code);
        $widget->setEntityName($modelObject);
        $widget->setData($data);

        return $widget;
    }

    /**
     * Если класс не объявлен, то битрикс генерирует новый класс в рантайме.
     * Если класс уже есть, то возвращаем имя как есть.
     *
     * @param $className
     * @return \Bitrix\Highloadblock\DataManager
     *
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\SystemException
     * @throws \Exception
     *
     */
    public static function getHLEntity($className)
    {
        if(!class_exists($className)){
            $parameters = array(
                'filter' => array(
                    'NAME' => $className,
                ),
                'limit' => 1
            );
            $hlInfo = HL\HighloadBlockTable::getList($parameters)->fetch();
            if($hlInfo){
                $entity = HL\HighloadBlockTable::compileEntity($hlInfo);
                return $entity->getDataClass();
            } else {
                $error = Loc::getMessage('DIGITALWAND_ADMIN_HELPER_GETMODEL_EXCEPTION');
                $exception = new \Exception($error);

                throw $exception;
            }
        }

        return $className;
    }
}