<?php

use outlet\ProxyGenerator;
use outlet\Config;

class Unit_ProxyGeneratorTest extends OutletTestCase {
	public function testGenerateProxyForSimpleEntity() {
		$expected = "class ProxyTest_OutletProxy extends ProxyTest implements \outlet\Proxy {}\n";
		$expected .= "class ProxyTest2_OutletProxy extends ProxyTest2 implements \outlet\Proxy {}\n";
		$this->assertEquals($expected, $this->generator->generate());
	}

	public function testGenerateProxyForSingleClass() {
		$expected = "class ProxyTest_OutletProxy extends ProxyTest implements \outlet\Proxy {}";
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

		$this->config = new Config($config);
		$this->generator = new ProxyGenerator($this->config);
	}
}