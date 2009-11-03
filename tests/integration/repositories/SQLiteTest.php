<?php
require_once 'RepositoryTestCase.php';

if (strstr(REPOSITORIES_TO_TEST, 'sqlite') !== false) {

class Integration_Repositories_SQLiteTest extends Integration_Repositories_RepositoryTestCase {
	protected function createTables() {
		$this->session->getConnection()->execute('CREATE TABLE bugs (id NUMERIC, name TEXT)');
		$this->session->getConnection()->execute('CREATE TABLE projects (id NUMERIC, description TEXT, project_name TEXT)');
		$this->session->getConnection()->execute('CREATE TABLE composite_test (id NUMERIC, name TEXT)');
	}
}

}