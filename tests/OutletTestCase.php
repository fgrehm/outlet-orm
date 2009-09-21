<?php
//error_reporting(E_STRICT);
require_once 'PHPUnit/Framework.php';

abstract class OutletTestCase extends PHPUnit_Framework_TestCase {
	protected function getSQLiteInMemoryDSN() {
//		return 'sqlite:'.realpath(dirname(__FILE__)).'/test-db.sqlite';
		return 'sqlite::memory:';
	}

	protected function getSQLiteInMemoryPDOConnection() {
		return new PDO($this->getSQLiteInMemoryDSN());
	}

	protected function createConfig($classesArray) {
		$config = array(
			'connection' => array(
				'pdo' => $this->getSQLiteInMemoryPDOConnection(),
				'dialect' => 'sqlite'
			),
			'classes' => $classesArray
		);
		return new OutletConfig($config);
	}

	protected function openSession($configClassesArray) {
		return Outlet::openSession($this->createConfig($configClassesArray));
	}
}