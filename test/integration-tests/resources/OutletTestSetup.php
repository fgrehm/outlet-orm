<?php
class OutletTestSetup
{
	public static function createSQLiteTables(PDO $pdo)
	{
		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS projects (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name TEXT,
				created_date TEXT NOT NULL,
				status_id INTEGER NOT NULL,
				description TEXT NOT NULL
			)'
		);

		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS addresses (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				user_id INTEGER NOT NULL,
				street TEXT
			)'
		);

		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS bugs (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				project_id INTEGER NOT NULL,
				user_id INTEGER,
				title TEXT,
                test_one INTEGER,
                time_to_fix FLOAT
			)'
		);

		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS users (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				first_name TEXT,
				last_name TEXT
			)'
		);

		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS profiles (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				user_id INTEGER NOT NULL
			)'
		);

		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS watchers (
				user_id INTEGER,
				bug_id INTEGER,
				PRIMARY KEY (user_id, bug_id)
			)'
		);

		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS machines (
				name TEXT PRIMARY KEY,
				description TEXT		
			)'
		);
	}
	
	public static function createMySQLTables(PDO $pdo)
	{
		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS projects (
				id INTEGER PRIMARY KEY AUTO_INCREMENT,
				name TEXT,
				created_date TEXT NOT NULL,
				status_id INTEGER NOT NULL,
				description TEXT NOT NULL
			)'
		);

		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS addresses (
				id INTEGER PRIMARY KEY AUTO_INCREMENT,
				user_id INTEGER NOT NULL,
				street TEXT
			)'
		);

		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS bugs (
				id INTEGER PRIMARY KEY AUTO_INCREMENT,
				project_id INTEGER NOT NULL,
				user_id INTEGER,
				title TEXT,
                test_one INTEGER,
                time_to_fix FLOAT
			)'
		);

		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS users (
				id INTEGER PRIMARY KEY AUTO_INCREMENT,
				first_name TEXT,
				last_name TEXT
			)'
		);

		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS profiles (
				id INTEGER PRIMARY KEY AUTO_INCREMENT,
				user_id INTEGER NOT NULL
			)'
		);

		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS watchers (
				user_id INTEGER,
				bug_id INTEGER,
				PRIMARY KEY (user_id, bug_id)
			)'
		);

		$pdo->exec(
			'CREATE TABLE IF NOT EXISTS machines (
				name VARCHAR(255) PRIMARY KEY,
				description TEXT
			)'
		);
	}
	
	public static function createPostgresTables(PDO $pdo)
	{
		if (!$pdo->query('SELECT EXISTS(SELECT relname FROM pg_class WHERE relname = \'projects\')')->fetchColumn()) {
			$pdo->exec(
				'CREATE TABLE projects (
					id SERIAL,
					name TEXT,
					created_date TIMESTAMP,
					status_id INTEGER NOT NULL,
					description TEXT NOT NULL
				);'
			);
			
			$pdo->exec('ALTER TABLE projects_id_seq RENAME TO projects_id_seq_test');
		}
		
		if (!$pdo->query('SELECT EXISTS(SELECT relname FROM pg_class WHERE relname = \'addresses\')')->fetchColumn()) {
			$pdo->exec(
				'CREATE TABLE addresses (
					id SERIAL,
					user_id INTEGER NOT NULL,
					street TEXT
				)'
			);
		}

		if (!$pdo->query('SELECT EXISTS(SELECT relname FROM pg_class WHERE relname = \'bugs\')')->fetchColumn()) {
			$pdo->exec(
				'CREATE TABLE bugs (
					id SERIAL,
					project_id INTEGER NOT NULL,
					user_id INTEGER,
					title TEXT,
					test_one INTEGER,
					time_to_fix FLOAT8
				)'
			);
		}

		if (!$pdo->query('SELECT EXISTS(SELECT relname FROM pg_class WHERE relname = \'users\')')->fetchColumn()) {
			$pdo->exec(
				'CREATE TABLE users (
					id SERIAL,
					first_name TEXT,
					last_name TEXT
				)'
			);
		}

		if (!$pdo->query('SELECT EXISTS(SELECT relname FROM pg_class WHERE relname = \'profiles\')')->fetchColumn()) {
			$pdo->exec(
				'CREATE TABLE profiles (
					id SERIAL,
					user_id INTEGER NOT NULL
				)'
			);
		}

		if (!$pdo->query('SELECT EXISTS(SELECT relname FROM pg_class WHERE relname = \'watchers\')')->fetchColumn()) {
			$pdo->exec(
				'CREATE TABLE watchers (
					user_id INTEGER,
					bug_id INTEGER,
					PRIMARY KEY (user_id, bug_id)
				)'
			);
		}

		if (!$pdo->query('SELECT EXISTS(SELECT relname FROM pg_class WHERE relname = \'machines\')')->fetchColumn()) {
			$pdo->exec(
				'CREATE TABLE machines (
					name TEXT PRIMARY KEY,
					description TEXT
				)'
			);
		}
	}
}