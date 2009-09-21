<?php

class Unit_UnitOfWorkTest extends OutletTestCase {
	/**
	 *
	 * @var OutletUnitOfWork
	*/
	protected $uow;

	public function testInsert() {
		$entity = new UowEntity();
		$this->uow->save($entity);

		$this->repositoryMock
			->expects($this->once())
			->method('add')
			->with($entity);

		$this->uow->commit();
		$this->assertTrue(true);
	}

	public function testUpdate() {
		$entity = new UowEntity_OutletProxy();
		$this->uow->save($entity);

		$this->repositoryMock
			->expects($this->once())
			->method('update')
			->with($entity);
		
		$this->uow->commit();
		$this->assertTrue(true);
	}

	public function testMultipleCallsToUpdateShouldResultInOnlyOneOrder() {
		$entity = new UowEntity_OutletProxy();
		$this->uow->save($entity);
		$this->uow->save($entity);
		
		$this->repositoryMock
			->expects($this->once())
			->method('update')
			->with($entity);
		
		$this->uow->commit();
		$this->assertTrue(true);
	}

	public function testDeleteStored() {
		$entity = new UowEntity_OutletProxy();
		$this->uow->delete($entity);

		$this->repositoryMock
			->expects($this->once())
			->method('remove')
			->with($entity);

		$this->uow->commit();
		$this->assertTrue(true);
	}

	public function testMultipleCallsToDeleteShouldResultInOnlyOneOrder() {
		$entity = new UowEntity_OutletProxy();
		$this->uow->delete($entity);
		$this->uow->delete($entity);

		$this->repositoryMock
			->expects($this->once())
			->method('remove')
			->with($entity);

		$this->uow->commit();
		$this->assertTrue(true);
	}

	public function testDeleteIgnoresEntityNotStored() {
		$entity = new UowEntity();
		$this->uow->delete($entity);

		$this->repositoryMock
			->expects($this->never())
			->method('remove');

		$this->uow->commit();
		$this->assertTrue(true);
	}

	public function testDeleteOverridesUpdate() {
		$entity = new UowEntity_OutletProxy();
		$this->uow->save($entity);
		$this->uow->delete($entity);

		$this->repositoryMock
			->expects($this->once())
			->method('remove')
			->with($entity);

		$this->repositoryMock
			->expects($this->never())
			->method('update');

		$this->uow->commit();
		$this->assertTrue(true);
	}

	public function testDeleteOverridesInsert() {
		$entity = new UowEntity();
		$this->uow->save($entity);
		$this->uow->delete($entity);

		$this->repositoryMock
			->expects($this->never())
			->method('remove');

		$this->repositoryMock
			->expects($this->never())
			->method('add');

		$this->uow->commit();
		$this->assertTrue(true);
	}

	public function testCreateEntity() {
		$entity = $this->uow->createEntity('UowEntity', array('id' => 3, 'property' => 'value'));

		$this->assertEquals(3, $entity->id);
		$this->assertEquals('value', $entity->property);
		$this->assertThat($entity, $this->isInstanceOf('OutletProxy'));
		$this->assertThat($entity, $this->isInstanceOf('UowEntity'));
	}

	public function testGetsOriginalValue() {
		$entity = $this->uow->createEntity('UowEntity', array('id' => 3, 'property' => 'value'));

		$this->assertEquals('value', $this->uow->getOriginalValue($entity, 'property'));

		$entity->name = 'new value';
		$this->assertEquals('value', $this->uow->getOriginalValue($entity, 'property'));
	}

	public function setUp() {
		$classes = array(
			'UowEntity' => array(
				'table' => 'testing',
				'props' => array(
					'id' => array('id', 'int', array('pk' => true)),
					'property' => array('property', 'varchar', array('pk' => true))
				)
			)
		);
		$session = $this->openSession($classes);
		$this->uow = $session->getUnitOfWork();
		$this->repositoryMock = $this->getMock('OutletRepository', array(), array($session));
		$this->uow->setRepository($this->repositoryMock);
	}
}

class UowEntity {
	public $id;
	public $property;

	public function __construct($id = null, $name = null) {
		$this->id = $id;
		$this->name = $name;
	}
}

class UowEntity_OutletProxy extends UowEntity implements OutletProxy {

}