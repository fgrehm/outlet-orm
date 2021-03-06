<?php

namespace outlet;

class IdentityMap {
	private $map = array();
	private $session;

	public function __construct(Session $session) {
		$this->session = $session;
		$this->config = $session->getConfig();
	}

	/**
	 * Gets the key used to store the object
	 * @param $obj
	 * @return string
	 */
	private function getKey($obj) {
		return $this->config->getEntity($obj)->getSuperClass();
	}

	public function register($obj) {
		$mapper = $this->session->getMapperFor($obj);
		$key = $this->getKey($obj);

		if (!isset($this->map[$key])) $this->map[$key] = array();

		$pks = $mapper->getPKs($obj);

		$this->map[$key][join(';', $pks)] = $obj;
	}

	public function get($class, $pk) {
		if (is_array($pk)) {
			// TODO: create a hash method for reusing
			$pk = join(';', $pk);
		}
		$key = $this->getKey($class);
                if (!isset($this->map[$key])) $this->map[$key] = array();

                return isset($this->map[$key][$pk]) ? $this->map[$key][$pk] : null;
	}

	public function remove($obj) {
		$mapper = $this->session->getMapperFor($obj);

		$key = $this->getKey($obj);

		if (!isset($this->map[$key])) $this->map[$key] = array();

		unset($this->map[$key][join(';', $mapper->getPKs($obj))]);
	}

	public function clear() {
		$this->map = array();
	}
}