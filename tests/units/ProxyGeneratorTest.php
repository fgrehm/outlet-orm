<?php

use outlet\ProxyGenerator;
use outlet\Config;

class Unit_ProxyGeneratorTest extends OutletTestCase {
	public function testGenerateProxyForSingleClass() {
		$expected = "class ProxyTest_OutletProxy extends ProxyTest implements \\outlet\\Proxy {}";
		$this->assertEquals($expected, $this->generator->generate('ProxyTest'));
	}

	public function testSupportForNamespaces() {
		$expected = "namespace testing;\nclass ProxyTest3_OutletProxy extends ProxyTest3 implements \\outlet\\Proxy {}";
		$this->assertEquals($expected, $this->generator->generate('ProxyTest3'));
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
				),
				'testing\ProxyTest3' => array (
					'alias' => 'ProxyTest3',
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