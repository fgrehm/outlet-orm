<?php

class OutletPDO extends PDO {
	private $driver;

	function __construct ($dsn, $user=null, $pass=null) {
		// store the driver
		$this->driver = substr($dsn, 0, strpos($dsn, ':'));		

		parent::__construct($dsn, $user, $pass);
	}

	function lastInsertId($name=null) {
		if ($this->driver == 'mssql') {
			return $this->query('SELECT SCOPE_IDENTITY() as id')->fetchColumn('id');
		} else {
			return parent::lastInsertId();
		}
	}
}


