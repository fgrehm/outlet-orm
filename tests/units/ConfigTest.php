<?php

class Unit_ConfigTest extends OutletTestCase {
	public function testRequireConnectionConfig() {
		try {
			$config = new OutletConfig(array());
		} catch (OutletConfigException $ex) { $this->assertTrue(true); return; }
		$this->fail("should've raised an exception");
	}

	public function testRequireConnectionDsnOrPdo() {
		try {
			$config = new OutletConfig(array('connection' => 1));
		} catch (OutletConfigException $ex) { $this->assertTrue(true); return; }
		$this->fail("should've raised an exception");
	}

	public function testRequireDialect() {
		// TODO: this test looks ugly
		try {
			$config = new OutletConfig(array('connection' => array('dsn' => $this->getSQLiteInMemoryDSN())));
		} catch (OutletException $ex) {
			$this->assertTrue(true);
			try {
				$config = new OutletConfig(array('connection' => array('pdo' => 1)));
			} catch (OutletConfigException $ex) { $this->assertTrue(true); return; }
		}
		$this->fail("should've raised an exception");
	}

	public function testRequireClassesMapping() {
		try {
			$config = new OutletConfig(array('connection' => array('dsn' => $this->getSQLiteInMemoryDSN(), 'dialect' => 'mysql')));
		} catch (OutletConfigException $ex) { $this->assertTrue(true); return;}
		$this->fail("should've raised an exception");
	}

	public function testCanGetConnection() {
		$config = new OutletConfig(array('connection' => array('dsn' => $this->getSQLiteInMemoryDSN(), 'dialect' => 'sqlite'), 'classes' => array()));
		$this->assertThat($config->getConnection(), $this->isInstanceOf('OutletConnection'));

		$config = new OutletConfig(array('connection' => array('pdo' => $this->getSQLiteInMemoryPDOConnection(), 'dialect' => 'sqlite'), 'classes' => array()));
		$this->assertThat($config->getConnection(), $this->isInstanceOf('OutletConnection'));
	}

	public function testRaisesExceptionIfEntityNotFound() {
		$config = new OutletConfig(array('connection' => array('pdo' => $this->getSQLiteInMemoryPDOConnection(), 'dialect' => 'sqlite'), 'classes' => array('Testing' => array('table' => 'testing', 'props' => array('id' => array('id', 'int', array('pk' => true)))))));
		try {
			$this->assertThat($config->getEntity('Testing2'), $this->isInstanceOf('OutletEntityConfig'));
		} catch (OutletException $ex) { $this->assertTrue(true); return; }
		$this->fail("should've raised an exception");
	}

	public function testAllowToSuppresExceptionIfEntityNotFound() {
		$config = new OutletConfig(array('connection' => array('pdo' => $this->getSQLiteInMemoryPDOConnection(), 'dialect' => 'sqlite'), 'classes' => array('Testing' => array('table' => 'testing', 'props' => array('id' => array('id', 'int', array('pk' => true)))))));
		$this->assertNull($config->getEntity('Testing2', false));
	}

	public function testGettersAndSettersAreDisabledByDefault() {
		$config = $this->_createConfig();
		$this->assertFalse($config->useGettersAndSetters());
		$this->assertFalse($config->getEntity('ConfigEntity')->useGettersAndSetters());
	}

	public function testSetGettersAndSettersUsageGlobal() {
		$config = $this->_createConfig(true);
		$this->assertTrue($config->useGettersAndSetters());
		$this->assertTrue($config->getEntity('ConfigEntity')->useGettersAndSetters());
	}

	public function testSetGettersAndSettersUsagePerEntity() {
		$config = $this->_createConfig(true, false);
		$this->assertFalse($config->getEntity('ConfigEntity')->useGettersAndSetters());

		$config = $this->_createConfig(false, true);
		$this->assertTrue($config->getEntity('ConfigEntity')->useGettersAndSetters());
	}

	public function testCanGetEntityConfig() {
		$config = new OutletConfig(array('connection' => array('pdo' => $this->getSQLiteInMemoryPDOConnection(), 'dialect' => 'sqlite'), 'classes' => array('Testing' => array('table' => 'testing', 'props' => array('id' => array('id', 'int', array('pk' => true)))))));
		$this->assertThat($config->getEntity('Testing'), $this->isInstanceOf('OutletEntityConfig'));
	}

	public function testGettingEntityConfigByObject() {
		$config = $this->_createConfig();
		$this->assertThat($config->getEntity(new ConfigEntity()), $this->isInstanceOf('OutletEntityConfig'));
	}

	public function testGettingEntityConfigByProxy() {
		$config = $this->_createConfig();
		$this->assertThat($config->getEntity(new ConfigEntity_OutletProxy()), $this->isInstanceOf('OutletEntityConfig'));
	}

	protected function _createConfig($globalUseGettersAndSetters = null, $entityUseGettersAndSetters = null) {
		$config = array(
			'connection' => array(
				'pdo' => $this->getSQLiteInMemoryPDOConnection(),
				'dialect' => 'sqlite'
			),
			'classes' => array(
				'ConfigEntity' => array(
					'table' => 'testing',
					'props' => array('id' => array('id', 'int', array('pk' => true)))
				)
			)
		);
		if ($globalUseGettersAndSetters !== null)
			$config['useGettersAndSetters'] = $globalUseGettersAndSetters;
		if ($entityUseGettersAndSetters !== null)
			$config['classes']['ConfigEntity']['useGettersAndSetters'] = $entityUseGettersAndSetters;
		return new OutletConfig($config);
	}
}

class ConfigEntity {
	public $id;
}

class ConfigEntity_OutletProxy extends ConfigEntity implements OutletProxy {}