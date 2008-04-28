<?php
// simpletest
require_once 'simpletest/unit_tester.php';
require_once 'simpletest/reporter.php';

// outlet
require_once 'outlet/Outlet.php';
require_once 'entities.php';
require_once 'OutletTestCase.php';

// basic setup
Outlet::init('outlet-config.php');
//Outlet::getInstance()->createProxies();
require 'outlet-proxies.php';

$test = new GroupTest('All Tests');
$test->addTestFile('tests/TestOfSimpleOperations.php');
$test->addTestFile('tests/TestOfRelationships.php');

if (isset($_SERVER)) {
	$test->run(new HtmlReporter);
} else {
	$test->run(new TextReporter);
}
