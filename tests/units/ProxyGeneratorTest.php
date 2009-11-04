<?php

use outlet\ProxyGenerator;

class Unit_ProxyGeneratorTest extends OutletTestCase {
	public function testGenerateProxyForSimpleEntity() {
		$expected = "class ProxyTest_OutletProxy extends ProxyTest implements OutletProxy {}\n";
		$expected .= "class ProxyTest2_OutletProxy extends ProxyTest2 implements OutletProxy {}\n";
		$this->assertEquals($expected, $this->generator->generate());
	}

	public function testGenerateProxyForSingleClass() {
		$expected = "class ProxyTest_OutletProxy extends ProxyTest implements OutletProxy {}";
		$this->assertEquals($expected, $this->generator->generate('ProxyTest'));
	}

	public function setUp () {
		$config = array(
			'connection' => array (
				'pdo' => $this->getSQLiteInMemoryPDOConnection(),
				'dialect' => 'sqlite'
			),
			'classes' => array (
				'ProxyTest' => array (
					'table' => 'proxy',
					'props' => array (
					    'id' => array('id', 'varchar', array('pk' => true))
					)
				),
				'ProxyTest2' => array (
					'table' => 'proxy',
					'props' => array (
					    'id' => array('id', 'varchar', array('pk' => true))
					)
				)
			)
		);

		$this->config = new OutletConfig($config);
		$this->generator = new ProxyGenerator($this->config);
	}
}