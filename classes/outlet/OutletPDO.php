<?php

class OutletPDO extends PDO {
	private $driver;

	function __construct ($dsn, $user=null, $pass=null) {
		// store the driver
		$this->driver = substr($dsn, 0, strpos($dsn, ':'));		

		parent::__construct($dsn, $user, $pass);
	}

	
}


