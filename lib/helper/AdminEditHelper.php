<?php

namespace DigitalWand\AdminHelper\Helper;

use Bitrix\Main\Localization\Loc;
use DigitalWand\AdminHelper\EntityManager;
use DigitalWand\AdminHelper\Widget\HelperWidget;
use Bitrix\Main\Entity\DataManager;

Loc::loadMessages(__FILE__);

/**
 * Базовый класс для реализации детальной страницы админки.
 * При создании своего класса необходимо переопределить следующие переменные:
 * <ul>
 * <li> static protected $model</li>
 * </ul>
 *
 * Этого будет дастаточно для получения минимальной функциональности.
 *
 * @package AdminHelper
 * 
 * @see AdminBaseHelper::$model
 * @see AdminBaseHelper::$module
 * @see AdminBaseHelper::$listViewName
 * @see AdminBaseHelper::$viewName
 *
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 * @author Artem Yarygin <artx19@yandex.ru>
 */
abstract class AdminEditHelper extends AdminBaseHelper
{
	const OP_SHOW_TAB_ELEMENTS = 'AdminEditHelper::showTabElements';
	const OP_EDIT_ACTION_BEFORE = 'AdminEditHelper::editAction_before';
	const OP_EDIT_ACTION_AFTER = 'AdminEditHelper::editAction_after';

	/**
	 * @var array Данные сущности, редактируемой в данный момент. Ключи ассива — названия полей в БД.
	 * @api
	 */
	protected $data;
	/**
	 * @var array Вкладки страницы редактирования.
	 */
	protected $tabs = array();
	/**
	 * @var array Элементы верхнего меню страницы.
	 * @see AdminEditHelper::fillMenu()
	 */
	protected $menu = array();
	/**
	 * @var \CAdminForm
	 */
	protected $tabControl;

	/**
	 * Производится инициализация переменных, обработка запросов на редактирование
	 *
	 * @param array $fields
	 * @param array $tabs
	 */
	public function __construct(array $fields, array $tabs = array())
	{
		$this->tabs = $tabs;

		if (empty($this->tabs)) {
			$this->tabs = array(
				array(
					'DIV' => 'DEFAULT_TAB',
					'TAB' => Loc::getMessage('DEFAULT_TAB'),
					'ICON' => 'main_user_edit',
					'TITLE' => Loc::getMessage('DEFAULT_TAB'),
					'VISIBLE' => true,
				)
			);
		}
		else {
			if (!is_array(reset($this->tabs))) {
				$converted = array();

				foreach ($this->tabs as $tabCode => $tabName) {
					$tabVisible = true;

					if (is_array($tabName)) {
						$tabVisible = isset($tabName['VISIBLE']) ? $tabName['VISIBLE'] : $tabVisible;
						$tabName = $tabName['TITLE'];
					}

					$converted[] = array(
						'DIV' => $tabCode,
						'TAB' => $tabName,
						'ICON' => '',
						'TITLE' => $tabName,
						'VISIBLE' => $tabVisible,
					);
				}
				$this->tabs = $converted;
			}
		}

		parent::__construct($fields, $tabs);

		$this->tabControl = new \CAdminForm(str_replace("\\", "", get_called_class()), $this->tabs);

		if (isset($_REQUEST['apply']) OR isset($_REQUEST['save'])) {
			if (
				isset($_SERVER["HTTP_BX_AJAX"])
				||
				isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && $_SERVER["HTTP_X_REQUESTED_WITH"] === "XMLHttpRequest"
			) {
				\CUtil::JSPostUnescape();
			}
			$this->data = $_REQUEST['FIELDS'];

			if (isset($_REQUEST[$this->pk()])) {
				//Первичный ключ проставляем отдельно, чтобы не вынуждать всегда указывать его в настройках интерфейса.
				$this->data[$this->pk()] = $_REQUEST[$this->pk()];
			}

			foreach ($fields as $code => $settings) {
				if (isset($_REQUEST[$code])) {
					$this->data[$code] = $_REQUEST[$code];
				}
			}

			if ($this->editAction()) {
				if (isset($_REQUEST['apply'])) {
					$id = $this->data[$this->pk()];
					$url = $this->app->GetCurPageParam($this->pk() . '=' . (is_array($id) ? $id[$this->pk()] : $id), array('ID'));
				}
				else {
					if (isset($_REQUEST['save'])) {
						$listHelperClass = static::getHelperClass(AdminListHelper::className());
						$url = $listHelperClass::getUrl(array_merge($this->additionalUrlParams,
							array(
								'restore_query' => 'Y'
							)));
					}
				}
			}
			else {
				if (isset($this->data[$this->pk()])) {
					$id = $this->data[$this->pk()];
					$url = $this->app->GetCurPageParam($this->pk() . '=' . $id);
				}
				else {
					unset($this->data);
					$this->data = $_REQUEST['FIELDS']; //Заполняем, чтобы в случае ошибки сохранения поля не были пустыми
				}
			}

			if (isset($url)) {
				if (defined('BX_PUBLIC_MODE') && BX_PUBLIC_MODE === 1 && ($errors = $this->getErrors())) {
					ob_end_clean();
					$jsMessage = \CUtil::JSEscape(implode("\n", $errors));
					echo '<script>top.BX.WindowManager.Get().ShowError("' . $jsMessage . '");</script>';
					die();
				}
				$this->setAppException($this->app->GetException());
				LocalRedirect($url);
			}
		}
		else {
			$helperFields = $this->getFields();
			$select = array_keys($helperFields);

			foreach ($select as $key => $field) {
				if (isset($helperFields[$field]['VIRTUAL'])
					AND $helperFields[$field]['VIRTUAL'] == true
					AND (!isset($helperFields[$field]['FORCE_SELECT']) OR $helperFields[$field]['FORCE_SELECT'] = false)
				) {
					unset($select[$key]);
				}
			}

			$this->data = $this->loadElement($select);

			$id = isset($_REQUEST[$this->pk()]) ? $_REQUEST[$this->pk()] : null;

			if ($this->data === false && !is_null($id)) {
				$this->show404();
			}

			if (isset($_REQUEST['action']) || isset($_REQUEST['action_button'])) {
				$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : $_REQUEST['action_button'];
				$this->customActions($action, $this->getPk());
			}
		}

		$this->setElementTitle();
	}

	/**
	 * Возвращает верхнее меню страницы.
	 * По-умолчанию две кнопки:
	 * <ul>
	 * <li> Возврат в список</li>
	 * <li> Удаление элемента</li>
	 * </ul>
	 *
	 * Добавляя новые кнопки, нужно указывать параметр URl "action", который будет обрабатываться в
	 * AdminEditHelper::customActions()
	 *
	 * @param bool $showDeleteButton Управляет видимостью кнопки удаления элемента.
     * 
     * @return array
     * 
	 * @see AdminEditHelper::$menu
	 * @see AdminEditHelper::customActions()
	 * 
     * @api
	 */
	protected function getMenu($showDeleteButton = true)
	{
		$listHelper = static::getHelperClass(AdminListHelper::className());
        
		$menu = array(
			$this->getButton('RETURN_TO_LIST', array(
				'LINK' => $listHelper::getUrl(array_merge($this->additionalUrlParams,
					array('restore_query' => 'Y')
				)),
				'ICON' => 'btn_list',
			))
		);

		$arSubMenu = array();

		if (isset($this->data[$this->pk()]) && $this->hasWriteRights()) {
			$arSubMenu[] = $this->getButton('ADD_ELEMENT', array(
				'LINK' => static::getUrl(array_merge($this->additionalUrlParams,
					array(
						'action' => 'add',
						'lang' => LANGUAGE_ID,
						'restore_query' => 'Y',
					))),
				'ICON' => 'edit'
			));
		}

		if ($showDeleteButton && isset($this->data[$this->pk()]) && $this->hasDeleteRights()) {
			$arSubMenu[] = $this->getButton('DELETE_ELEMENT', array(
				'ONCLICK' => "if(confirm('" . Loc::getMessage('DIGITALWAND_ADMIN_HELPER_EDIT_DELETE_CONFIRM') . "')) location.href='" .
					static::getUrl(array_merge($this->additionalUrlParams,
						array(
							'ID' => $this->data[$this->pk()],
							'action' => 'delete',
							'lang' => LANGUAGE_ID,
							'restore_query' => 'Y',
						))) . "'",
				'ICON' => 'delete'
			));
		}

		if (count($arSubMenu)) {
			$menu[] = array('SEPARATOR' => 'Y');
			$menu[] = $this->getButton('ACTIONS', array(
				'MENU' => $arSubMenu,
				'ICON' => 'btn_new'
			));
		}

		return $menu;
	}

    /**
     * {@inheritdoc}
     */
	public function show()
	{
		if (!$this->hasReadRights()) {
			$this->addErrors(Loc::getMessage('DIGITALWAND_ADMIN_HELPER_ACCESS_FORBIDDEN'));
			$this->showMessages();

			return false;
		}

		$context = new \CAdminContextMenu($this->getMenu());
		$context->Show();

		$this->tabControl->BeginPrologContent();
		$this->showMessages();
		$this->showProlog();
		$this->tabControl->EndPrologContent();

		$this->tabControl->BeginEpilogContent();
		$this->showEpilog();
		$this->tabControl->EndEpilogContent();

		$query = $this->additionalUrlParams;
        
		if (isset($_REQUEST[$this->pk()])) {
			$query[$this->pk()] = $_REQUEST[$this->pk()];
		}
		elseif (isset($_REQUEST['SECTION_ID']) && $_REQUEST['SECTION_ID']) {
			$this->data[static::getSectionField()] = $_REQUEST['SECTION_ID'];
		}

		$this->tabControl->Begin(array(
			'FORM_ACTION' => static::getUrl($query)
		));

		foreach ($this->tabs as $tabSettings) {
			if ($tabSettings['VISIBLE']) {
				$this->showTabElements($tabSettings);
			}
		}

		$this->showEditPageButtons();
		$this->tabControl->ShowWarnings('editform', array()); //TODO: дописать
		$this->tabControl->Show();
	}

	/**
	 * Отображение кнопок для управления элементом на странице редактирования.
	 */
	protected function showEditPageButtons()
	{
		$listHelper = static::getHelperClass(AdminListHelper::className());
	
        $this->tabControl->Buttons(array(
			'back_url' => $listHelper::getUrl(array_merge($this->additionalUrlParams,
				array(
					'lang' => LANGUAGE_ID,
					'restore_query' => 'Y',
				)))
		));
	}

	/**
	 * Отрисовка верхней части страницы.
     * 
	 * @api
	 */
	protected function showProlog()
	{
	}

	/**
	 * Отрисовка нижней части страницы. По-умолчанию рисует все поля, которые не попали в вывод, как input hidden.
     * 
	 * @api
	 */
	protected function showEpilog()
	{
		echo bitrix_sessid_post();
	
        $interfaceSettings = static::getInterfaceSettings();

		foreach ($interfaceSettings['FIELDS'] as $code => $settings) {
			if (!isset($settings['TAB']) AND isset($settings['FORCE_SELECT']) AND $settings['FORCE_SELECT'] == true) {
				print '<input type="hidden" name="FIELDS[' . $code . ']" value="' . $this->data[$code] . '" />';
			}
		}
	}

	/**
	 * Отрисовывает вкладку со всеми привязанными к ней полями.
	 *
	 * @param $tabSettings
     * 
	 * @internal
	 */
	private function showTabElements($tabSettings)
	{
		$this->setContext(AdminEditHelper::OP_SHOW_TAB_ELEMENTS);
		$this->tabControl->BeginNextFormTab();

		foreach ($this->getFields() as $code => $fieldSettings) {
			$widget = $this->createWidgetForField($code, $this->data);
			$fieldTab = $widget->getSettings('TAB');
			$fieldOnCurrentTab = ($fieldTab == $tabSettings['DIV'] OR $tabSettings['DIV'] == 'DEFAULT_TAB');

			if (!$fieldOnCurrentTab) {
				continue;
			}

			$fieldSettings = $widget->getSettings();

			if (isset($fieldSettings['VISIBLE']) && $fieldSettings['VISIBLE'] === false) {
				continue;
			}

			$this->tabControl->BeginCustomField($code, $widget->getSettings('TITLE'));
			$pkField = ($code == $this->pk());
			$widget->showBasicEditField($pkField);
			$this->tabControl->EndCustomField($code);
		}
	}

	/**
	 * Обработка запроса редактирования страницы. Этапы:
	 * <ul>
	 * <li> Проверка прав пользователя</li>
	 * <li> Создание виджетов для каждого поля</li>
	 * <li> Удаление значений для READONLY и HIDE_WHEN_CREATE полей</li>
	 * <li> Изменение данных модели каждым виджетом (исходя из его внутренней логики)</li>
	 * <li> Валидация значений каждого поля соответствующим виджетом</li>
	 * <li> Проверка на ошибики валидации</li>
	 * <li> В случае неудачи - выход из функции</li>
	 * <li> В случае успеха - обновление или добавление элемента в БД</li>
	 * <li> Постобработка данных модели каждым виджетом</li>
	 * </ul>
	 *
	 * @return bool
	 * 
     * @see HelperWidget::processEditAction();
	 * @see HelperWidget::processAfterSaveAction();
	 * 
     * @internal
	 */
	protected function editAction()
	{
		$this->setContext(AdminEditHelper::OP_EDIT_ACTION_BEFORE);

		if (!$this->hasWriteRights()) {
			$this->addErrors(Loc::getMessage('DIGITALWAND_ADMIN_HELPER_EDIT_WRITE_FORBIDDEN'));

			return false;
		}

		$allWidgets = array();

		foreach ($this->getFields() as $code => $settings) {
			if ($settings['READONLY'] && $code !== $this->pk()) {
				unset($this->data[$code]);
			}
		}

		foreach ($this->getFields() as $code => $settings) {
			$widget = $this->createWidgetForField($code, $this->data);
			$widget->processEditAction();
			$this->validationErrors = array_merge($this->validationErrors, $widget->getValidationErrors());
			$allWidgets[] = $widget;

			if ($widget->getSettings('READONLY') || empty($this->data[$this->pk()]) 
				&& $widget->getSettings('HIDE_WHEN_CREATE')) {
				unset($this->data[$code]);
			}
		}

		$this->addErrors($this->validationErrors);
		$success = empty($this->validationErrors);

		if ($success) {
			$this->setContext(AdminEditHelper::OP_EDIT_ACTION_AFTER);
			$existing = false;
			$id = $this->getPk();

			if ($id) {
				/** @var DataManager $className */
				$className = static::getModel();
				// Если имеется primary key, то модель уже существующая, пытаемся найти ее в БД
				$existing = $className::getById($id)->fetch();
			}

			if ($existing) {
				$result = $this->saveElement($id);
			}
			else {
				$result = $this->saveElement();
			}

			if ($result) {
				if (!$result->isSuccess()) {
					$this->addErrors($result->getErrorMessages());

					return false;
				}
			}
			else {
				// TODO Вывод ошибки
				return false;
			}

			$this->data[$this->pk()] = $result->getId();

			foreach ($allWidgets as $widget) {
				/** @var HelperWidget $widget */
				$widget->setData($this->data);
				$widget->processAfterSaveAction();
			}

			return true;
		}

		return false;
	}

	/**
	 * Функция загрузки элемента из БД. Можно переопределить, если требуется сложная логика и нет возможности
	 * определить её в модели.
	 *
	 * @param array $select
	 *
	 * @return bool
	 * @api
	 */
	protected function loadElement($select = array())
	{
		if ($this->getPk() !== null) {
			$className = static::getModel();
			$result = $className::getById($this->getPk());

			return $result->fetch();
		}

		return false;
	}

	/**
	 * Сохранение элемента. Можно переопределить, если требуется сложная логика и нет возможности определить её 
     * в модели.
     * 
     * Операциями сохранения модели занимается EntityManager.
	 *
	 * @param bool $id
	 * 
     * @return \Bitrix\Main\Entity\AddResult|\Bitrix\Main\Entity\UpdateResult
	 * 
     * @throws \Exception
     * 
     * @see EntityManager
	 * 
     * @api
	 */
	protected function saveElement($id = null)
	{
		/** @var EntityManager $entityManager */
		$entityManager = new static::$entityManager(static::getModel(), empty($this->data) ? array() : $this->data, $id, $this);
		$saveResult = $entityManager->save();
		$this->addNotes($entityManager->getNotes());

		return $saveResult;
	}

	/**
	 * Удаление элемента. Можно переопределить, если требуется сложная логика и нет возможности определить её в модели.
	 *
	 * @param $id
	 * 
     * @return bool|\Bitrix\Main\Entity\DeleteResult
	 * 
     * @throws \Exception
	 * 
     * @api
	 */
	protected function deleteElement($id)
	{
		if (!$this->hasDeleteRights()) {
			$this->addErrors(Loc::getMessage('DIGITALWAND_ADMIN_HELPER_EDIT_DELETE_FORBIDDEN'));

			return false;
		}
		
		/** @var EntityManager $entityManager */
		$entityManager = new static::$entityManager(static::getModel(), empty($this->data) ? array() : $this->data, $id, $this);

		$deleteResult = $entityManager->delete();
		$this->addNotes($entityManager->getNotes());

		return $deleteResult;
	}

	/**
	 * Выполнение кастомных операций над объектом в процессе редактирования.
	 *
	 * @param string $action Название операции.
	 * @param int|null $id ID элемента.
     * 
	 * @see AdminEditHelper::fillMenu()
	 * 
     * @api
	 */
	protected function customActions($action, $id = null)
	{
		if ($action == 'delete' AND !is_null($id)) {
			$result = $this->deleteElement($id);
            
			if(!$result->isSuccess()){
				$this->addErrors($result->getErrorMessages());
			}
			
            $listHelper = static::getHelperClass(AdminListHelper::className());
            $redirectUrl = $listHelper::getUrl(array_merge(
                $this->additionalUrlParams,
                array('restore_query' => 'Y')
            ));
			
            LocalRedirect($redirectUrl);
		}
	}

	/**
	 * Устанавливает заголовок исходя из данных текущего элемента.
	 *
	 * @see $data
	 * @see AdminBaseHelper::setTitle()
	 * 
     * @api
	 */
	protected function setElementTitle()
	{
		if (!empty($this->data)) {
			$title = Loc::getMessage('DIGITALWAND_ADMIN_HELPER_EDIT_TITLE', array('#ID#' => $this->data[$this->pk()]));
		}
		else {
			$title = Loc::getMessage('DIGITALWAND_ADMIN_HELPER_NEW_ELEMENT');
		}

		$this->setTitle($title);
	}

	/**
	 * @return \CAdminForm
	 */
	public function getTabControl()
	{
		return $this->tabControl;
	}

    /**
     * @inheritdoc
     */
	public static function getUrl(array $params = array())
	{
		return static::getViewURL(static::getViewName(), static::$editPageUrl, $params);
	}
}
