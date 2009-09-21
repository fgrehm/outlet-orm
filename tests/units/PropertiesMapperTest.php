<?php

require_once 'MapperTestCase.php';

class Unit_PropertiesMapperTest extends Unit_MapperTestCase {
	protected $entityName = 'PropertyEntity';
	protected $useGettersAndSetters = false;
}

class PropertyEntity {
	public $id;
	public $name;
	public $birthdate;
	public $lastvisit;
	public $children;
	public $weight;
}