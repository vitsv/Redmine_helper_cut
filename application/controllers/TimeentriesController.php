<?php

class TimeentriesController extends Zend_Controller_Action {

	public function preDispatch() {
		if (Zend_Auth::getInstance()->hasIdentity()) {
			$identity = Zend_Auth::getInstance()->getIdentity();
			if ($identity->redmine_api_key == "") {
				$this->_request->setControllerName('error');
				$this->_request->setActionName('apikeyerror');
			}
			$this->view->identity = $identity;
		}
	}

	public function init() {

		// Excel format context
		$excelConfig =
				array(
					'excel' => array(
						'suffix' => 'excel',
						'headers' => array(
							'Content-type' => 'application/vnd.ms-excel;charset=utf-8')),
		);

		// Init the Context Switch Action helper
		$contextSwitch = $this->_helper->contextSwitch();

		// Add the new context
		$contextSwitch->setContexts($excelConfig);

		// Set the new context to the reports action
		$contextSwitch->addActionContext('index', 'excel');



		// Initializes the action helper
		$contextSwitch->initContext();
	}

	public function indexAction() {
		$this->view->title = "Lista wpisów przepracowanych godzin";
		$this->view->headTitle($this->view->title, 'PREPEND');

		$time_entries = new Application_Model_TimeEntries();
		$form = new Application_Form_TimeEntrieFilter();

		$filtered = 0;
		$page = $this->_getParam('page');
		$params = $this->getRequest()->getParams();
		if ($form->isValid($this->getRequest()->getParams())) {
			if ($this->_getParam('showAll'))
				$paginator = $time_entries->getAllRows($params);
			else
				$paginator = $time_entries->getPaginatorRows($page, $params);
			if ($this->getRequest()->isPost())
				$filtered = 1;
		} else {
			if ($this->_getParam('showAll'))
				$paginator = $time_entries->getAllRows();
			else
				$paginator = $time_entries->getPaginatorRows($page);
		}
		if ($this->_getParam('showAll')) {
			$this->view->total = count($paginator);
			$this->view->no_pagination = 1;
		} else {
			$this->view->total = $paginator->getTotalItemCount();
		}
		$this->view->paginator = $paginator;

		$this->view->params = $params;
		$this->view->filtered = $filtered;

		//czy jest grupowanie
		$this->view->group_by_day = $this->_getParam('group_by_day');
		$this->view->group_by_tickiet = $this->_getParam('group_by_tickiet');
		$this->view->group_by_user = $this->_getParam('group_by_user');
		$this->view->rowspan = 0;

		$this->view->order = $order = (isset($params ['order']) and $params ['order'] == 'DESC') ? 'ASC' : 'DESC';
		$this->view->form = $form;
		$identity = Zend_Auth::getInstance()->getIdentity();
		$this->view->edit_only_self = $identity->edit_only_self;
		$config = new Config();
		$this->view->colums = $config->__get('colums');
		//pobieram grupy
		$select_groups = new Application_Model_SelectGroups();
		$this->view->select_groups = $select_groups->getMy($identity->id);
	}

	/**
	 *
	 * Uruchamia ręczną aktualnizację
	 */
	public function updateAction() {
		$this->view->success = 0;
		$this->view->title = "Aktualizaja bazy wpisów przepracowanych godzin";
		$this->view->headTitle($this->view->title, 'PREPEND');
		if ($this->_getParam('go')) {
			$updater = new External_Updater();
			$result = $updater->updateAll();
			if (!empty($result)) {
				foreach ($result as $key => $value)
					$this->view->$key = $value;
			}
			$this->view->success = 1;
		}
	}

	/**
	 * Wyczyszcza bazę i uruchamia aktualizację. Kasuje wpisy przepracowanego czasy, starsze 3 miesięcy,
	 * oraz wszystkie wpisy zagadnień które nie są związane z pozostaływmi wpisami przepracowanego czasu, oraz projekty, liste użytkownków
	 * i tag_time usuniętych zagadnień
	 */
	public function clearAction() {
		$this->view->success = 0;
		$this->view->title = "Wyczyść bazę";
		$this->view->headTitle($this->view->title, 'PREPEND');
		if ($this->_getParam('go')) {
			$time_entries = new Application_Model_TimeEntries();
			$time_entries->clear();
			$projects = new Application_Model_Projects();
			$projects->clear();
			$users = new Application_Model_RedMineUsers();
			$users->clear();
			$tagtime = new Application_Model_TagTime();
			$tagtime->clear();
			$updater = new External_Updater();
			$result = $updater->updateAll();
			if (!empty($result)) {
				foreach ($result as $key => $value)
					$this->view->$key = $value;
			}
			$this->view->success = 1;
		}
	}

	/**
	 * Generuje raport dla wybranych użytkowniĸów
	 */
	public function raportAction() {
		$this->view->title = "Raport aktywności";
		$this->view->headTitle($this->view->title, 'PREPEND');
		$form = new Application_Form_RaportForm();
		$this->view->form = $form;
		if ($this->getRequest()->isPost()) {
			$time_entries = new Application_Model_TimeEntries();
			$this->view->raport = $time_entries->generateRaport($this->_getParam('username'));
		}
	}

	/**
	 * Edytowanie przepracowanych godzin za pomoca ajax`u
	 */
	public function ajaxAction() {
		$ok = false;
		//wyłaczam layout
		$this->_helper->layout->disableLayout();

		//pobieram parametry
		$params = $this->getRequest()->getParams();

		//jezeli przekazany id, to pobieram dane z serwera
		if (isset($params['issue_id'])) {
			if (isset($params['estimated_hours']) || isset($params['declared_time'])) {
				$issue = new Application_Model_IssuesDetal();
				$issues = $issue->find($params['issue_id']);

				//jeżeli znaleziono, to zmeniamy
				if ($issues->response_code == "200") {
					$for_db = array();
					$my_issue = array();
					$my_issue['id'] = $issues->id;
					//podstawiam dane
					if (isset($params['estimated_hours'])) {
						$estimated_hours = 0;
						//sprawdzam czy godziny wprowadzone w formacie exela czy float
						if (strpos($params['estimated_hours'], ":") === false) {
							if (is_numeric($params['estimated_hours'])) {
								$estimated_hours = $params['estimated_hours'];
							} else {
								echo "error";
								return false;
							}
						} else {
							//jezeli jest dwukropek do rozdzielam godziny i minuty, sprawdzam czy dane sa poprawne i sumuje
							$hours = array();
							$hours = explode(":", $params['estimated_hours']);
							if (count($hours) == 2 && is_numeric($hours[0]) && is_numeric($hours[1])) {
								if ($hours[0] >= 0 && $hours[1] >= 0 && $hours[1] < 60) {
									$estimated_hours = (double) ($hours[0] + $hours[1] / 60);
								} else {
									echo "error";
									return false;
								}
							} else {
								echo "error";
								return false;
							}
						}
						$my_issue['estimated_hours'] = $estimated_hours;
						$for_db['estimated_hours'] = $estimated_hours;
					}
					if (isset($params['declared_time'])) {
						$declared_time = 0;
						//sprawdzam czy godziny wprowadzone w formacie exela czy float
						if (strpos($params['declared_time'], ":") === false) {
							if (is_numeric($params['declared_time'])) {
								$declared_time = $params['declared_time'];
							} else {
								echo "error";
								return false;
							}
						} else {
							//jezeli jest dwukropek do rozdzielam godziny i minuty, sprawdzam czy dane sa poprawne i sumuje
							$hours = array();
							$hours = explode(":", $params['declared_time']);
							if (count($hours) == 2 && is_numeric($hours[0]) && is_numeric($hours[1])) {
								if ($hours[0] >= 0 && $hours[1] >= 0 && $hours[1] < 60) {
									$declared_time = (double) ($hours[0] + $hours[1] / 60);
								} else {
									echo "error";
									return false;
								}
							} else {
								echo "error";
								return false;
							}
						}



						$custom_fileds = new SimpleXMLElement('<custom_fields type="array"></custom_fields>');
						$custom_filed = $custom_fileds->addChild('custom_field');
						$custom_filed->addAttribute('name', 'Deklarowany czas');
						$custom_filed->addAttribute('id', '22');
						$custom_filed_value = $custom_filed->addChild('value', $declared_time);
						$my_issue['custom_fields'] = $custom_fileds;
						$for_db['declared_time'] = $declared_time;

						/*
						  $custom_fileds = $issues->custom_fields;
						  //chyba nie jest najlepsze rozwiązanie, ale innego na razie nie wymysliłem
						  $i = 1;
						  foreach ($custom_fileds as $field) {
						  if ($i == 12)
						  $field->value = $declared_time;
						  $i++;
						  }
						  $issues->custom_fields = $custom_fileds; */
					}

					//$my_issue['updated_on'] = date("Y-m-d\TG:i:s+02:00");
					$issues->setData($my_issue);

					$issues->save();
					//sprawdzam czy zmiany sie zapisaly
					if ($issues->response_code == "200") {
						if (isset($params['issue_id'])) {
							$id_row = new Application_Model_DbTable_IssuesDetals();
							$update_id = new Application_Model_IssuesDetals(null, $id_row->fetchRow($id_row->select()
															->where('issue_id = ?', addslashes($params['issue_id']))));
							$update_id->updateOnlyTimes($for_db);
						}
						echo "ok";
						$ok = true;
					} else {
						echo "error";
						return false;
					}
				} else {
					echo "error";
					return false;
				}
			}

			//Tag użytkownika
			if (isset($params['tag_time'])) {
				$tag_time = 0;
				//sprawdzam czy godziny wprowadzone w formacie exela czy float
				if (strpos($params['tag_time'], ":") === false) {
					if (is_numeric($params['tag_time'])) {
						$tag_time = $params['tag_time'];
					} else {
						echo "error";
						return false;
					}
				} else {
					//jezeli jest dwukropek do rozdzielam godziny i minuty, sprawdzam czy dane sa poprawne i sumuje
					$hours = array();
					$hours = explode(":", $params['tag_time']);
					if (count($hours) == 2 && is_numeric($hours[0]) && is_numeric($hours[1])) {
						if ($hours[0] >= 0 && $hours[1] >= 0 && $hours[1] < 60) {
							$tag_time = (double) ($hours[0] + $hours[1] / 60);
						} else {
							echo "error";
							return false;
						}
					} else {
						echo "error";
						return false;
					}
				}
				$for_db['tag_time'] = $tag_time;

				//jeżeli jest ok to zapisujemy tag użytkownika

				$identity = Zend_Auth::getInstance()->getIdentity();
				$tag_time = new Application_Model_DbTable_TagTime();
				$select = $tag_time->select();
				$select->where('entrie_id = ?', addslashes($params['issue_id']));
				$select->where('users_id = ?', $identity->id);
				$tag_time_update = new Application_Model_TagTime(null, $tag_time->fetchRow($select));
				$tag_time_update->update(array('tag_time' => $for_db['tag_time'], 'entrie_id' => addslashes($params['issue_id']), 'users_id' => $identity->id));
				if (!$ok)
					echo "ok";
			}
		} else {
			echo "error";
		}
	}

}

