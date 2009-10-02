<?php
return array(
	'connection' => array(
		'dsn' => 'sqlite:test.sq3',
		'dialect' => 'sqlite'
	),
	'proxies' => array(
		'autoload' => true
	),
	'classes' => array(
		'User' => array(
			'table' => 'users',
			'props' => array(
				'ID' => array('id', 'int', array('pk'=>true, 'autoIncrement'=>true)),
				'Username' => array('username', 'varchar')
			)
		)
	)
);
