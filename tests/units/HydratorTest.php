<?php

use outlet\Query;
use outlet\Proxy;
use outlet\Hydrator;

class Unit_HydratorTest extends OutletTestCase {
	private $entityPrefix = 'hydratorentity_';
	
	public function testHydrateResult() {
		$result = array(
			array($this->entityPrefix.'id' => 10, $this->entityPrefix.'property' => 'value'),
			array($this->entityPrefix.'property' => 'other value', $this->entityPrefix.'id' => 2),
			array($this->entityPrefix.'property' => 'abcde', $this->entityPrefix.'id' => 3),
			array($this->entityPrefix.'id' => 14, $this->entityPrefix.'property' => 'edcba')
		);
		$expected = array(
			new HydratorEntity_OutletProxy(10, 'value'),
			new HydratorEntity_OutletProxy(2, 'other value'),
			new HydratorEntity_OutletProxy(3, 'abcde'),
			new HydratorEntity_OutletProxy(14, 'edcba')
		);

		$this->assertEquals($expected, $this->hydrator->hydrateResult($result, new HydratorQuery));
	}

	public function setUp() {
		$classes = array(
			'HydratorEntity' => array(
				'table' => 'testing',
				'props' => array(
					'id' => array('id', 'int', array('pk' => true)),
					'property' => array('prop', 'varchar')
				)
			)
		);
		$this->hydrator = new Hydrator($this->openSession($classes));
	}
}

class HydratorEntity_OutletProxy implements Proxy {
	public $id;
	public $property;

	public function __construct($id = 0, $property = '') {
		$this->id = $id;
		$this->property = $property;
	}
}

class HydratorQuery extends Query {
	public function  __construct() {
//		parent::__construct();
		$this->from('HydratorEntity');
	}
}