<?php

class OutletPDO extends PDO {
	private $driver;
	private $dialect;

	protected $nestedTransactionLevel = 0;

	function __construct ($dsn, $user=null, $pass=null) {
		// store the driver
		$this->driver = substr($dsn, 0, strpos($dsn, ':'));		

		parent::__construct($dsn, $user, $pass);
	}	

	function setDialect ($dialect) {
		$this->dialect = $dialect;
	}
	function getDialect () {
		return $this->dialect;
	}

	function beginTransaction () {
		if (!$this->nestedTransactionLevel++) {
			return parent::beginTransaction();
		}
		return true;
	}

	function commit () {
		if (!--$this->nestedTransactionLevel) {
			return parent::commit();
		}
		return true;
	}
	
	function rollBack () {
		if (!--$this->nestedTransactionLevel) {
			return parent::rollBack();
		}
		return true;
	}
}


