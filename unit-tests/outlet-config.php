<?php
return array(
	'connection' => array(
		'dsn' => 'sqlite:test.sq3'	
	),
	'classes' => array(
		'Bug' => array(
			'table' => 'bugs',
			'props' => array(
				'ID' 		=> array('id', 'int', array('pk'=>true, 'autoIncrement'=>true)),
				'Title'		=> array('title', 'varchar'),
				'ProjectID' => array('project_id', 'int'),
			),
			'associations' => array(
				array('many-to-one', 'Project', array('key'=>'ProjectID'))
			)
		),
		'Project' => array(
			'table' => 'projects',
			'props' => array(
				'ID' 	=> array('id', 'int', array('pk'=>true, 'autoIncrement'=>true)),
				'Name'	=> array('name', 'varchar')
			),
			'associations' => array(
				array('one-to-many', 'Bug', array('key'=>'ProjectID'))
			)
		),
		'User' => array(
			'table' => 'users',
			'props' => array(
				'ID' 		=> array('id', 'int', array('pk'=>true, 'autoIncrement'=>true)),
				'FirstName' => array('first_name', 'varchar'),
				'LastName'	=> array('last_name', 'varchar')
			)
		)
	)
);
