<?php
require_once 'test/integration-tests/resources/OutletTestCase.php';

class SimpleOperationsTest extends OutletTestCase
{
	public function testCrudOperations()
	{
		$outlet = Outlet::getInstance();
		
		$project = new OutletTest_Project();
		$project->setName('Project 1');
		
		$outlet->save($project);
		
		$bug = new OutletTest_Bug();
		$bug->Title = 'Test bug';
		$bug->ProjectID = $project->getProjectID();
		$outlet->save($bug);
		
		$id = $bug->ID;
		$this->assertNotNull($id, 'Row inserted');
		
		$bug = $outlet->load('OutletTest_Bug', $id);
		$this->assertTrue($bug instanceof OutletTest_Bug, 'Object is a Bug');
		$this->assertEquals('Test bug', $bug->Title, 'Row retrieved');

		$bug->Title = 'New Test Bug';
		$outlet->save($bug);
		
		$bug = $outlet->load('OutletTest_Bug', $bug->ID);
		$this->assertEquals('New Test Bug', $bug->Title, 'Row updated');

		$bug2 = new OutletTest_Bug();
		$bug2->Title = 'Test bug 2';
		$project->addBug($bug2);
		$outlet->save($project);
		$project = $outlet->load('OutletTest_Project', $project->getProjectID());
		$this->assertEquals(2, count($project->getBugs()), 'Two rows returned');

		$bug3 = new OutletTest_Bug();
		$bug3->Title = 'Bug 3';
		$bug3->setProject($project);
		$outlet->save($bug3);
		
		$project2 = new OutletTest_Project();
		$project2->setName('Project 2');
		$outlet->save($project2);
		
		$bug3->setProject($project2);
		$this->assertEquals($project2->getProjectID(), $bug3->ProjectID, "Bug gets assigned the id of the project on setProject");
	}
	
	function testNonAutoIncrementingVarcharPrimaryKey()
	{
		$m = new OutletTest_Machine();
		$m->Name = 'test';
		$m->Description = 'Test machine';
		
		$outlet = Outlet::getInstance();

		$outlet->save($m);
		$outlet->clearCache();

		$machine = $outlet->load('OutletTest_Machine', $m->Name);
		$this->assertNotNull($machine, "Machine was saved and retrieved");

		$machine->Description = 'Updated description';
		$outlet->save($machine);
	}
	
	function testDefaults()
	{
		$project = new OutletTest_Project();
		$project->setName('Test Project');
		$outlet = Outlet::getInstance();
		$outlet->save($project);
		
		$now = time();
		
		$outlet->clearCache();
		$project = $outlet->load('OutletTest_Project', $project->getProjectID());

		$this->assertTrue($now - ((int) $project->getCreatedDate()->format('U')) < 2);
		$this->assertEquals(1, $project->getStatusID());
		$this->assertEquals('Default Description', $project->getDescription());
	}
	
	function testDelete()
	{
		$outlet = Outlet::getInstance();
		
		$project = new OutletTest_Project();
		$project->setName('Test Project');
		$outlet->save($project);
		
		$project = $outlet->load('OutletTest_Project', $project->getProjectID());
		$project_id = $project->getProjectID();
		$outlet->delete('OutletTest_Project', $project_id);

		$project = $outlet->load('OutletTest_Project', $project_id);
		$this->assertNull($project, 'Project was deleted');
	}
	
	function testUpdate()
	{
		$outlet = Outlet::getInstance();
		
		$p = new OutletTest_Project();
		$p->setName('Project test update');
		$outlet->save($p);
		
		$id = $p->getProjectID();
		$p->setName('Project test update2');
		$p->setCreatedDate(new DateTime('2009-01-25'));
		
		$outlet->save($p);
		$outlet->clearCache();
		
		$stmt = $outlet->query('SELECT * FROM projects');

		$p = $outlet->load('OutletTest_Project', $id);
		$this->assertEquals('Project test update2', $p->getName());
		$this->assertEquals('2009-01-25', $p->getCreatedDate()->format('Y-m-d'));
	}
	
	function testAutoIncrementWithGettersAndSettersEnabled()
	{
		$outlet = Outlet::getInstance();
		
		$p = new OutletTest_Project();
		$p->setName('Project test update');
		$outlet->save($p);
		
		$this->assertNotNull($p->getProjectID());
	}
	
	function testFloatProperty()
	{
		$outlet = Outlet::getInstance();
		
		$project = new OutletTest_Project();
		$project->setName('Project 1');
		$outlet->save($project);

		$bug = new OutletTest_Bug();
		$bug->Title = 'Test bug';
		$bug->setProject($project);
		$outlet->save($bug);

		$this->assertEquals($bug->TimeToFix, 2000.000001);
		$bug->TimeToFix = 100000.000001;
		$outlet->save($bug);
		$id = $bug->ID;

		$outlet->clearCache();
		$bug = $outlet->load('OutletTest_Bug', $id);
		
		$this->assertEquals(100000.000001, $bug->TimeToFix);
	}
	
	function testDataTypes()
	{
		$outlet = Outlet::getInstance();
		
		$project = new OutletTest_Project();
		$project->setName('Project 1');
		$outlet->save($project);
		$project_id = $project->getProjectID();

		$bug = new OutletTest_Bug();
		$bug->Title = 'Test bug';
		$bug->setProject($project);
		$outlet->save($bug);
		$bug_id = $bug->ID;

		$outlet->clearCache();
		
		$bug = $outlet->load('OutletTest_Bug', $bug_id);
		$project = $outlet->load('OutletTest_Project', $project_id);
		
		$this->assertTrue(is_string($bug->Title));
		$this->assertTrue(is_int($bug->ID));
		$this->assertTrue(is_float($bug->TimeToFix));
		$this->assertTrue($project->getCreatedDate() instanceof DateTime);
	}
	
	function testSelectAndUpdate()
	{
		$outlet = Outlet::getInstance();
		
		$p = new OutletTest_Project();
		$p->setName('Project test update');
		
		$outlet->save($p);
		$outlet->clearCache();
		
		$p = $outlet->select('OutletTest_Project');
		$p = $p[0];
		$p->setName('Project test update2');

		$outlet->save($p);
	}
	
	function testDbFunctions()
	{
		$outlet = Outlet::getInstance();
		
		$p1 = new OutletTest_Project();
		$p1->setName('AAAA');
		$outlet->save($p1);
		
		$p2 = new OutletTest_Project();
		$p2->setName('BBBB');
		$outlet->save($p2);
		
		$stmt = $outlet->query('SELECT MAX({p.Name}) as max_project FROM {OutletTest_Project p}');
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$this->assertEquals('BBBB', $data[0]['max_project']);
	}
}