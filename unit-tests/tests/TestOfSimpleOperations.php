<?php

class TestOfSimpleOperations extends OutletTestCase {

	function testCrudOperations() {
		$outlet = Outlet::getInstance();
		$project = new Project;
		$project->Name = 'Project 1';
		
		Outlet::getInstance()->save($project);

		// test insert
		$bug = new Bug;
		$bug->Title = 'Test bug';
		$bug->ProjectID = $project->ProjectID;

		$outlet->save($bug);
		
		$id = $bug->ID;

		$this->assertNotNull( $id, 'Row inserted' );

		// test retrieve
		$bug = $outlet->load('Bug', $id);
		
		$this->assertIsA($bug, 'Bug', 'Object is a Bug');
		$this->assertEqual( $bug->Title, 'Test bug', 'Row retrieved' );

		// test update
		$bug->Title = 'New Test Bug';	

		$outlet->save($bug);

		$bug = $outlet->load('Bug', $bug->ID);

		$this->assertEqual( $bug->Title, 'New Test Bug', 'Row updated' );

		// test update when adding a relationship entity
		$bug2 = new Bug;
		$bug2->Title = 'Test bug 2';
		$project->addBug( $bug2 );

		$outlet->save($project);

		$project = $outlet->load('Project', $project->ProjectID);
		$this->assertEqual(count($project->getBugs()), 2, 'Two rows returned');
	}
	
	function testDefaults () {
		// make sure that the created date of the project is assigned
		// as per the defaultExpr setting
		// also make sure that the status or the project is set 
		// as per the default setting
		$project = new Project;
		$project->Name = 'Test Project';
		
		$outlet = Outlet::getInstance();
		
		$outlet->save($project);
		
		$project = $outlet->load('Project', $project->ProjectID);
		
		$this->assertEqual($project->CreatedDate, date("Y-m-d H:i:s"));
		$this->assertEqual($project->StatusID, 1);
	}

}

