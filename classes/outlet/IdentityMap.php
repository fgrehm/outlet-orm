<?php

class OutletIdentityMap {
	private $map = array();
	private $session;

	public function __construct(OutletSession $session) {
		$this->session = $session;
		$this->config = $session->getConfig();
	}

	public function register($obj) {
		$class = $this->config->getEntity($obj)->getClass();
		$mapper = $this->session->getMapperFor($class);
		if (!isset($this->map[$class])) $this->map[$class] = array();
		
		$pks = $mapper->getPKs($obj);

		$this->map[$class][join(';', $pks)] = $obj;
	}

	public function get($class, $pk) {
		if (is_array($pk)) {
			// TODO: create a hash method for reusing
			$pk = join(';', $pk);
		}
		return $this->map[$class][$pk];
	}

	public function remove($obj) {
		$class = $this->config->getEntity($obj)->getClass();
		$mapper = $this->session->getMapperFor($class);
		if (!isset($this->map[$class])) $this->map[$class] = array();

		unset($this->map[$class][join(';', $mapper->getPKs($obj))]);
	}

	public function clear() {
		$this->map = array();
	}
}