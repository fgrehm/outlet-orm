<?php
require_once 'test/integration-tests/resources/init.php';
require_once 'PHPUnit/Framework/TestCase.php';

abstract class OutletTestCase extends PHPUnit_Framework_TestCase
{
	/**
	 * For some reason having this set to true (default) 
	 * makes phpunit try to serialize PDO
	 * 
	 * @var boolean
	 */
	protected $backupGlobals = false;
	
	protected function setUp()
	{
		$pdo = Outlet::getInstance()->getConnection()->getPDO();
		
		switch (DATABASE_DRIVER) {
			case 'mysql':
				OutletTestSetup::createMySQLTables($pdo);
				break;
			case 'pgsql':
				OutletTestSetup::createPostgresTables($pdo);
				break;
			case 'sqlite':
			default:
				OutletTestSetup::createSQLiteTables($pdo);
		}
		
		$pdo->exec('DELETE FROM projects');
		$pdo->exec('DELETE FROM addresses');
		$pdo->exec('DELETE FROM bugs');
		$pdo->exec('DELETE FROM machines');
		$pdo->exec('DELETE FROM users');
		$pdo->exec('DELETE FROM watchers');
		$pdo->exec('DELETE FROM profiles');
		
		parent::setUp();
	}
	
	protected function tearDown()
	{
		parent::tearDown();
	}
}