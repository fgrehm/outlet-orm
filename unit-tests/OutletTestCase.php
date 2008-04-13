<?php

class OutletTestCase extends UnitTestCase {

	function setUp () {
		// create database
		$pdo = new PDO('sqlite:test.sq3');	
	
		// create projects table
		$pdo->exec("
			CREATE TABLE projects (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name TEXT
			)
		");

		// create bugs table
		$pdo->exec("
			CREATE TABLE bugs (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				project_id INTEGER NOT NULL,
				title TEXT
			)
		");

		Outlet::init('outlet-config.php');
	}

	function tearDown () {
		// remove database
		@unlink('test.sq3');
	}

}

