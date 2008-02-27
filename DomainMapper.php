<?php

class DomainMapper {
	private $conf;
	private $con;
	
	function __construct ( array $conf ) {
		$this->conf = $conf;
		$c = $conf['connection'];
		
		$this->con = new PDO('mysql:host='.$c['host'].';dbname='.$c['database'], $c['username'], $c['password']);
	}
	
	public function save ($obj) {		
		if ($this->isNew($obj)) {
			return $this->insert($obj);
		} else {
			return $this->update($obj);
		}
	}
	
	public function insert ($obj) {
		$table = $this->getTable($obj);	
		$fields = $this->getFields($obj);
		
		$props = array_keys($fields);
		$dbfields = array();
		foreach ($fields as $f) {
			$dbfields[] = $f[0];
		}
		
		$q = "INSER INTO $table ";
		$q .= "(" . implode(', ', $dbfields) . ")";
		$q .= " VALUES ";
		$q .= "(" . implode(', ', str_split(str_repeat('?', count($dbfields)))) . ")";
		
		$stmt = $this->con->prepare($q);
		
		// get the values
		$values = array();
		foreach ($props as $p) {
			$values[] = $obj->$p;
		}

		$stmt->execute($values);
	}
	
	private function getTable($obj) {
		$clazz = get_class($obj);
		return $this->conf['classes'][$clazz]['table'];
	}
	
	private function isNew($obj) {
		$pks = $this->getPkFields( $obj );
		
		if (!count($pks)) throw new Exception('You must configure at least one primary key');
		
		foreach ($pks as $field) {
			if (!$obj->$field) return true;
		}
		return false;
	}
	
	private function getFields ($obj) {
		$fs = array();
		$fields = $this->conf['classes'][get_class($obj)]['fields'];
		foreach ($fields as $key=>$f) {
			if (isset($f[2]) && isset($f[2]['autoIncrement']) && $f[2]['autoIncrement']) continue;
			$fs[$key] = $f;
		}
		
		return $fs;
	}
	
	private function getPkFields( $obj ) {
		$fields = $this->conf['classes'][get_class($obj)]['fields'];
		
		$pks = array();
		foreach ($fields as $key => $f) {
			if (isset($f[2]) && isset($f[2]['pk']) && $f[2]['pk']) $pks[] = $key; 
		}
		
		return $pks;
	}
	
}