<?php

class DomainQuery {
	private $map;
	private $from;

	public function __construct ( DomainMap $dm ) {
		$this->map = $dm->getMap();
	}

	function from ($from) {
		$this->from = $from;
		return $this;
	}

	function toSQL () {
		return "SELECT * FROM " . $this->getTable($this->from) . "";
	}

	function getFrom () {
		return $this->from;
	}

	private function getTable( $clazz ) {
		return $this->map[$clazz]['table'];
	}
}
