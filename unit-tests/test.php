<?php
// simpletest
require_once 'simpletest/unit_tester.php';
require_once 'simpletest/reporter.php';

// outlet
require_once '../classes/outlet/Outlet.php';
require_once 'entities.php';
require_once 'OutletTestCase.php';

// basic setup
Outlet::init('outlet-config.php');
Outlet::getInstance()->createProxies();

$test = new GroupTest('All Tests');
$test->addTestFile('tests/TestOfSimpleOperations.php');
$test->addTestFile('tests/TestOfRelationships.php');
$test->run(new TextReporter);
