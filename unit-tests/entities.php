<?php

class Address {
	public $ID;
	public $UserID;
	public $Street;
}

class Bug {
	public $ID;
	public $Title;
	public $ProjectID;

	private $project;
	private $watchers = array();

	function getProject () {
		return $this->project;
	}
	function setProject (Project $p) {
		$this->project = $p;	
	}

	function getWatchers () {
		return $this->watchers;	
	}
	function setWatchers (array $watchers) {
		$this->watchers = $watchers;
	}
	function addWatcher(User $watcher) {
		$this->watcher[] = $watcher;
	}
}

class Project {
	public $ID;
	public $Name;

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

class User {
	public $ID;
	public $FirstName;
	public $LastName;

	private $addresses = array();

	public function getAddresses () {
		return $this->addresses;
	}
	public function setAddresses(array $addresses) {
		$this->addresses = $addresses;
	}
	public function addAddress(Address $addr) {
		$this->addresses[] = $addr;
	}
}

