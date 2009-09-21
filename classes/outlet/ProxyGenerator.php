<?php

class OutletProxyGenerator {
	private $config;

	function __construct (OutletConfig $config) {
		$this->config = $config;
	}

	function generate () {
		$c = '';
		foreach ($this->config->getEntities() as $entity) {
			$clazz = $entity->getClass();

			$c .= "class {$clazz}_OutletProxy extends $clazz implements OutletProxy {}";
		}

		return $c;
	}
}

