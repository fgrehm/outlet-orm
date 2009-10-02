<?php
error_reporting(E_STRICT);
date_default_timezone_set("America/Sao_Paulo");

abstract class OutletTestCase extends PHPUnit_Framework_TestCase {
	protected function getSQLiteInMemoryDSN() {
//		return 'sqlite:'.realpath(dirname(__FILE__)).'/test-db.sqlite';
		return 'sqlite::memory:';
	}

	protected function getSQLiteInMemoryPDOConnection() {
		return new PDO($this->getSQLiteInMemoryDSN());
	}

	protected function createConfigArray($classesArray) {
		return array(
			'connection' => array(
				'pdo' => $this->getSQLiteInMemoryPDOConnection(),
				'dialect' => 'sqlite'
			),
			'classes' => $classesArray
		);
	}

	protected function createConfig($classesArray) {
		return new OutletConfig($this->createConfigArray($classesArray));
	}

	protected function openSession($configClassesArray) {
		return Outlet::openSession($this->createConfig($configClassesArray));
	}
}