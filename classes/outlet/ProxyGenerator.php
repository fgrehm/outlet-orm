<?php

namespace outlet;

class ProxyGenerator {
	private $config;

	function __construct(Config $config) {
		$this->config = $config;
	}

	function generate($clazz) {
		$config = $this->config->getEntity($clazz);
		$proxyClazz = $config->getProxyClass();
		$clazz = $config->getEntityClass();
		$namespace = trim($config->getNamespace(), '\\');

		$c = '';
		if ($config->getNamespace() != null)
			$c .= "namespace {$namespace};\n";
		$c .= "class {$proxyClazz} extends $clazz implements \outlet\Proxy {}";

		return $c;
	}
}

