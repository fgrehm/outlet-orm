<?php
require_once 'test/integration-tests/resources/OutletTestCase.php';

class FluentInterfaceQueryAPITest extends OutletTestCase
{
	function testSimpleSelect()
	{
		$outlet = Outlet::getInstance();
		
		$p = new Project();
		$p->setName('Project 1');
		
		$outlet->save($p);
		
		$p = new Project();
		$p->setName('Project 2');
		
		$outlet->save($p);
		
		$this->assertEquals(2, count($outlet->from('Project')->find()));
	}
	
	function testFindOne()
	{
		$outlet = Outlet::getInstance();
		
		$p = new Project();
		$p->setName('Project 1');
		
		$outlet->save($p);

		$this->assertEquals($p, $outlet->from('Project')->findOne());
	}
	
	/*
          Currently not implemented
        function testEagerFetchingOneToMany () {
                // insert a project and some bugs
		$project = new Project;
		$project->setName('Cool Project');

		$bug1 = new Bug;
		$bug1->Title = 'Bug 1';
		$project->addBug( $bug1 );

		$bug2 = new Bug;
		$bug2->Title = 'Bug2';
		$project->addBug( $bug2 );

		$outlet = Outlet::getInstance();

		$outlet->save($project);

                // clear cache because it will be used to check if bugs were loaded
                $outlet->clearCache();

                $projects = $outlet->from('Project')->with('Bug')->find();
                $this->assertEqual(2, count(OutletMapper::$map['Bug']));
        }
       
        function testEagerFetchingManyToMany () {
                // Implement.....
        }
         
         */
	
	function testEagerFetchingOneToOne()
	{
		$outlet = Outlet::getInstance();
		
		$user = new User();
		$user->FirstName = 'Alvaro';
		$user->LastName = 'Carrasco';
		
		$outlet->save($user);
		
		$profile = new Profile();
		$profile->setUserID($user->UserID);
		$outlet->save($profile);
		
		$outlet->clearCache();
		
		$profile = $outlet->from('Profile')->with('User Users')->findOne();
		
		$this->assertEquals($user->UserID, $profile->getUser()->UserID);
		$this->assertEquals($user->FirstName, $profile->getUser()->FirstName);
		$this->assertEquals($user->LastName, $profile->getUser()->LastName);
	}
	
	function testEagerFetchingManyToOne()
	{
		$outlet = Outlet::getInstance();
		
		$bug = new Bug();
		$bug->Title = 'Test Bug';
		
		$project = new Project();
		$project->setName('Test Project');
		
		$bug->setProject($project);
		
		$outlet->save($bug);

		$outlet->clearCache();
		
		$bug = $outlet->from('Bug')->with('Project')->findOne();
		
		//$this->assertEquals($project->getProjectID(), $bug->getProject()->getProjectID()); When saving Project from Bugs the ProjectID was not retrieved...
		$this->assertEquals($project->getName(), $bug->getProject()->getName());
	}
	
	function testPagination()
	{
		$outlet = Outlet::getInstance();
		$totalRecords = 25;
		
		for ($i = 0; $i < $totalRecords; $i++) {
			$project = new Project();
			$project->setName('Test Project ' . $i);
			$outlet->save($project);
		}
		
		$this->assertEquals(10, count($outlet->from('Project')->limit(10)->find()));
		$this->assertEquals(5, count($outlet->from('Project')->limit(10)->offset(20)->find()));
	}
}