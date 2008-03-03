<?php

class TestOfRelationships extends OutletTestCase {

	function setUp () {
		parent::setUp();

		// insert a project and some bugs
		$project = new Project;
		$project->Name = 'Cool Project';

		$bug1 = new Bug;
		$bug1->Title = 'Bug 1';	
		$project->addBug( $bug1 );

		$bug2 = new Bug;
		$bug2->Title = 'Bug2';
		$project->addBug( $bug2 );

		$outlet = Outlet::getInstance();

		$outlet->save($project);

		$project = $outlet->load('Project', 1);

		$this->assertEqual( count($project->getBugs()), 2 );
	}

	function testManyToOne () {
		
	}

}

