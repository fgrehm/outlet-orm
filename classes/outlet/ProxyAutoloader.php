<?php

namespace outlet;

use \OutletConfig as Config;

class ProxyAutoloader {
        protected $config;
	protected $proxyGenerator;

        public function __construct(Config $config) {
                if ($config == null) throw new OutletException('Config cannot be null');

                if (!$config->autoloadProxies) return;
	
		$this->config = $config;
		$this->proxyGenerator = new ProxyGenerator($config);
		$this->register();
        }

	protected function useCache() {
		return $this->config->proxiesCache !== false;
	}

	public function register() {
		spl_autoload_register(array($this, 'autoload'));
	}

	public function unregister() {
		spl_autoload_unregister(array($this, 'autoload'));
	}

	public function  __destruct() {
		$this->unregister();
	}

        public function autoload($proxyClass) {
                if (strpos($proxyClass, '_OutletProxy') == false) return;
	
		$class = substr($proxyClass, 0, -12);

		if ($this->config->getEntity($class, false) === null) return;

		if (!$this->useCache())
			eval($this->proxyGenerator->generate($class));
		else {
			$proxyCachePath = $this->config->proxiesCache.'/'.$proxyClass.'.php';
			if (!file_exists($proxyCachePath))
				file_put_contents($proxyCachePath, "<?php \n".$this->proxyGenerator->generate($class));
		
			require_once $proxyCachePath;
		}
        }
}