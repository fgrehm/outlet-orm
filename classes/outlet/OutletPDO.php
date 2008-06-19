<?php

class OutletPDO extends PDO {
	private $driver;
	private $dialect;

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
}


