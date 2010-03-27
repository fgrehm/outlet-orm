<?php
require_once dirname(__FILE__).'/../OutletTestCase.php';

class SimpleOperationsTest extends OutletTestCase {

	function testCrudOperations() {
		$outlet = Outlet::getInstance();
		$project = new Project;
		$project->setName('Project 1');

		$outlet->save($project);

		// test insert
		$bug = new Bug;
		$bug->Title = 'Test bug';
		$bug->ProjectID = $project->getProjectID();

		$outlet->save($bug);

		$id = $bug->ID;

		$this->assertNotNull( $id, 'Row inserted' );

		// test retrieve
		$bug = $outlet->load('Bug', $id);

		$this->assertTrue($bug instanceof Bug, 'Object is a Bug');
		$this->assertEquals( $bug->Title, 'Test bug', 'Row retrieved' );

		// test update
		$bug->Title = 'New Test Bug';

		$outlet->save($bug);

		$bug = $outlet->load('Bug', $bug->ID);

		$this->assertEquals( $bug->Title, 'New Test Bug', 'Row updated' );

		// test update when adding a relationship entity
		$bug2 = new Bug;
		$bug2->Title = 'Test bug 2';
		$project->addBug( $bug2 );

		$outlet->save($project);

		$project = $outlet->load('Project', $project->getProjectID());

		$this->assertEquals(2, count($project->getBugs()), 'Two rows returned');

		// test assignment of many to one
		$bug3 = new Bug;
		$bug3->Title = 'Bug 3';
		$bug3->setProject( $project );

		$outlet->save($bug3);

		$project2 = new Project;
		$project2->setName('Project 2');
		$outlet->save($project2);

		$bug3->setProject($project2);

		$this->assertEquals($bug3->ProjectID, $project2->getProjectID(), "Bug gets assigned the id of the project on setProject");

	}

	function testNonAutoIncrementingVarcharPrimaryKey () {
		$m = new Machine;
		$m->Name = 'test';
		$m->Description = 'Test machine';

		$outlet = Outlet::getInstance();

		// test insert
		$outlet->save( $m );

		$outlet->clearCache();

		// test loading
		$machine = $outlet->load('Machine', $m->Name);

		$this->assertNotNull($machine, "Machine was saved and retrieved");

		// test update
		$machine->Description = 'Updated description';

		$outlet->save($machine);
	}

	function testDefaults () {
		// make sure that the created date of the project is assigned
		// as per the defaultExpr setting
		// also make sure that the status or the project is set
		// as per the default setting
		$project = new Project;
		$project->setName('Test Project');

		$outlet = Outlet::getInstance();

		$outlet->save($project);
		$now = time();

		$outlet->clearCache();

		$project = $outlet->load('Project', $project->getProjectID());

		// allow for a 1 sec delay
		$this->assertTrue( $now - ((int) $project->getCreatedDate()->format('U')) < 2 );

		$this->assertEquals($project->getStatusID(), 1);
		$this->assertEquals($project->getDescription(), 'Default Description');
	}

	function testDelete () {
		$project = new Project;
		$project->setName('Test Project');

		$outlet = Outlet::getInstance();

		$outlet->save($project);

		$project = $outlet->load('Project', $project->getProjectID());

		$project_id = $project->getProjectID();

		$outlet->delete('Project', $project_id);

		// I'll have to do something better than this
		// when I get a chance
		$project = $outlet->load('Project', $project_id);

		$this->assertTrue(is_null($project), 'Project was deleted');
	}

	function testUpdate () {
		$p = new Project;
		$p->setName('Project test update');

		$outlet = Outlet::getInstance();

        $outlet->save($p);
        $id = $p->getProjectID();

        $p->setName('Project test update2');
		$p->setCreatedDate(new DateTime('2009-01-25'));

		$outlet->save($p);
        $outlet->clearCache();

		$stmt = $outlet->query('SELECT * FROM projects');
		//print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

        $p = $outlet->load('Project', $id);

        $this->assertEquals($p->getName(), 'Project test update2');
        $this->assertEquals($p->getCreatedDate()->format('Y-m-d'), '2009-01-25');
	}

    function testAutoIncrementWithGettersAndSettersEnabled(){
        $p = new Project;
		$p->setName('Project test update');

		$outlet = Outlet::getInstance();

		$outlet->save($p);
        $this->assertNotNull($p->getProjectID());
    }

    function testFloatProperty(){
        $outlet = Outlet::getInstance();

        $project = new Project;
		$project->setName('Project 1');

		$outlet->save($project);

		// test insert
		$bug = new Bug;
		$bug->Title = 'Test bug';
        $bug->setProject($project);

		$outlet->save($bug);

        // Tests default value
        $this->assertEquals($bug->TimeToFix, 2000.000001);

        $bug->TimeToFix = 100000.000001;
        $outlet->save($bug);

        $id = $bug->ID;
        // Clears cache so we guarantee that the values comes from db
        $outlet->clearCache();

        $bug = $outlet->load('Bug', $id);
        $this->assertEquals($bug->TimeToFix, 100000.000001);
    }

    function testDataTypes(){
        $outlet = Outlet::getInstance();

        $project = new Project;
		$project->setName('Project 1');

		$outlet->save($project);
        $project_id = $project->getProjectID();

		// test insert
		$bug = new Bug;
		$bug->Title = 'Test bug';
        $bug->setProject($project);

		$outlet->save($bug);
        $bug_id = $bug->ID;
        // Clears cache so we guarantee that the values comes from db
        $outlet->clearCache();

        $bug = $outlet->load('Bug', $bug_id);
        $project = $outlet->load('Project', $project_id);
        $this->assertTrue(is_string($bug->Title));
        $this->assertTrue(is_int($bug->ID));
        $this->assertTrue(is_float($bug->TimeToFix));
        $this->assertTrue($project->getCreatedDate() instanceof DateTime);
    }

	function testSelectAndUpdate() {
		$outlet = Outlet::getInstance();

		$p = new Project;
		$p->setName('Project test update');
		$outlet->save($p);
		$outlet->clearCache();

		$p = $outlet->select('Project');
		$p = $p[0];

		$p->setName('Project test update2');

		// This should raise an exception as reported on:
		// http://groups.google.com/group/outlet-orm/browse_thread/thread/edce030aa444befa
		$outlet->save($p);
		$this->assertTrue(true);
	}

	function testDbFunctions () {
		$outlet = Outlet::getInstance();

		$p1 = new Project;
		$p1->setName('AAAA');

		$outlet->save($p1);

		$p2 = new Project;
		$p2->setName('BBBB');

		$outlet->save($p2);

		$stmt = $outlet->query('SELECT MAX({p.Name}) as max_project FROM {Project p}');
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$this->assertEquals($data[0]['max_project'], 'BBBB');
	}
}

