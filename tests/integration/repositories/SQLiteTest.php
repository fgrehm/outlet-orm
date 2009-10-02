<?php

class Integration_Repositories_SQLiteTest extends OutletTestCase {
	public function testCRUD() {
		$this->create(new Project('new project', 10));
		$loaded = $this->repository->get('Project', 10);

		$this->assertEquals(new Project_OutletProxy('new project', 10), $loaded);

		$loaded->name = 'updated';
		$this->repository->update($loaded);

		$updated = $this->repository->get('Project', 10);
		$this->assertEquals('updated', $updated->name);

		$this->repository->remove($loaded);

		$this->assertNull($this->repository->get('Project', 10));
	}

	public function testCRUDUsingGettersAndSetters() {
		$this->create(new Bug('name', 1));
		$loaded = $this->repository->get('Bug', 1);

		$this->assertEquals(new Bug_OutletProxy('name', 1), $loaded);

		$loaded->setName('updated');
		$this->repository->update($loaded);

		$updated = $this->repository->get('Bug', 1);
		$this->assertEquals('updated', $updated->getName());

		$this->repository->remove($loaded);

		$this->assertNull($this->repository->get('Bug', 1));
	}

	public function testCompositePKCRUD() {
		$this->create(new Composite(1, '2'));
		$loaded = $this->repository->get('Composite', array(1, '2'));

		$this->assertEquals(new Composite_OutletProxy(1, '2'), $loaded);

		$loaded->setOtherPK('updated');
		$this->repository->update($loaded);

		$updated = $this->repository->get('Composite', array(1, 'updated'));
		$this->assertEquals('updated', $updated->getOtherPK());

		$this->repository->remove($loaded);

		$this->assertNull($this->repository->get('Composite', array(1, 'updated')));
	}

	public function testQuery() {
		$this->_testQuery('Project');
		$this->_testQuery('Bug');
	}

	public function testUpdateDirtyValuesOnly() {
		$this->create(new Project('name', 1));
		$project = $this->repository->get('Project', 1);
		$project->name = 'new name';
		$this->session->getConnection()->execute('UPDATE projects SET description = "project description" WHERE id = 1');
		$this->repository->update($project);

		$this->session->clear();

		$project = $this->repository->get('Project', 1);
		$this->assertEquals('new name', $project->name);
		$this->assertEquals('project description', $project->description);
	}

	protected function _testQuery($class) {
		$this->create(array(
			new $class('value', 10),
			new $class('other value', 1),
			new $class('some other value', 4)
		));
		$query = new OutletQuery($class);
		$proxy = $class.'_OutletProxy';
		$expected = array(
			new $proxy('value', 10),
			new $proxy('other value', 1),
			new $proxy('some other value', 4)
		);

		$this->assertEquals($expected, $this->repository->query($query));
	}

	protected function create($objects) {
		$objects = is_array($objects) ? $objects : array($objects);
		foreach ($objects as $obj)
			$this->repository->add($obj);
	}

	public function setUp() {
		$classes = array(
			'Project' =>
				array(
					'table' => 'projects',
					'props' => array(
						'id' => array('id', 'int', array('pk' => true)),
						'name' => array('project_name', 'varchar'),
						'description' => array('description', 'varchar'))
				),
			'Bug' =>
				array(
					'table' => 'bugs',
					'props' => array(
						'ID' => array('id', 'int', array('pk' => true)),
						'Name' => array('name', 'varchar')),
					'useGettersAndSetters' => true
				),
			'Composite' =>
				array(
					'table' => 'composite_test',
					'props' => array(
						'PK' => array('id', 'int', array('pk' => true)),
						'OtherPK' => array('name', 'varchar', array('pk' => true))),
					'useGettersAndSetters' => true
				),
		);
		$this->session = $this->openSession($classes, true);
		$this->repository = new OutletRepositorySQLite($this->session);
		$this->session->getConnection()->execute('CREATE TABLE bugs (id NUMERIC, name TEXT)');
		$this->session->getConnection()->execute('CREATE TABLE projects (id NUMERIC, description TEXT, project_name TEXT)');
		$this->session->getConnection()->execute('CREATE TABLE composite_test (id NUMERIC, name TEXT)');
	}
}

class Composite {
	private $pk;
	private $otherPK;

	public function __construct($pk1 = 0, $pk2 = 0) {
		$this->pk = $pk1;
		$this->otherPK = $pk2;
	}

	public function getPK() {
		return $this->pk;
	}
	public function setPK($pk) {
		$this->pk = $pk;
	}

	public function getOtherPK() {
		return $this->otherPK;
	}
	public function setOtherPK($otherPK) {
		$this->otherPK = $otherPK;
	}
}

class Composite_OutletProxy extends Composite implements OutletProxy {

}