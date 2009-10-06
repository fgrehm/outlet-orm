<?php

class Project {
	public $id;
	public $name;
	public $description;

	public function __construct($name = '', $id = 0) {
		$this->id = $id;
		$this->name = $name;
	}
}

class Bug {
	private $id;
	private $name;

	public function __construct($name = '', $id = 0) {
		$this->id = $id;
		$this->name = $name;
	}

	public function getID() {
		return $this->id;
	}
	public function setID($id) {
		$this->id = $id;
	}

	public function getName() {
		return $this->name;
	}
	public function setName($name) {
		$this->name = $name;
	}
}

class Composite {
	private $pk;
	private $otherPK;

	public function __construct($pk1 = 0, $pk2 = 0) {
		$this->pk = $pk1;
		$this->otherPK = $pk2;
	}

	public function getPK() {
		return $this->pk;
	}
	public function setPK($pk) {
		$this->pk = $pk;
	}

	public function getOtherPK() {
		return $this->otherPK;
	}
	public function setOtherPK($otherPK) {
		$this->otherPK = $otherPK;
	}
}