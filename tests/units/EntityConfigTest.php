<?php

use outlet\Config;
use outlet\ConfigException;
use outlet\EntityConfig;
use outlet\PropertyConfig;

class Unit_EntityConfigTest extends OutletTestCase {
	private $entityName = 'Testing';

	public function testRequireTableName() {
		try {
			$config = $this->_createConfig();
			$this->fail("should've raised an exception");
		} catch (ConfigException $ex) { $this->assertTrue(true);}
	}

	public function testRequireProperties() {
		try {
			$config = $this->_createConfig('table');
			$this->fail("should've raised an exception");
		} catch (ConfigException $ex) { $this->assertTrue(true);}
	}

	public function testRequireAtLeastOnePrimaryKey() {
		try {
			$config = $this->_createConfig('table', array('test' => array('test', 'int', array())));
			$this->fail("should've raised an exception");
		} catch (ConfigException $ex) { $this->assertTrue(true);}
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

		$this->assertThat($config->getProperty('test'), $this->isInstanceOf('outlet\PropertyConfig'));
		$this->assertThat($config->getProperty('test2'), $this->isInstanceOf('outlet\PropertyConfig'));
	}

	public function testRaisesExceptionIfPropertyNotFound() {
		$config = $this->_createConfig('table', array('test' => array('test', 'int', array('pk' => true))));

		try {
			$config->getProperty('test2');
			$this->fail("should've raised an exception");
		} catch (ConfigException $ex) {$this->assertTrue(true);}
	}

	public function testAllowToSuppresExceptionIfPropertyNotFound() {
		$config = $this->_createConfig('table', array('test' => array('test', 'int', array('pk' => true))));
		$this->assertNull($config->getProperty('test2', false));
	}

	public function testCanGetEntityClass() {
		$config = $this->_createConfig('table', array('test' => array('test', 'int', array('pk' => true))));
		$this->assertEquals($this->entityName, $config->getEntityClass());
	}

	public function testCanGetQualifiedEntityClass() {
		$className = 'namespace\SomeEntity';
		$config = $this->_createConfig('table', array('test' => array('test', 'int', array('pk' => true))), null, null, null, null, $className);
		$this->assertEquals($className, $config->getQualifiedEntityClass());
	}

	public function testCanGetSinglePKPropety() {
		$props = array('test' => array('test_col', 'int', array('pk' => true)), 'test2' => array('test2', 'varchar', array('pk' => false)));
		$config = $this->_createConfig('table', $props);
		$pks = $config->getPkProperties();

		$this->assertThat($pks, $this->isType('array'));
		$this->assertEquals(1, count($pks));
	}

	public function testCanGetAlias() {
		$alias = 'EntityAlias';
		$config = $this->_createConfig('table', array('test' => array('test', 'int', array('pk' => true))), null, null, null, $alias);
		$this->assertEquals('EntityAlias', $config->getAlias());
	}

	public function testAliasDefaultsToClassName() {
		$qualifiedClassName = 'namespace\otherNamespace\SomeEntity';
		$config = $this->_createConfig('table', array('test' => array('test', 'int', array('pk' => true))), null, null, null, null, $qualifiedClassName);
		$this->assertEquals('SomeEntity', $config->getAlias());
	}

	public function testCanGetProxyClass() {
		$className = 'namespace\SomeEntity';
		$config = $this->_createConfig('table', array('test' => array('test', 'int', array('pk' => true))), null, null, null, null, $className);
		$this->assertEquals('SomeEntity_OutletProxy', $config->getProxyClass());
	}

	public function testCanGetQualifiedProxyClass() {
		$className = 'namespace\SomeEntity';
		$config = $this->_createConfig('table', array('test' => array('test', 'int', array('pk' => true))), null, null, null, null, $className);
		$this->assertEquals($className.'_OutletProxy', $config->getQualifiedProxyClass());
	}

	public function testCanGetNamespace() {
		$className = 'namespace\test\SomeEntity';
		$config = $this->_createConfig('table', array('test' => array('test', 'int', array('pk' => true))), null, null, null, null, $className);
		$this->assertEquals('namespace\test\\', $config->getNamespace());
	}
	
	protected function _createConfig($tableName = null, $properties = null,
				$subclasses = null, $discriminator = null,
				$discriminatorValue = null, $alias = null,
				$entityClass = null) {
		$entityClass = ($entityClass === null) ? $this->entityName : $entityClass;

		$config = array(
			'connection' => array(
				'pdo' => $this->getSQLiteInMemoryPDOConnection(),
				'dialect' => 'sqlite'
			),
			'classes' => array(
				$entityClass => array()
			)
		);
		if ($tableName !== null)
			$config['classes'][$entityClass]['table'] = $tableName;
		if ($properties !== null)
			$config['classes'][$entityClass]['props'] = $properties;
		if ($subclasses !== null)
			$config['classes'][$entityClass]['subclasses'] = $subclasses;
		if ($discriminator !== null)
			$config['classes'][$entityClass]['discriminator'] = $discriminator;
		if ($discriminatorValue !== null)
			$config['classes'][$entityClass]['discriminator-value'] = $discriminatorValue;
		if ($alias !== null)
			$config['classes'][$entityClass]['alias'] = $alias;
		$config = new Config($config);
		return $config->getEntity($entityClass);
	}
}