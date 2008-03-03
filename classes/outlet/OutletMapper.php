<?php

class OutletMapper {
	private $obj;
	private $conf;
	private $outlet;
	private $clazz;
	private $con;

	function __construct (&$obj, &$conf) {
		$this->obj = &$obj;
		$this->clazz = str_replace('_OutletProxy', '', get_class($obj));
		$this->conf = &$conf;
		$this->con = Outlet::getInstance()->getConnection();
	}

	function save () {
		if ($this->isNew()) {
			return $this->insert();
		} else {
			return $this->update();
		}
	}

	private function isNew() {
		// if it's not in the identity map, it's new
		$outlet = Outlet::getInstance();

		if ( $outlet->get($this->clazz, $this->getPK()) ) return false;

		return true;
	}

	private function getPK() {
		$pks = array();
		foreach ($this->conf['fields'] as $key=>$f) {
			if (@$f[2]['pk'] == true) {
				$pks[] = $this->obj->$key;
			}

			if (!count($pks)) throw new Exception('You must specified at least one primary key');

			if (count($pks) == 1) {
				return $pks[0];
			} else {
				return $pks;
			}
		}
	}

	private function insert () {	
		$props = array_keys($this->conf['fields']);
		$table = $this->conf['table'];

		// grab insert fields
		$insert_fields = array();
		$insert_props = array();
		foreach ($this->conf['fields'] as $prop=>$f) {
			// skip autoIncrement fields
			if (@$f[2]['autoIncrement']) continue;

			$insert_props[] = $prop;
			$insert_fields[] = $f[0];
		}
		
		$q = "INSERT INTO $table ";
		$q .= "(" . implode(', ', $insert_fields) . ")";
		$q .= " VALUES ";
		$q .= "(" . implode(', ', str_split(str_repeat('?', count($insert_fields)))) . ")";
		
		$stmt = $this->con->prepare($q);
	
		// get the values
		$values = array();
		foreach ($insert_props as $p) {
			$values[] = $this->obj->$p;
		}

		$stmt->execute($values);

		$proxy_class = "{$this->clazz}_OutletProxy";
		$proxy = new $proxy_class;
		foreach ($this->conf['fields'] as $key=>$f) {
			$field = $key;
			if (@$f[2]['autoIncrement']) {
				$id = $this->con->lastInsertId();
				$proxy->$field = $id;
			} else {
				$proxy->$field = $this->obj->$field;
			}
		}
		$this->obj = $proxy;
	}
}

