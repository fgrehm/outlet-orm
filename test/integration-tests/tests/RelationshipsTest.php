<?php
require_once 'test/integration-tests/resources/OutletTestCase.php';

class RelationshipsTest extends OutletTestCase
{
	public function testOneToMany()
	{
		$outlet = Outlet::getInstance();
		
		$project = new OutletTest_Project();
		$project->setName('Cool Project');
		
		$bug1 = new OutletTest_Bug();
		$bug1->Title = 'Bug 1';
		$project->addBug($bug1);
		
		$bug2 = new OutletTest_Bug();
		$bug2->Title = 'Bug2';
		$project->addBug($bug2);
		
		$outlet->save($project);
		$project = $outlet->load('OutletTest_Project', $project->getProjectID());
		
		$this->assertEquals(2, count($project->getBugs()));
	}
	
	public function testGettingCollectionItem()
	{
		$outlet = Outlet::getInstance();
		
		$project = new OutletTest_Project();
		$project->setName('Cool Project');
		
		$bug1 = new OutletTest_Bug();
		$bug1->Title = 'Bug 1';
		$project->addBug($bug1);
		
		$bug2 = new OutletTest_Bug();
		$bug2->Title = 'Bug2';
		$project->addBug($bug2);
		
		$outlet->save($project);
		$outlet->clearCache();
		
		$project = $outlet->load('OutletTest_Project', $project->getProjectID());
		$bugs = $project->getBugs();
		
		$this->assertNotNull($bugs[1]);
	}
	
	public function testManyToOne()
	{
		$outlet = Outlet::getInstance();
		
		$bug = new OutletTest_Bug();
		$bug->Title = 'Test Bug';
		
		$project = new OutletTest_Project();
		$project->setName('Test Project');
		$bug->setProject($project);
		
		$outlet->save($bug);
	}

	public function testOneToOne()
	{
		$outlet = Outlet::getInstance();
		
		$user = new OutletTest_User();
		$user->FirstName = 'Alvaro';
		$user->LastName = 'Carrasco';
		
		$outlet->save($user);
		
		$profile = new OutletTest_Profile();
		$profile->setUserID($user->UserID);
		
		$outlet->save($profile);
		
		$this->assertEquals($user, $profile->getUser());
	}
	
	public function testManyToMany()
	{
		$outlet = Outlet::getInstance();
		
		$user = new OutletTest_User();
		$user->Firstname = 'Alvaro';
		$user->LastName = 'Carrasco';
		
		$bug = new OutletTest_Bug();
		$bug->Name = 'Test Bug';
		
		$project = new OutletTest_Project();
		$project->setName('Test Project');
		$bug->setProject($project);
		
		$outlet->save($user);
		$outlet->save($bug);
		
		$user->getBugs()->add($bug);
		$outlet->save($user);
		
		$this->assertEquals(1, count($user->getBugs()), 'One project attached to this user');
	}
	
	public function testPlural()
	{
		$outlet = Outlet::getInstance();
		
		$addr = new OutletTest_Address();
		$addr->Street = 'Test Street';
		
		$user = new OutletTest_User();
		$user->addWorkAddress($addr);
		
		$outlet->save($user);
	}
	
	public function testUpdateAfterRelationshipUpdate()
	{
		$outlet = Outlet::getInstance();

		$p = new OutletTest_Project();
		$p->setName('Name 1');
		
		$b = new OutletTest_Bug();
		$b->Title = 'Test Bug';
		$p->addBug($b);
		
		$outlet->save($p);
		
		$projectid = $p->getProjectID();
		$p->setName('Name 2');

		$outlet->save($p);
		
		$p = $outlet->load('OutletTest_Project', $projectid);
		
		$this->assertEquals('Name 2', $p->getName());
	}
}