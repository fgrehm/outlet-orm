<?php

class Bug {
	public $ID;
	public $Title;
	public $ProjectID;

	private $project;

	function getProject () {
		return $this->project;
	}
	function setProject (Project $p) {
		$this->project = $p;	
	}
}

class Project {
	public $ID;

	private $bugs = array();

	function getBugs () {
		return $this->bugs;
	}
	function setBugs (array $bugs) {
		$this->bugs = $bugs;
	}
	function addBug (Bug $bug) {
		$this->bugs[] = $bug;
	}
}

