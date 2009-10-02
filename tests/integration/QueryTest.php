<?php

class Integration_QueryTest extends OutletTestCase {
	public function testGetsFromIdentityMapWhenQuerying() {
		$this->connection->execute('INSERT INTO bugs (id, name) VALUES (1, "test")');
		$this->connection->execute('INSERT INTO bugs (id, name) VALUES (2, "test2")');

		$bugs = $this->session->from('Bug')->find();

		$this->assertSame($bugs, $this->session->from('Bug')->find());
	}

	public function setUp() {
		$classes = array(
//			'Project' => array(
//				'table' => 'projects',
//				'props' => array(
//					'id' => array('id', 'int', array('pk' => true)),
//					'name' => array('name', 'varchar')
//				)
//			),
			'Bug' => array(
				'table' => 'bugs',
				'props' => array(
					'id' => array('id', 'int', array('pk' => true)),
					'name' => array('name', 'varchar')
				),
				'useGettersAndSetters' => true
			)
		);
		$this->session = $this->openSession($classes, true);
		$this->connection = $this->session->getConnection();
		$this->connection->execute('CREATE TABLE bugs (id NUMERIC, name TEXT)');
	}
}