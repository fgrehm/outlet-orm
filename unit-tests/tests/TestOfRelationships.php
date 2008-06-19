<?php

class TestOfRelationships extends OutletTestCase {

	function testOneToMany () {
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

		$project = $outlet->load('Project', $project->ID);

		$this->assertEqual( count($project->getBugs()), 2 );
	}

	function testManyToOne () {
		$bug = new Bug;
		$bug->Title = 'Test Bug';

		$project = new Project;
		$project->Name = 'Test Project';

		$bug->setProject( $project );

		$outlet = Outlet::getInstance();
	
		$outlet->save( $bug );
	}

	function testPlural () {
		$addr = new Address;
		$addr->Street = 'Test Street';

		$user = new User;
		$user->addAddress( $addr );

		$outlet = Outlet::getInstance();

		$outlet->save( $user );

	}

}

