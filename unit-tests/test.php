<?php
// simpletest
require_once 'simpletest/unit_tester.php';
require_once 'simpletest/reporter.php';

// outlet
require_once '../classes/outlet/Outlet.php';
require_once 'entities.php';
require_once 'OutletTestCase.php';

// basic setup
Outlet::init(
	array(
		'connection' => array(
			'dsn' => 'sqlite:test.sq3'	
		),
		'classes' => array(
			'Bug' => array(
				'table' => 'bugs',
				'fields' => array(
					'ID' 		=> array('id', 'int', array('pk'=>true, 'autoIncrement'=>true)),
					'Title'		=> array('title', 'varchar'),
					'ProjectID' => array('project_id', 'int')
				)
			),
			'Project' => array(
				'table' => 'projects',
				'fields' => array(
					'ID' 	=> array('id', 'int', array('pk'=>true, 'autoIncrement'=>true)),
					'Name'	=> array('name', 'varchar')
				)
			)
		)
	)
);
Outlet::getInstance()->createProxies();

$test = new GroupTest('All Tests');
$test->addTestFile('tests/TestOfSimpleOperations.php');
$test->addTestFile('tests/TestOfRelationships.php');
$test->run(new TextReporter);
