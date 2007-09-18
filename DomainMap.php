<?php

class DomainMap {
	private $con;
	private $map = array();

	function __construct (PDO $pdo) {
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->con = $pdo;		
	}

	function configure ( array $map ) {
		$this->map = $map;
	}

	function createQuery () {
		require_once 'DomainQuery.php';
		return new DomainQuery( $this );
	}

	function getMap () {
		return $this->map;
	}

	function save ( $obj ) {
		// get definition
		if ($obj->id) {
			return $this->update( $obj );
		} else {
			return $this->insert( $obj );
		}	
	}

	function get ( $clazz, $pk ) {

	}

	function select ( DomainQuery $q ) {
		$res = $this->con->query( $q->toSQL() );

		$clazz = $q->getFrom();
		$coll = array();
		foreach ($res as $row) {
			$obj = new $clazz;
			foreach ($this->map[$clazz]['mappings'] as $key=>$mapping) {
				$obj->$key = $row[$mapping['column']];
			}
			$coll[] = $obj;
		}
		return $coll;
	}

	private function insert ( $obj ) {
		$def = $this->map[get_class($obj)];
	
		$data = array();
		foreach ($obj as $key=>$val) {
			$data[$key] = $val;
		}

		$fields = array();
		$values = array();
		foreach ($def['mappings'] as $key=>$val) {
			if (@$val['autoincrement']) continue;
			$fields[] = $val['column'];
			$values[] = $data[$key];
		}
		$q = "INSERT INTO " . $def['table'] . " (" . implode(", ", $fields) . ") VALUES ('" . implode("', '", $values) . "')";

		echo $q;

		$this->con->exec($q);
	}

	private function update ( $obj ) {
	}
}


