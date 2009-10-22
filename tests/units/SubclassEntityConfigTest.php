<?php

class Unit_SubclassEntityConfigTest extends OutletTestCase {
	private $entityName = 'Testing';
	/**
	 * @var OutletConfig
	 */
	private $config;

	public function testRequireSuperclassDiscriminator() {
		try {
			$config = $this->_createConfig('table', array('test' => array('test_col', 'int', array('pk'=>true))), array('Subclass'=>array()));
			$this->fail("should've raised an exception");
		} catch (OutletConfigException $ex) { $this->assertTrue(true);}
	}

	public function testCanGetDiscriminatorColumn() {
		$config = $this->_createConfig('table', array('test' => array('test_col', 'int', array('pk'=>true))), array('Subclass'=>array('discriminator-value'=>'subclass', 'props'=>array())), array('type','varchar'), 'superclass');
		$this->assertNotNull($config->getDiscriminator());
	}

	public function testRequireDiscriminatorValueOnSuperclass() {
		try {
			$config = $this->_createConfig('table', array('test' => array('test_col', 'int', array('pk'=>true))), array('Subclass'=>array()), array('type','varchar'));
			$this->fail("should've raised an exception");
		} catch (OutletConfigException $ex) { $this->assertTrue(true);}
	}
	
	public function testCanGetDiscriminatorValue() {
		$config = $this->_createConfig('table', array('test' => array('test_col', 'int', array('pk'=>true))), array('Subclass'=>array('discriminator-value'=>'subclass','props'=>array())), array('type','varchar'),'superclass');
		$this->assertEquals('superclass', $config->getDiscriminatorValue());
	}	

	public function testCanGetSubclassDiscriminatorValue() {
		$this->_createConfig('table', array('test' => array('test_col', 'int', array('pk'=>true))), array('Subclass'=>array('discriminator-value'=>'subclass','props'=>array())), array('type','varchar'),'superclass');
		$en = $this->config->getEntity("Subclass");
		$this->assertEquals('subclass', $en->getDiscriminatorValue());
	}	
	
	public function testRequireDiscriminatorValueOnSubclass() {
		try {
			$config = $this->_createConfig('table', array('test' => array('test_col', 'int', array('pk'=>true))), array('Subclass'=>array()), array('type','varchar'), 'superclass');
			$this->fail("should've raised an exception");
		} catch (OutletConfigException $ex) { $this->assertTrue(true);}
	}
	
	public function testDontRequireTableNameAndPKIfSubclass() {
		$config = $this->_createConfig('table', array('test' => array('test_col', 'int', array('pk'=>true))), array('Subclass'=>array('discriminator-value'=>'subclass', 'props'=>array())), array('type','varchar'), 'superclass');
	}
	
	public function testGettingSuperclassAllPropertiesIncludeSubclassesProperties() {
		$config = $this->_createConfig('table', array('test' => array('test_col', 'int', array('pk'=>true))), array('Subclass'=>array('discriminator-value'=>'subclass', 'props'=>array('subclassProp'=>array('subclassProp_col', 'int')))), array('type','varchar'), 'superclass');
		$props = $config->getAllProperties();
		$this->assertEquals(3, count($props));
	}
	
	public function testGettingSubclassPropertiesShouldIncludeSuperclassProperties() {
		$this->_createConfig('table', array('test' => array('test_col', 'int', array('pk'=>true))), array('Subclass'=>array('discriminator-value'=>'subclass', 'props'=>array('subclassProp'=>array('subclassProp_col', 'int')))), array('type','varchar'), 'superclass');
		$en = $this->config->getEntity("Subclass");
		$props = $en->getProperties();
		$this->assertEquals(2, count($props));
	}

	public function testSubclassShouldUseSuperclassTable() {
		$this->_createConfig('table', array('test' => array('test_col', 'int', array('pk'=>true))), array('Subclass'=>array('discriminator-value'=>'subclass', 'props'=>array('subclassProp'=>array('subclassProp_col', 'int')))), array('type','varchar'), 'superclass');
		$en = $this->config->getEntity("Subclass");
		$this->assertEquals('table', $en->getTable());
	}
	
	public function testSubclassShouldUseSuperclassPKs() {
		$superClass = $this->_createConfig('table', array('test' => array('test_col', 'int', array('pk'=>true))), array('Subclass'=>array('discriminator-value'=>'subclass', 'props'=>array('subclassProp'=>array('subclassProp_col', 'int')))), array('type','varchar'), 'superclass');
		$en = $this->config->getEntity("Subclass");
		$this->assertEquals($superClass->getPkProperties(), $en->getPkProperties());
	}
	
	public function testGetPropertyShouldIncludeSubclassesProperties() {
		$superClass = $this->_createConfig('table', array('test' => array('test_col', 'int', array('pk'=>true))), array('Subclass'=>array('discriminator-value'=>'subclass', 'props'=>array('subclassProp'=>array('subclassProp_col', 'int')))), array('type','varchar'), 'superclass');
		$this->assertNotNull($superClass->getProperty("subclassProp"));
  	}

	public function testGetUpperMostClassName() {
		$superClass = $this->_createConfig('table', array('test' => array('test_col', 'int', array('pk'=>true))), array('Subclass'=>array('discriminator-value'=>'subclass', 'props'=>array('subclassProp'=>array('subclassProp_col', 'int')))), array('type','varchar'), 'superclass');
		$en = $this->config->getEntity("Subclass");
		$this->assertEquals($superClass->getClass(), $en->getSuperClass());
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
		$this->config = new OutletConfig($config);
		return $this->config->getEntity($this->entityName);
	}
}
