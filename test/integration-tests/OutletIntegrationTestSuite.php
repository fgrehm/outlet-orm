<?php
require_once 'PHPUnit\Framework\TestSuite.php';

require_once 'test\integration-tests\tests\SimpleOperationsTest.php';
require_once 'test\integration-tests\tests\FluentInterfaceQueryAPITest.php';
require_once 'test\integration-tests\tests\RelationshipsTest.php';
require_once 'test\integration-tests\tests\IdentityMapTest.php';
/**
 * Static test suite.
 */
class OutletIntegrationTestSuite extends PHPUnit_Framework_TestSuite
{
	/**
	 * Constructs the test suite handler.
	 */
	public function __construct()
	{
		$this->setName('OutletIntegrationTestSuite');
		$this->addTestSuite('SimpleOperationsTest');
		$this->addTestSuite('FluentInterfaceQueryAPITest');
		$this->addTestSuite('RelationshipsTest');
		$this->addTestSuite('IdentityMapTest');
	}
	/**
	 * Creates the suite.
	 */
	public static function suite()
	{
		return new self();
	}
}

