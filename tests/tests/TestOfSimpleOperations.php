<?php

class TestOfSimpleOperations extends OutletTestCase {

	function testCrudOperations() {
		$outlet = Outlet::getInstance();

		// test insert
		$bug = new Bug;
		$bug->Title = 'Test bug';
		$bug->ProjectID = 1;

		$outlet->save($bug);

		$this->assertEqual( $bug->ID, 1 , 'Row inserted' );

		// test retrie
		$bug = $outlet->load('Bug', 1);

		$this->assertEqual( $bug->Title, 'Test bug', 'Row retrieved' );

		// test update
		$bug->Title = 'New Test Bug';	

		$outlet->save($bug);

		$bug = $outlet->load('Bug', 1);

		$this->assertEqual( $bug->Title, 'New Test Bug', 'Row updated' );
	}

}
