<?php

class Bug {
	public $ID;
	public $Title;
	public $ProjectID;
	protected $Project;

	function getProject () {
		return $this->Project;
	}
}

class Project {
	public $ID;

	protected $Bugs = array();

	function getBugs () {
		return $this->Bugs;
	}

	function addBug (Bug $bug) {
		$this->Bugs[] = $bug;
	}
}

