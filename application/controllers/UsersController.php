<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of UsersController
 *
 * @author araneo
 */
class UsersController extends Zend_Controller_Action {

	/**
	 * Inicjalizacja
	 */
	public function init() {
		$this->_auth = Zend_Auth::getInstance();
		$this->view->identity = $this->_auth->getIdentity();

		$this->_errorMessenger = new Zend_Controller_Action_Helper_FlashMessenger();
		$this->_errorMessenger->setActionController($this)->init();
		$this->_errorMessenger->setNamespace('error');

		$this->_noticeMessenger = new Zend_Controller_Action_Helper_FlashMessenger();
		$this->_noticeMessenger->setActionController($this)->init();
		$this->_noticeMessenger->setNamespace('notice');

		$this->view->errors = $this->_errorMessenger->getMessages();
		$this->view->notices = $this->_noticeMessenger->getMessages();
	}

	public function indexAction() {
		$this->view->title = "Lista użytkowników";
		$this->view->headTitle($this->view->title, 'PREPEND');

		$user = new Application_Model_User();
		$select = $user->getSelect();
		$select->order('active ASC');
		$this->view->users = $user->getAllUsers($select);
	}

	public function addAction() {
		$this->view->title = "Dodawanie nowego użytkownika";
		$this->view->headTitle($this->view->title, 'PREPEND');
		$form = new Application_Form_User();
		if ($this->getRequest()->isPost()) {
			if ($form->isValid($this->getRequest()->getPost())) {
				$user = new Application_Model_User();
				$user->fill($form->getValues());
				$user->created = date("Y-m-d H:i:s");
				$user->password = sha1($user->password);
				$user->save();
				$this->_helper->redirector('index');
			}
		}

		$this->view->form = $form;
	}

	public function deleteAction() {
		$id = $this->_getParam('id');
		$user = new Application_Model_User($id);
		$user->delete();
		$this->_helper->redirector('index');
	}

	public function editAction() {
		$this->view->title = "Edycja danych użytkownika";
		$this->view->headTitle($this->view->title, 'PREPEND');
		$id = $this->_getParam('id');
		$user = new Application_Model_User($id);
		if ($user->username) {
			$form = new Application_Form_User();
			if ($this->getRequest()->isPost()) {
				if ($form->isValid($this->getRequest()->getPost())) {
					$user->modifed = date("Y-m-d H:i:s");
					$user->username = $user->username;
					if ($form->getValue('password')) {
						$user->password = sha1($form->getValue('password'));
					} else {
						$user->password = $user->password;
					}
					if ($form->getValue('email')) {
						$user->email = $form->getValue('email');
					}
					$user->active = $form->getValue('active');
					$user->edit_only_self = $form->getValue('edit_only_self');
					$user->save();
					//$user->sendMail();
					$this->_flashNotice('Dane użytkownika zostałe zmienione.');
					$this->_helper->redirector('index');
				}
			} else {
				$form->populate($user->populateForm());
			}
			$this->view->form = $form;
		}
	}

	/**
	 * Rejestracja nowego użytkownika
	 */
	public function registrationAction() {
		$this->view->title = "Rejestracja nowego użytkownika";
		$this->view->headTitle($this->view->title, 'PREPEND');
		$form = new Application_Form_Registration();
		if ($this->getRequest()->isPost()) {
			if ($form->isValid($this->getRequest()->getPost())) {
				$user = new Application_Model_User();
				$user->fill($form->getValues());
				$user->created = date("Y-m-d H:i:s");
				$user->password = sha1($user->password);
				$user->save();
				//$user->sendMail();
				//$this->_helper->redirector('login','index');
				$this->view->success = 1;
			}
		}
		$this->view->form = $form;
	}

	/**
	 * Edycja własnego profila
	 */
	public function profileAction() {
		$this->view->title = "Edycja profila";
		$this->view->headTitle($this->view->title, 'PREPEND');
		$form = new Application_Form_Profile();
		if (Zend_Auth::getInstance()->hasIdentity()) {
			$identity = Zend_Auth::getInstance()->getIdentity();
			$user = new Application_Model_User($identity->id);
			if ($this->getRequest()->isPost()) {
				if ($form->isValid($this->getRequest()->getPost())) {
					$user->modifed = date("Y-m-d H:i:s");
					$user->username = $user->username;
					if ($form->getValue('password')) {
						$user->password = sha1($form->getValue('password'));
					} else {
						$user->password = $user->password;
					}
					if ($form->getValue('email')) {
						$user->email = $form->getValue('email');
					}
					if ($form->getValue('redmine_api_key')) {
						$user->redmine_api_key = $form->getValue('redmine_api_key');
					}
					$user->save();
					//$user->sendMail();
					$this->_flashNotice('Dane zostałe zmienione. Mysisz zalogować się ponownie.');
					$this->_helper->redirector('profile');
				}
			} else {
				$form->populate($user->populateForm());
			}
		}
		$this->view->form = $form;
	}

	protected function _flashError($message) {
		$this->_errorMessenger->addMessage($message);
	}

	protected function _flashNotice($message) {
		$this->_noticeMessenger->addMessage($message);
	}

}

?>
