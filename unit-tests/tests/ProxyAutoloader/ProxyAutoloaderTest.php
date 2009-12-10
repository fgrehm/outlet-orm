<?php

require_once dirname(__FILE__).'/../../OutletTestCase.php';

require_once 'entities.php';

class ProxyAutoloaderTest extends OutletTestCase {
	protected $entityToCacheProxyPath;
	protected $root;
	protected $autoloader;

	public function setUp() {
		$this->root = dirname(__FILE__).'/';
		$this->entityToCacheProxyPath = $this->root.'OutletTestEntityToCache.php';
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
		$this->autoloader = new OutletProxyAutoloader($this->_createConfig());

		$proxy = 'OutletTestOnDemandEntity_OutletProxy';
                // This will call autoload registered functions
		$this->assertTrue(class_exists($proxy));

		// Will raise an exception if the proxy is not generated automatically
		$proxy = new $proxy();
		$this->assertTrue($proxy instanceof OutletProxy);
	}

	public function testDisabled() {
		$this->autoloader = new OutletProxyAutoloader($this->_createConfig(false));

		$proxy = 'OutletTestDisabledEntity_OutletProxy';
		$this->assertFalse(class_exists($proxy));
	}

	public function testGeneratesProxyOnlyForMappedEntities() {
		$this->autoloader = new OutletProxyAutoloader($this->_createConfig());

		$proxy = 'OutletTestUnmappedEntity_OutletProxy';
		$this->assertFalse(class_exists($proxy), 'Generated proxy for unmapped entity');
	}

	public function testCreatesProxyCache() {
		$this->autoloader = new OutletProxyAutoloader($this->_createConfig(true, $this->root));

		$proxy = 'OutletTestEntityToCache_OutletProxy';
		$this->assertTrue(class_exists($proxy));
		$this->assertFileExists($this->entityToCacheProxyPath);
	}

	public function testLoadsProxyFromCache() {
		$this->autoloader = new OutletProxyAutoloader($this->_createConfig(true, $this->root));

		$proxy = 'OutletTestCachedEntity_OutletProxy';
		$this->assertTrue(class_exists($proxy));

		$proxy = new $proxy();
		$this->assertTrue($proxy instanceof OutletProxy);

		// checks if the file was loaded
		$this->assertTrue(defined('PROXY_CACHE_TEST'));
	}

	protected function _createConfig($autoload = true, $cachePath = false) {
		$conf = array(
			'table' => 'table',
			'props' => array(
				'id' => array('id', 'int', array('pk' => true))
			)
		);
		$classes = array(
			'OutletTestOnDemandEntity' => $conf,
			'OutletTestCachedEntity' => $conf,
			'OutletTestEntityToCache' => $conf,
			'OutletTestDisabledEntity' => $conf,
		);
		$config = array(
			'connection' => array(
				'pdo' => new PDO('sqlite:test.sq3'),
				'dialect' => 'sqlite'
			),
			'classes' => $classes
		);

		$config['proxies'] = array(
			'autoload' => $autoload,
			'cache' => $cachePath
		);

		return new OutletConfig($config);
	}
}

class OutletTestDisabledEntity { }

class OutletTestOnDemandEntity { }

class OutletTestCachedEntity { }

class OutletTestEntityToCache { }

class OutletTestUnmappedEntity { }