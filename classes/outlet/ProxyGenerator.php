<?php

namespace outlet;

class ProxyGenerator {
	private $config;

	function __construct(\OutletConfig $config) {
		$this->config = $config;
	}

	function generate($clazz = '') {
		$c = '';
		if ($clazz == '') {
			foreach ($this->config->getEntities() as $entity) {
				$clazz = $entity->getClass();

				$c .= $this->generate($clazz)."\n";
			}
		} else {
			$c = "class {$clazz}_OutletProxy extends $clazz implements \outlet\Proxy {}";
		}

		return $c;
	}
}

