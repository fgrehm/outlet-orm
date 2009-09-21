<?php

require_once 'MapperTestCase.php';

class Unit_GettersAndSettersMapperTest extends Unit_MapperTestCase {
	protected $entityName = 'GettersSettersEntity';
	protected $useGettersAndSetters = true;
}

class GettersSettersEntity {
	protected $id;
	protected $name;
	protected $birthdate;
	protected $lastvisit;
	protected $children;
	protected $weight;

	public function  __call($name,  $arguments) {
		$prop = substr($name, 3);
		$action = substr($name, 0, 3);
		if ($action == 'get'){
			return $this->{$prop};
		} else {
			$this->{$prop} = $arguments[0];
		}
	}
}