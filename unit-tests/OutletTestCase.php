<?php

class OutletTestCase extends UnitTestCase {

	function setUp () {
		// create database
		$pdo = Outlet::getInstance()->getConnection();

		switch (DATABASE_DRIVER) {
			case 'sqlite': 	OutletTestSetup::createSQLiteTables( $pdo );	break;
			case 'mysql':	OutletTestSetup::createMySQLTables( $pdo );		break;
			default: 		OutletTestSetup::createSQLiteTables( $pdo );
		}	
	
		/*	
		$pdo->exec('DELETE FROM projects');
		$pdo->exec('DELETE FROM bugs');
		$pdo->exec('DELETE FROM users');
		$pdo->exec('DELETE FROM watchers');
		*/
	}

	function tearDown () {

	}
}

class OutletTestSetup {
	function createSQLiteTables ($pdo) {
		// create projects table
		$pdo->exec("
			CREATE TABLE IF NOT EXISTS projects (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name TEXT,
				created_date TEXT,
				status_id INTEGER
			)
		");

		// create addresses table
		$pdo->exec("
			CREATE TABLE IF NOT EXISTS addresses (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				user_id INTEGER NOT NULL,
				street TEXT
			)
		");

		// create bugs table
		$pdo->exec("
			CREATE TABLE IF NOT EXISTS bugs (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				project_id INTEGER NOT NULL,
				user_id INTEGER,
				title TEXT
			)
		");

		// create users table
		$pdo->exec("
			CREATE TABLE IF NOT EXISTS users (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				first_name TEXT,
				last_name TEXT
			)
		");

		$pdo->exec("
			CREATE TABLE IF NOT EXISTS profiles (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				user_id INTEGER NOT NULL
			)
		");

		// create watchers table
		$pdo->exec("
			CREATE TABLE IF NOT EXISTS watchers (
				user_id INTEGER,
				bug_id INTEGER,
				PRIMARY KEY (user_id, bug_id)
			)	
		");
	}

	function createMySQLTables ($pdo) {
		// create projects table
		$pdo->exec("
			CREATE TABLE IF NOT EXISTS projects (
				id INTEGER PRIMARY KEY AUTO_INCREMENT,
				name TEXT,
				created_date DATETIME
			)
		");

		// create bugs table
		$pdo->exec("
			CREATE TABLE IF NOT EXISTS bugs (
				id INTEGER PRIMARY KEY AUTO_INCREMENT,
				project_id INTEGER NOT NULL,
				user_id INTEGER,
				title TEXT
			)
		");

		// create users table
		$pdo->exec("
			CREATE TABLE IF NOT EXISTS users (
				id INTEGER PRIMARY KEY AUTO_INCREMENT,
				first_name TEXT,
				last_name TEXT
			)
		");

		// create watchers table
		$pdo->exec("
			CREATE TABLE IF NOT EXISTS watchers (
				user_id INTEGER,
				bug_id INTEGER,
				PRIMARY KEY (user_id, bug_id)
			)	
		");
	}
}
