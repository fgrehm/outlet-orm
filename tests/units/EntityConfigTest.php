<?php

class Unit_EntityConfigTest extends OutletTestCase {
	private $entityName = 'Testing';

	public function testRequireTableName() {
		try {
			$config = $this->_createConfig();
			$this->fail("should've raised an exception");
		} catch (OutletConfigException $ex) { $this->assertTrue(true);}
	}

	public function testRequireProperties() {
		try {
			$config = $this->_createConfig('table');
			$this->fail("should've raised an exception");
		} catch (OutletConfigException $ex) { $this->assertTrue(true);}
	}

	public function testRequireAtLeastOnePrimaryKey() {
		try {
			$config = $this->_createConfig('table', array('test' => array('test', 'int', array())));
			$this->fail("should've raised an exception");
		} catch (OutletConfigException $ex) { $this->assertTrue(true);}
	}

	public function testCanGetTableName() {
		$config = $this->_createConfig('table', array('test' => array('test', 'int', array('pk' => true))));
		$this->assertEquals('table', $config->getTable());
	}

	public function testCanGetProperties() {
		$props = array('test' => array('test', 'int', array('pk' => true)));
		$config = $this->_createConfig('table', $props);
		
		$this->assertEquals(1, count($config->getProperties()));
	}

	public function testCanGetPropertyConfig() {
		$propConf1 = array('test', 'int', array('pk' => true));
		$propConf2 = array('test2', 'int');
		$props = array('test' => $propConf1, 'test2' => $propConf2);
		$config = $this->_createConfig('table', $props);

		$this->assertThat($config->getProperty('test'), $this->isInstanceOf('OutletPropertyConfig'));
		$this->assertThat($config->getProperty('test2'), $this->isInstanceOf('OutletPropertyConfig'));
	}

	public function testRaisesExceptionIfPropertyNotFound() {
		$config = $this->_createConfig('table', array('test' => array('test', 'int', array('pk' => true))));

		try {
			$this->assertEquals($propConf1, $config->getProperty('test2'));
			$this->fail("should've raised an exception");
		} catch (OutletConfigException $ex) {$this->assertTrue(true);}
	}

	public function testAllowToSuppresExceptionIfPropertyNotFound() {
		$config = $this->_createConfig('table', array('test' => array('test', 'int', array('pk' => true))));
		$this->assertNull($config->getProperty('test2', false));
	}

	public function testCanGetClassName() {
		$config = $this->_createConfig('table', array('test' => array('test', 'int', array('pk' => true))));
		$this->assertEquals($this->entityName, $config->getClass());
	}

	public function testCanGetSinglePKPropety() {
		$props = array('test' => array('test_col', 'int', array('pk' => true)), 'test2' => array('test2', 'varchar', array('pk' => false)));
		$config = $this->_createConfig('table', $props);
		$pks = $config->getPkProperties();

		$this->assertThat($pks, $this->isType('array'));
		$this->assertEquals(1, count($pks));
	}
	
	protected function _createConfig($tableName = null, $properties = null, $subclasses = null, $discriminator = null, $discriminatorValue) {
		$config = array(
			'connection' => array(
				'pdo' => $this->getSQLiteInMemoryPDOConnection(),
				'dialect' => 'sqlite'
			),
			'classes' => array(
				$this->entityName => array()
			)
		);
		if ($tableName !== null)
			$config['classes'][$this->entityName]['table'] = $tableName;
		if ($properties !== null)
			$config['classes'][$this->entityName]['props'] = $properties;
		if ($subclasses !== null)
			$config['classes'][$this->entityName]['subclasses'] = $subclasses;
		if ($discriminator !== null)
			$config['classes'][$this->entityName]['discriminator'] = $discriminator;
		if ($discriminatorValue !== null)
			$config['classes'][$this->entityName]['discriminator-value'] = $discriminatorValue;
		$config = new OutletConfig($config);
		return $config->getEntity($this->entityName);
	}
}