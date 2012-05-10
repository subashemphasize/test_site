<?php


class Am_Exception extends Exception
{
    protected $logError = true;
    function getLogError()
    {
        return $this->logError;
    }
    function setLogError($logError)
    {
        $this->logError = (bool)$logError;
    }
    /**
     * Return a message to be displayed to visitors if no AM_DEBUG enabled
     * @return string
     */
    function getPublicError(){
        return ___('An internal error happened in the script, please contact webmaster for details');
    }
}
class Am_Exception_InternalError extends Am_Exception {}
class Am_Exception_Security extends Am_Exception {}
class Am_Exception_NotImplemented extends Am_Exception {}
class Am_Exception_InputError extends Am_Exception {
    public function getPublicError() { return $this->getMessage(); }
}
class Am_Exception_AccessDenied extends Am_Exception {
    public function getPublicError() { return $this->getMessage(); }
}
class Am_Exception_Configuration extends Am_Exception {
    protected $logError = true;
    public function getPublicError() { return "There is a configuration error in the membership sofware, please contact site webmaster to fix it"; }
}
class Am_Exception_Db extends Am_Exception {
    protected $dbMessage;
    public function getPublicError() {
          return ___('The database has encountered a problem, please try again later.');  }
    public function setDbMessage($err){  $this->dbMessage = $err; }
    public function getDbMessage(){ return $this->dbMessage; }
}
class Am_Exception_Db_NotFound extends Am_Exception_Db {}
class Am_Exception_Db_NotUnique extends Am_Exception_Db {
    protected $_table = null;
    public function setTable($table) { $this->_table = $table; }
    public function getTable() { return $this->_table; }
}
class Am_Exception_FatalError extends Am_Exception {
    public function getPublicError() { return $this->getMessage(); }
}
/** Error triggered by the integration plugin db call */
class Am_Exception_PluginDb extends Am_Exception_Db { }
class Am_Exception_Redirect extends Am_Exception {
    protected $logError = false;
}