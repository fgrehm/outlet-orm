<?php

use outlet\Outlet;

abstract class OutletTestCase extends PHPUnit_Framework_TestCase {
	protected function getSQLiteInMemoryDSN() {
		return 'sqlite::memory:';
	}

	protected function getSQLiteInMemoryPDOConnection() {
		return new PDO($this->getSQLiteInMemoryDSN());
	}

	protected function createConfigArray($classesArray, $pdo = null) {
		if ($pdo == null)
			$pdo = $this->getSQLiteInMemoryPDOConnection();
		return array(
			'connection' => array(
				'pdo' => $pdo,
				'dialect' => 'sqlite'
			),
			'classes' => $classesArray
		);
	}

	protected function createConfig($classesArray) {
		return new OutletConfig($this->createConfigArray($classesArray));
	}

	protected function openSession($configClassesArray, $enableAutoload = false) {
		if (!$enableAutoload)
			return Outlet::openSession($this->createConfig($configClassesArray));
		else {
			$config = $this->createConfigArray($configClassesArray);
			$config['proxies'] = array(
				'autoload' => true
			);

			return Outlet::openSession(new OutletConfig($config));
		}
	}
}