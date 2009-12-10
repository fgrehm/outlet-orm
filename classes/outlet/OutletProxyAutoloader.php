<?php

class OutletProxyAutoloader {
        protected $config;
	protected $proxyGenerator;

        public function __construct(OutletConfig $config) {
                if ($config == null) throw new OutletException('Config cannot be null');

		$this->config = $config;

                if (!$config->autoloadProxies) return;

		$this->proxyGenerator = new OutletProxyGenerator($config);
		$this->register();
        }

        public function  __destruct() {
		$this->unregister();
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
        
        public function autoload($proxyClass) {
                if (strpos($proxyClass, '_OutletProxy') === false) return;

		$class = substr($proxyClass, 0, -12);
		$entityConfig = $this->config->getEntity($class, false);
		if ($entityConfig === null) return;

		$class = $entityConfig->clazz;
		if (!$this->useCache())
			eval($this->proxyGenerator->generate($class));
		else {
			$proxyCachePath = $this->config->proxiesCache.'/'.$class.'.php';

			if (!file_exists($proxyCachePath))
				file_put_contents($proxyCachePath, "<?php \n".$this->proxyGenerator->generate($class));

			require_once $proxyCachePath;
		}
        }
}