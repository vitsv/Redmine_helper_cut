<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of User
 *
 * @author araneo
 */
class Application_Model_TimeEntries extends External_Model {

	public function __construct($id = null) {
		parent::__construct(new Application_Model_DbTable_TimeEntries(), $id);
	}

	public function getById($id) {
		if ($id) {
			return $this->_dbTable->fetchRow($this->_dbTable->select()->where('id = ?', $id));
		}
	}

	public function getAllRows($params = array()) {
		$select = $this->prepareFetchAllSelect($params);
		return $this->_dbTable->fetchAll($select);
	}

	public function getPaginatorRows($pageNumber = 1, $params = array()) {
		$select = $this->prepareFetchAllSelect($params);
		$paginator = new Zend_Paginator(new Zend_Paginator_Adapter_DbSelect($select));
		$paginator->setCurrentPageNumber($pageNumber);
		$paginator->setItemCountPerPage(50);
		$paginator->setPageRange(10);
		return $paginator;
	}

	public function update($time_etrie, $issues_detal_id = null) {
		if ($time_etrie) {
			$this->id = $time_etrie->id;
			$this->issue_id = $time_etrie->issue->attributes()->id;
			$this->user_name = $time_etrie->user->attributes()->name;
			$this->user_id = $time_etrie->user->attributes()->id;
			$this->activity_name = $time_etrie->activity->attributes()->name;
			$this->activity_id = $time_etrie->activity->attributes()->id;
			$this->hours = $time_etrie->hours;
			if ($time_etrie->comments != "")
				$this->comments = strip_tags($time_etrie->comments);
			$this->spent_on = $time_etrie->spent_on;
			$this->created_on = $time_etrie->created_on;
			$this->updated_on = $time_etrie->updated_on;
			if ($issues_detal_id != null) {
				$this->issues_detal_id = $issues_detal_id;
			}
			$this->save();
		}
	}

	/**
	 * Przygotowywuje selecta na potrzeby paginatora
	 *
	 * @param   array   $params     lista parametrów wyszukiwania
	 * @return  Zend_Db_Table_Select
	 */
	private function prepareFetchAllSelect($params) {
		//czy trzeba posumowac czas
		$sumowac = 0;

		// Tworzymy selecta
		$select = $this->_dbTable->select();
		$select->setIntegrityCheck(false);
		//koloumny z tabeli 'issues_detals'
		$join_colums = array('subject', 'status_name', 'project_name', 'issue_id', 'description', 'author_name', 'priority_name', 'assigned_to_name', 'estimated_hours', 'declared_time', 'resumed_by_araneo', 'resumed_by_klient', 'spent_hours', 'category');
		// ustawiamy domyślny order/sort
		if (isset($params ['order_by'])) {
			$order = (isset($params ['order']) and $params ['order'] == 'DESC') ? 'DESC' : 'ASC';
			if (in_array($params ['order_by'], $this->_dbTable->info(Zend_Db_Table::COLS)) || in_array($params ['order_by'], $join_colums))
				$select->order(addslashes($params ['order_by']) . ' ' . $order);
		} else {
			$select->order('spent_on DESC');
		}
		foreach ($params as $key => $value) {
			switch ($key) {

				case 'project' :
					if ($value)
						$select->where('project_id IN (?)', $value);
					break;

				case 'username' :
					if ($value)
						$select->where('user_id IN(?)', $value);
					break;

				case 'issue' :
					if ($value)
						$select->where('issue_id = ?', $value);
					break;

				case 'spent_on_from' :
					if ($value) {
						$select->where('CAST(spent_on AS DATE ) >= ?', $value);
					}
					break;
				case 'spent_on_to' :
					if ($value) {
						$select->where('CAST(spent_on AS DATE ) <= ?', $value);
					}
					break;
				case 'estimated_filled' :
					if ($value) {
						$select->where('estimated_hours != ?', 0);
					}
					break;
				case 'declared_filled' :
					if ($value) {
						$select->where('declared_time != ?', 0);
					}
					break;
				case 'period' :
					if ($value == '2') {
						$select->where('CAST(spent_on AS DATE ) >= ?', date('Y-m-d', mktime(0, 0, 0, date("m") - 1, date("d"), date("Y"))));
					} elseif ($value == '3') {
						$select->where('CAST(spent_on AS DATE ) >= ?', date('Y-m-d', mktime(0, 0, 0, date("m") - 2, date("d"), date("Y"))));
					}
					break;

				case 'activity_id':
					if ($value)
						$select->where('activity_id IN (?)', $value);
					break;
				case 'group_by_day':
					if ($value) {
						$select->group('spent_on');
						$sumowac = 1;
						$select_colums = array('spent_on');
					}
					break;
				case 'group_by_tickiet':
					if ($value) {
						$select->group('issue_id');
						$sumowac = 2;
						$join = 1;
						$select_colums = array();
					}
					break;
				case 'group_by_user':
					if ($value) {
						$select->group('user_name');
						$sumowac = 3;
						if (isset($select_colums))
							$select_colums = array_merge($select_colums, array('user_name'));
						else
							$select_colums = array('user_name');
					}
					break;
				case 'status_id':
					if ($value)
						$select->where('status_id IN (?)', $value);
					break;
				case 'category':
					if ($value)
						$select->where('category IN (?)', $value);
					break;

				case 'order' :
					// Order uwzględniamy gdzie indziej więc tutaj ignorujemy ten parametr
					continue;
					break;

				default :
					// Domyślne filtrowanie - oszczędza sporo pisania
					// Jeśli nazwa pola ma swój odpowiednik w kolumnach tabeli bazowej (posts)
					// to filtrujemy po tej kolumnie. Jeśli nie to nic się nie stanie
					//if (in_array($key, $this->info(Zend_Db_Table::COLS))) {
					//    $select->where($key . ' LIKE ?', '%' . $value . '%');
					//}
					break;
			}
		}

		if ($sumowac) {
			$select_colums = array_merge($select_colums, array('id', 'comments' => 'GROUP_CONCAT(comments SEPARATOR \' | \')', 'hours' => 'SUM(hours)'));
			$select->from(array('t' => 'time_entries'), $select_colums);
			if (isset($join)) {
				$select->joinLeft(array('i' => 'issues_detal'),
						't.issues_detal_id = i.issues_detal_id', $join_colums);
				$auth = Zend_Auth::getInstance();
				$select->joinLeft(array('tt' => 'tag_time'),
						'i.issue_id = tt.entrie_id AND tt.users_id = ' . $auth->getIdentity()->id);
			}
		} else {
			$select->from(array('t' => 'time_entries'))
					->joinLeft(array('i' => 'issues_detal'),
							't.issues_detal_id = i.issues_detal_id', $join_colums);
			$auth = Zend_Auth::getInstance();
			$select->joinLeft(array('tt' => 'tag_time'),
					'i.issue_id = tt.entrie_id AND tt.users_id = ' . $auth->getIdentity()->id);
		}
		return $select;
	}

	/**
	 * Wyczyszcza bazę. Kasuje wpisy przepracowanego czasy, starsze 3 miesięcy,
	 * oraz wszystkie wpisy zagadnień które nie są związane z pozostaływmi wpisami przepracowanego czasu
	 */
	public function clear(){
		$where = $this->_dbTable->getAdapter()->quoteInto('CAST(spent_on AS DATE ) < ?', date('Y-m-d', mktime(0, 0, 0, date("m") - 2, 1, date("Y"))));
		$this->_dbTable->delete($where);
		$sql = 'DELETE FROM issues_detal USING issues_detal LEFT JOIN time_entries ON issues_detal.issues_detal_id = time_entries.issues_detal_id
WHERE time_entries.issues_detal_id IS NULL';
		$stmt = $this->_dbTable->getAdapter()->query($sql);
		$stmt->execute();
	}

	/**
	 * Generuje prosty raport. Ile czasu spędzono i ile wznowień
	 * Można to rozbudować do generowania bardziej zaawansowanych raportów z wielu warunkami i td.
	 *
	 * @param <type> $users
	 * @return <type>
	 */
	public function generateRaport($users){
		if(!empty($users)){
		$select = $this->_dbTable->select();
		$select->setIntegrityCheck(false);
		$select->from(array('t' => 'time_entries'), array('user_name', 'hours' => 'SUM(hours)'));
		$select->where('t.user_id IN(?)', $users);
		$in_users = implode(',',$users);
		$select->joinLeft(array('i' => 'issues_detal'),
						"t.issues_detal_id = i.issues_detal_id AND i.assigned_to_id IN ({$in_users})", array('by_araneo' => 'SUM(resumed_by_araneo)', 'by_klient' => 'SUM(resumed_by_klient)'));
		$select->group('user_name');
		$raport =  $this->_dbTable->fetchAll($select);
		return $raport;
		}
	}

}

?>
