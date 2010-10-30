<?php
require_once dirname(__FILE__) . '/../OutletTestCase.php';
class RelationshipsTest extends OutletTestCase
{
	public function testOneToMany()
	{
		$outlet = Outlet::getInstance();
		
		$project = new Project();
		$project->setName('Cool Project');
		
		$bug1 = new Bug();
		$bug1->Title = 'Bug 1';
		$project->addBug($bug1);
		
		$bug2 = new Bug();
		$bug2->Title = 'Bug2';
		$project->addBug($bug2);
		
		$outlet->save($project);
		$project = $outlet->load('Project', $project->getProjectID());
		
		$this->assertEquals(2, count($project->getBugs()));
	}
	
	public function testGettingCollectionItem()
	{
		$outlet = Outlet::getInstance();
		
		$project = new Project();
		$project->setName('Cool Project');
		
		$bug1 = new Bug();
		$bug1->Title = 'Bug 1';
		$project->addBug($bug1);
		
		$bug2 = new Bug();
		$bug2->Title = 'Bug2';
		$project->addBug($bug2);
		
		$outlet->save($project);
		$outlet->clearCache();
		
		$project = $outlet->load('Project', $project->getProjectID());
		$bugs = $project->getBugs();
		
		$this->assertNotNull($bugs[1]);
	}
	
	public function testManyToOne()
	{
		$outlet = Outlet::getInstance();
		
		$bug = new Bug();
		$bug->Title = 'Test Bug';
		
		$project = new Project();
		$project->setName('Test Project');
		$bug->setProject($project);
		
		$outlet->save($bug);
	}

	public function testOneToOne()
	{
		$outlet = Outlet::getInstance();
		
		$user = new User();
		$user->FirstName = 'Alvaro';
		$user->LastName = 'Carrasco';
		
		$outlet->save($user);
		
		$profile = new Profile();
		$profile->setUserID($user->UserID);
		
		$outlet->save($profile);
		
		$this->assertEquals($user, $profile->getUser());
	}
	
	public function testManyToMany()
	{
		$outlet = Outlet::getInstance();
		
		$user = new User();
		$user->Firstname = 'Alvaro';
		$user->LastName = 'Carrasco';
		
		$bug = new Bug();
		$bug->Name = 'Test Bug';
		
		$project = new Project();
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
		
		$addr = new Address();
		$addr->Street = 'Test Street';
		
		$user = new User();
		$user->addWorkAddress($addr);
		
		$outlet->save($user);
	}
	
	public function testUpdateAfterRelationshipUpdate()
	{
		$outlet = Outlet::getInstance();

		$p = new Project();
		$p->setName('Name 1');
		
		$b = new Bug();
		$b->Title = 'Test Bug';
		$p->addBug($b);
		
		$outlet->save($p);
		
		$projectid = $p->getProjectID();
		$p->setName('Name 2');

		$outlet->save($p);
		
		$p = $outlet->load('Project', $projectid);
		
		$this->assertEquals('Name 2', $p->getName());
	}
}