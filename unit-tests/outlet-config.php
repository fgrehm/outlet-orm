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
				'ProjectID' => array('project_id', 'int')
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
		)
	)
);
