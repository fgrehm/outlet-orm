<?php

class Unit_IdentityMapTest extends OutletTestCase {
	public function testCanRegisterAndRetrieveEntity() {
		$entity1 = new IdentityEntity1_OutletProxy();
		$entity1->id = 1;
		$entity2 = new IdentityEntity2_OutletProxy();
		$entity2->id = 1;

		$this->assertNull($this->identityMap->get('IdentityEntity1', 1));
		$this->assertNull($this->identityMap->get('IdentityEntity2', 1));

		$this->identityMap->register($entity1);
		$this->identityMap->register($entity2);

		$this->assertSame($entity1, $this->identityMap->get('IdentityEntity1', 1));
		$this->assertSame($entity2, $this->identityMap->get('IdentityEntity2', 1));
	}

	public function testReturnsNullIfNotRegistered() {
		$this->assertNull($this->identityMap->get('IdentityEntity2', 1));
	}

	public function testCanRemoveFromMap() {
		$entity = new IdentityEntity1_OutletProxy();
		$entity->id = 1;

		$this->identityMap->register($entity);
		$this->identityMap->remove($entity);

		$this->assertNull($this->identityMap->get('IdentityEntity1', 1));
	}

	public function testSubclassIsRegisteredAsSuper() {
		$sub = new SubEntity_OutletProxy();
		$sub->id = 2;
		$this->identityMap->register($sub);
		$this->assertSame($sub, $this->identityMap->get('SuperEntity', 2));
		$this->assertSame($sub, $this->identityMap->get('SubEntity', 2));
	}

	public function testRegisteresSuclassCanBeRemoved() {
		$sub = new SubEntity_OutletProxy();
		$sub->id = 2;

		$this->identityMap->register($sub);
		$this->identityMap->remove($sub);

		$this->assertNull($this->identityMap->get('SubEntity', 2));
	}

	public function setUp() {
		$config = array(
			'IdentityEntity1' => array(
				'table' => 'testing',
				'props' => array('id' => array('id', 'int', array('pk' => true)))
			),
			'IdentityEntity2' => array(
				'table' => 'testing',
				'props' => array('id' => array('id', 'int', array('pk' => true)))
			),
      		'SuperEntity' => array(
        		'table' => 'testing',
        		'props' => array('id' => array('id', 'int', array('pk' => true))),
        		'discriminator' => array('type', 'int'),
        		'discriminator-value' => 'super',
        		'subclasses' => array(
          			'SubEntity' => array(
            			'discriminator-value' => 'sub',
            			'props' => array(
              				'foo' => array('bar', 'int')
						)
					)
				)
			)
		);

		$this->identityMap = $this->openSession($config)->getIdentityMap();
	}
}

class IdentityEntity1_OutletProxy implements OutletProxy {
	public $id;
}

class IdentityEntity2_OutletProxy implements OutletProxy {
	public $id;
}

class SuperEntity_OutletProxy implements OutletProxy {
	public $id;
}

class SubEntity_OutletProxy implements OutletProxy {
	public $id;
}
