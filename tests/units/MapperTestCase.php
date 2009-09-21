<?php

abstract class Unit_MapperTestCase extends OutletTestCase {
	protected $entityName;
	protected $useGettersAndSetters;
	protected $entity;
	protected $mapper;

	public function testCanGetAndSetValue() {
		$this->mapper->set($this->entity, 'name', 'new name');

		$this->assertEquals('new name', $this->mapper->get($this->entity, 'name'));
	}

	public function testCasting() {
		$this->mapper
			->set($this->entity, 'id', '5')
			->set($this->entity, 'name', 'some name')
			->set($this->entity, 'birthdate', '20090101000000')
			->set($this->entity, 'lastvisit', '20090101000001')
			->set($this->entity, 'children', '5')
			->set($this->entity, 'weight', '1.5');

		$this->assertThat($this->mapper->get($this->entity, 'id'), $this->isType('int'));
		$this->assertThat($this->mapper->get($this->entity, 'children'), $this->isType('int'));
		$this->assertThat($this->mapper->get($this->entity, 'weight'), $this->isType('float'));
		$this->assertThat($this->mapper->get($this->entity, 'birthdate'), $this->isInstanceOf('DateTime'));
		$this->assertThat($this->mapper->get($this->entity, 'lastvisit'), $this->isInstanceOf('DateTime'));
	}

	public function testDate() {
		$this->mapper->set($this->entity, 'birthdate', '20091231');

		$this->assertEquals(2009, $this->mapper->get($this->entity, 'birthdate')->format('Y'));
		$this->assertEquals(12, $this->mapper->get($this->entity, 'birthdate')->format('m'));
		$this->assertEquals(31, $this->mapper->get($this->entity, 'birthdate')->format('d'));
		$this->assertEquals(0, $this->mapper->get($this->entity, 'birthdate')->format('H'));
		$this->assertEquals(0, $this->mapper->get($this->entity, 'birthdate')->format('i'));
		$this->assertEquals(0, $this->mapper->get($this->entity, 'birthdate')->format('s'));
	}

	public function testDateTime() {
		$this->mapper->set($this->entity, 'lastvisit', '20080229235959');

		$this->assertEquals(2008, $this->mapper->get($this->entity, 'lastvisit')->format('Y'));
		$this->assertEquals(2, $this->mapper->get($this->entity, 'lastvisit')->format('m'));
		$this->assertEquals(29, $this->mapper->get($this->entity, 'lastvisit')->format('d'));
		$this->assertEquals(23, $this->mapper->get($this->entity, 'lastvisit')->format('H'));
		$this->assertEquals(59, $this->mapper->get($this->entity, 'lastvisit')->format('i'));
		$this->assertEquals(59, $this->mapper->get($this->entity, 'lastvisit')->format('s'));
	}

	public function testCanSetAndRetrievePK() {
		$this->mapper->setPKs($this->entity, array('id' => 1));
		$this->assertEquals(array('id' => 1), $this->mapper->getPKs($this->entity));
	}

	public function testSetArray() {
		$values = array(
			'id' => 5,
			'name' => 'some name',
			'children' => 6
		);
		$this->mapper->set($this->entity, $values);

		$this->assertEquals(5, $this->mapper->get($this->entity, 'id'));
		$this->assertEquals(6, $this->mapper->get($this->entity, 'children'));
		$this->assertEquals('some name', $this->mapper->get($this->entity, 'name'));
	}

	public function testGetValues() {
		$values = array(
			'id' => 5,
			'name' => 'some name',
			'children' => 6,
			'lastvisit' => null,
			'birthdate' => null,
			'weight' => null
		);
		$this->mapper->set($this->entity, $values);

		$this->assertEquals($values, $this->mapper->getValues($this->entity));
	}

	public function testGetDirtyValues() {
		$newValues = $originalValues = array(
			'id' => 5,
			'name' => 'some name',
			'children' => 6,
			'lastvisit' => null,
			'birthdate' => null,
			'weight' => null
		);
		$dirtyValues = array(
			'name' => 'some name',
			'children' => 6
		);
		$this->mapper->set($this->entity, $newValues);
		$originalValues['name'] = 'old name';
		$originalValues['children'] = 9;

		$this->assertEquals($dirtyValues, $this->mapper->getDirtyValues($this->entity, $originalValues));
	}

	public function setUp() {
		$classes = array(
			$this->entityName => array(
				'table' => 'testing',
				'props' => array(
					'id' => array('id', 'int', array('pk' => true)),
					'name' => array('name', 'varchar'),
					'birthdate' => array('bd', 'date'),
					'lastvisit' => array('lastvisit', 'datetime'),
					'children' => array('children_count', 'int'),
					'weight' => array('weight', 'float')
				),
				'useGettersAndSetters' => $this->useGettersAndSetters
			)
		);
		$session = $this->openSession($classes);

		$this->entity = new $this->entityName();
		$this->mapper = $session->getMapperFor($this->entityName);
	}
}