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
class Application_Model_User extends External_Model {

    public function __construct($id = null) {
        parent::__construct(new Application_Model_DbTable_Users(), $id);
    }

    public function populateForm() {
        if ($this->_row)
            return $this->_row->toArray();
        else
            return null;
    }

    public function getAllUsers($select = null) {
        return $this->_dbTable->fetchAll($select);
    }

    public function sendMail() {
        $mail = new External_Mail();
        $mail->addTo($this->email);
        $mail->setSubject('Rejestracja na Redmine Helper');
        $mail->setBodyView('new', array('user' => $this));
        $mail->send();
    }

    public function authorize($username, $password) {
        $auth = Zend_Auth::getInstance();
        $authAdapter = new Zend_Auth_Adapter_DbTable(
                        Zend_Db_Table::getDefaultAdapter(),
                        'users',
                        'username',
                        'password',
                        'sha(?) and active = 1'
        );
        $authAdapter->setIdentity($username)
                ->setCredential($password);

        $result = $auth->authenticate($authAdapter);
        if ($result->isValid()) {
            $storage = $auth->getStorage();
            $storage->write($authAdapter->getResultRowObject(null, array('password')));
            return true;
        } else {
            return false;
        }
    }

    /**
     * Zlicza nieaktywnych użytkowników
     */
    public function countUnactive() {
        // Tworzymy selecta
        $select = $this->_dbTable->select();
        $select->from($this->_dbTable, array('COUNT(*) as amount'));
        $select->where('active = ?',0);
        $rows = $this->_dbTable->fetchAll($select);
        return $rows[0]->amount;

    }

}

?>
