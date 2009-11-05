<?php

require_once 'entities.php';

use outlet\Config,
    outlet\Proxy,
    outlet\ProxyAutoloader;

class Integration_ProxyAutoloaderTest extends OutletTestCase {
	protected $entityToCacheProxyPath;
	protected $root;
	protected $autoloader;

	public function setUp() {
		$this->root = __DIR__.'/';
		$this->entityToCacheProxyPath = $this->root.'EntityToCache.php';
	}

	public function tearDown() {
		parent::tearDown();

		if (file_exists($this->entityToCacheProxyPath))
			unlink($this->entityToCacheProxyPath);

		// Force unregistering
		if (isset($this->autoloader)) {
			$this->autoloader->unregister();
			unset($this->autoloader);
		}
	}

	public function testGenerateProxiesOnDemand() {
		$this->autoloader = new ProxyAutoloader($this->_createConfig());

		$proxy = 'outlet\tests\model\OnDemandEntity_OutletProxy';
		$this->assertTrue(class_exists($proxy));

		// Will raise an exception if the proxy is not generated automatically
		$proxy = new $proxy();
		$this->assertTrue($proxy instanceof Proxy);
	}

	public function testDisabled() {
		$this->autoloader = new ProxyAutoloader($this->_createConfig(false));

		$proxy = 'outlet\tests\model\DisabledEntity_OutletProxy';
		$this->assertFalse(class_exists($proxy));
	}

	public function testGeneratesProxyOnlyForMappedEntities() {
		$this->autoloader = new ProxyAutoloader($this->_createConfig());

		$proxy = 'outlet\tests\model\UnmappedEntity_OutletProxy';
		$this->assertFalse(class_exists($proxy), 'Generated proxy for unmapped entity');
	}

	public function testLoadsProxyFromCache() {
		$this->autoloader = new ProxyAutoloader($this->_createConfig(true, $this->root));

		$proxy = 'outlet\tests\model\CachedEntity_OutletProxy';
		$this->assertTrue(class_exists($proxy));

		$proxy = new $proxy();
		$this->assertTrue($proxy instanceof Proxy);

		// checks if the file was loaded
		$this->assertTrue(defined('PROXY_CACHE_TEST'));
	}

	public function testCreatesProxyCache() {
		$this->autoloader = new ProxyAutoloader($this->_createConfig(true, $this->root));

		$proxy = 'outlet\tests\model\EntityToCache_OutletProxy';
		$this->assertTrue(class_exists($proxy));
		$this->assertFileExists($this->entityToCacheProxyPath);
	}

	protected function _createConfig($autoload = true, $cachePath = false) {
		$conf = array(
			'table' => 'table',
			'props' => array(
				'id' => array('id', 'int', array('pk' => true))
			)
		);
		$classes = array(
			'outlet\tests\model\OnDemandEntity' => $conf,
			'outlet\tests\model\CachedEntity' => $conf,
			'outlet\tests\model\EntityToCache' => $conf,
			'outlet\tests\model\DisabledEntity' => $conf,
		);
		$config = $this->createConfigArray($classes);

		$config['proxies'] = array(
			'autoload' => $autoload,
			'cache' => $cachePath
		);

		return new Config($config);
	}
}