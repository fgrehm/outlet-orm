<?php
require_once 'RepositoryTestCase.php';

if (strstr(REPOSITORIES_TO_TEST, 'mysql') !== false) {

class Integration_Repositories_MySQLTest extends Integration_Repositories_RepositoryTestCase {
	protected function openSession($configClassesArray, $enableAutoload = false) {
		$config = $this->createConfigArray($configClassesArray, new PDO(MYSQL_DSN, MYSQL_USER, MYSQL_PASSWORD));
		$config['connection']['dialect'] = 'mysql';
		$config['proxies'] = array(
			'autoload' => true
		);

		return Outlet::openSession(new OutletConfig($config));
	}

	protected function createTables() {
		$this->session->getConnection()->execute('CREATE TABLE bugs (id INT, name VARCHAR(255))');
		$this->session->getConnection()->execute('CREATE TABLE projects (id INT, description TEXT, project_name VARCHAR(255))');
		$this->session->getConnection()->execute('CREATE TABLE composite_test (id INT, name VARCHAR(255))');
	}
}

}