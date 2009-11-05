<?php
return array(
	'connection' => array(
		'dsn' => 'sqlite:'.__DIR__.'/test.sq3',
		'dialect' => 'sqlite'
	),
	'proxies' => array(
		'autoload' => true
	),
	'classes' => array(
		'outlet\samples\model\User' => array(
			'table' => 'users',
			'props' => array(
				'ID' => array('id', 'int', array('pk'=>true, 'autoIncrement'=>true)),
				'Username' => array('username', 'varchar')
			)
		)
	)
);
