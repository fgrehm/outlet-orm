<?php
return array(
	'connection' => array(
		//'dsn' => 'sqlite:test.sq3',
		'pdo' => new PDO('sqlite::memory:'),
		'dialect' => 'sqlite'
	),
	'classes' => array(
		'OutletTest_Address' => array(
			'table' => 'addresses',
			'plural' => 'Addresses',
			'props' => array(
				'AddressID'	=> array('id', 'int', array('pk'=>true, 'autoIncrement'=>true)),
				'UserID'	=> array('user_id', 'int'),
				'Street'	=> array('street', 'varchar')
			)
		),
		'OutletTest_Bug' => array(
			'table' => 'bugs',
			'plural' => 'Bugs',
			'props' => array(
				'ID' 	=> array('id', 'int', array('pk'=>true, 'autoIncrement'=>true)),
				'Title'		=> array('title', 'varchar'),
				'ProjectID' => array('project_id', 'int'),
                'TimeToFix' => array('time_to_fix', 'float', array('default' => 2000.000001)),
				'Test_One'	=> array('test_one', 'int') // test an identifier with an underscore on it
			),
			'associations' => array(
				array('many-to-one', 'OutletTest_Project', array('key'=>'ProjectID', 'name' => 'Project'))
			)
		),
		'OutletTest_Machine' => array(
			'table' => 'machines',
			'plural' => 'Machines',
			'props' => array(
				'Name' 			=> array('name', 'varchar', array('pk'=>true)),
				'Description'	=> array('description', 'varchar')
			)
		),
		'OutletTest_Project' => array(
			'table' => 'projects',
			'plural' => 'Projects',
			'props' => array(
				'ProjectID' 	=> array('id', 'int', array('pk'=>true, 'autoIncrement'=>true)),
				'Name'			=> array('name', 'varchar'),
				'CreatedDate' 	=> array('created_date', 'datetime', array('defaultExpr'=>"datetime(current_timestamp, 'localtime')")),
				'StatusID'		=> array('status_id', 'int', array('default'=>1)),
				'Description'	=> array('description', 'varchar', array('default'=>'Default Description'))
			),
			'associations' => array(
				array('one-to-many', 'OutletTest_Bug', array('key'=>'ProjectID', 'name' => 'Bug'))
			),
			'useGettersAndSetters' => true
		),
		'OutletTest_User' => array(
			'table' => 'users',
			'plural' => 'Users',
			'props' => array(
				'UserID' 	=> array('id', 'int', array('pk'=>true, 'autoIncrement'=>true)),
				'FirstName' => array('first_name', 'varchar'),
				'LastName'	=> array('last_name', 'varchar')
			),
			'associations' => array(
				array('one-to-many', 'OutletTest_Address', array('key'=>'UserID', 'name'=>'WorkAddress', 'plural'=>'WorkAddresses', 'name' => 'Address')),
				array('many-to-many', 'OutletTest_Bug', array('table'=>'watchers', 'tableKeyLocal'=>'user_id', 'tableKeyForeign'=>'bug_id', 'name' => 'Bug'))
			)
		),
		'OutletTest_Profile' => array(
			'table' => 'profiles',
			'plural' => 'profiles',
			'props' => array(
				'ProfileID' 	=> array('id', 'int', array('pk'=>true, 'autoIncrement'=>true)),
				'UserID' 		=> array('user_id', 'int')
			),
			'associations' => array(
				array('one-to-one', 'OutletTest_User', array('key'=>'UserID', 'refKey' => 'UserID', 'name' => 'User'))
			),
			'useGettersAndSetters' => true
		)
	)
);