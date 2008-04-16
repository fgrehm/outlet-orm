<?php

class OutletTestCase extends UnitTestCase {

	function setUp () {
		// create database
		$pdo = new PDO('sqlite:test.sq3');
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
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
				user_id INTEGER,
				title TEXT
			)
		");

		// create users table
		$pdo->exec("
			CREATE TABLE users (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				first_name TEXT,
				last_name TEXT
			)
		");

		// create watchers table
		$pdo->exec("
			CREATE TABLE watchers (
				user_id INTEGER,
				bug_id INTEGER,
				PRIMARY KEY (user_id, bug_id)
			)	
		");

		Outlet::init('outlet-config.php');
	}

	function tearDown () {
		// remove database
		@unlink('test.sq3');
	}

}

