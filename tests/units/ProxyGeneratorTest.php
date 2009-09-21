<?php

class Unit_ProxyGeneratorTest extends OutletTestCase {
	public function testGenerateProxyForSimpleClass() {
		$expected = "class ProxyTest_OutletProxy extends ProxyTest implements OutletProxy {}";
		$this->assertEquals($expected, $this->generator->generate());
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
				)
			)
		);

		$this->config = new OutletConfig($config);
		$this->generator = new OutletProxyGenerator($this->config);
	}
}