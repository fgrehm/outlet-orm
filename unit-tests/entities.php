<?php

class Address {
	public $AddressID;
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
	public $ProjectID;
	public $Name;
	public $CreatedDate;
	public $StatusID;

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
	public $UserID;
	public $FirstName;
	public $LastName;

	private $addresses = array();

	public function getWorkAddresses () {
		return $this->addresses;
	}
	public function setWorkAddresses(array $addresses) {
		$this->addresses = $addresses;
	}
	public function addWorkAddress(Address $addr) {
		$this->addresses[] = $addr;
	}
}

