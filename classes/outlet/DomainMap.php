<?php

class DomainMap {
	private $con;
	private $map = array();
	private $cache = array();

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
		$obj = new $clazz;
		foreach ($this->map[$clazz] as $m) {
			
		}
	}

	function select ( $clazz, $where_clause ) {
		$d = $this->map[$clazz];
		$table = $d['table'];
		
		// select clause
		$q = "SELECT ";
		foreach ($d['mappings'] as $key=>$value) {
			$cols[] = $table.'.'.$value;
		}
		$q .= implode(', ', $cols) . ' ';
		
		// from clause
		$q .= "FROM " . $d['table'] . " ";
		
		// where clause
		$q .= "WHERE ";
		foreach ($d['mappings'] as $key=>$value) {
			$where_clause = str_replace($clazz.'.'.$key, $table.'.'.$value, $where_clause);
		}
		$q .= $where_clause;
		
		echo $q;
		
		$coll = array();
		foreach ($this->con->query($q, PDO::FETCH_ASSOC) as $row) {
			$obj = new $clazz;
			foreach ($d['mappings'] as $key=>$value) {
				$obj->$key = $row[$value];
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

class DomainMapConfiguration {
	
}

$conf = array(
	'classes' => array(
		'User' => array(
			'table' => 'users',
			'fields' => array(
				'UserId' => array(
					'user_id', 
					'int', 
					array('pk'=>true, 'autoIncrement'=>true)
				),
				'FirstName' => array(
					'first_name',
					'varchar'
				)
			)
		)
	)
);
