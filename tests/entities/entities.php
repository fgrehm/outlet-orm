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