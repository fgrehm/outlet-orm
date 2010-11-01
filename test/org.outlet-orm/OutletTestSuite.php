<?php
require_once 'PHPUnit/Framework/TestSuite.php';

require_once 'test/org.outlet-orm/autoloader/OutletAutoloaderTest.php';
require_once 'test/org.outlet-orm/config/OutletXmlConfigTest.php';
/**
 * Static test suite.
 */
class OutletTestSuite extends PHPUnit_Framework_TestSuite
{
	/**
	 * Constructs the test suite handler.
	 */
	public function __construct()
	{
		$this->setName('OutletTestSuite');
		
		$this->addTestSuite('OutletAutoloaderTest');
		$this->addTestSuite('OutletXmlConfigTest');
	}
	/**
	 * Creates the suite.
	 */
	public static function suite()
	{
		return new self();
	}
}