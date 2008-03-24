<?php

class OutletMapper {
	private $obj;
	private $conf;
	private $outlet;
	private $clazz;
	private $con;

	function __construct (&$obj) {
		if (!is_object($obj)) throw new Exception('You must pass and object');
		if ($obj instanceof OutletMapper) throw new Exception('You passed and OutletMapper object');

		$this->obj = &$obj;
		$this->clazz = str_replace('_OutletProxy', '', get_class($obj));

		$outlet = Outlet::getInstance();

		$this->con = $outlet->getConnection();

		$conf = $outlet->getConfiguration();

		$this->conf = &$conf['classes'][$this->clazz];
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

		if ( $this->getPK() || $outlet->get($this->clazz, $this->getPK()) ) return false;

		return true;
	}

	public function getPK() {
		$pks = array();

		$pk_prop = self::getPkProp($this->clazz);

		return $this->obj->$pk_prop;
	}

	static function getPkProp ( $clazz ) {
		$conf = Outlet::getInstance()->getConfiguration();
		foreach ($conf['classes'][$clazz]['props'] as $key=>$f) {
			if (@$f[2]['pk'] == true) {
				$pks[] = $key;
			}

			if (!count($pks)) throw new Exception('You must specified at least one primary key');

			if (count($pks) == 1) {
				return $pks[0];
			} else {
				return $pks;
			}
		}

	}


	private function saveOneToMany () {
		foreach ($this->conf['associations'] as $assoc) {
			$type = $assoc[0];
			$entity = $assoc[1];

			if ($type != 'one-to-many') continue;

			$foreign_fk = $assoc[2]['foreign_key'];

			$getter = "get{$entity}s";

			$children = $this->obj->$getter(null);
			foreach ($children as &$child) {
				$child->$foreign_fk = $this->getPK();

				$mapped = new self($child);
				if ($mapped->isNew()) {
					$mapped->save();
				}
			}

			$setter = "set{$entity}s";
			$this->obj->$setter( $children );
		}
	}

	private function saveManyToOne () {
		foreach ($this->conf['associations'] as $assoc) {
			$type = $assoc[0];
			$entity = $assoc[1];

			if ($type != 'many-to-one') continue;

			$local_fk = $assoc[2]['local_key'];

			$method = "get$entity";
			$ent = $this->obj->$method();

			if ($ent) {
				// wrap with a mapper
				$mapped = new self($ent);

				if ($mapped->isNew()) {
					$mapped->save();
					$this->obj->$local_fk = $mapped->getPK();
				}
			}
		}

	}

	private function insert () {
		$this->saveManyToOne();

		$props = array_keys($this->conf['props']);
		$table = $this->conf['table'];

		// grab insert fields
		$insert_fields = array();
		$insert_props = array();
		foreach ($this->conf['props'] as $prop=>$f) {
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
		foreach ($this->conf['props'] as $key=>$f) {
			$field = $key;
			if (@$f[2]['autoIncrement']) {
				$id = $this->con->lastInsertId();
				$proxy->$field = $id;
			} else {
				$proxy->$field = $this->obj->$field;
			}
		}
		$this->obj = $proxy;

		$this->saveOneToMany();
	}

	public function update() {
		$this->saveManyToOne();

		$table = $this->conf['table'];

		$q = "UPDATE $table \n";
		$q .= "SET \n";

		$ups = array();
		foreach ($this->conf['props'] as $key=>$f) {
			// skip primary key 
			if (@$f[2]['pk']) continue;

			$value = $this->con->quote( $this->obj->$key );
			$ups[] = "  $f[0] = $value";
		}
		$q .= implode(", \n", $ups);

		$q .= "\nWHERE ";

		$clause = array();
		foreach ($this->conf['props'] as $key=>$pk) {
			// if it's not a primary key, skip it
			if (!@$pk[2]['pk']) continue;

			$value = $this->con->quote( $this->obj->$key );
			$clause[] = "$pk[0] = {$this->obj->$key}";
		}
		$q .= implode(' AND ', $clause);

		$this->con->exec($q);

		$this->saveOneToMany();
	}

	function toArray () {
		return (array) $this->obj;
	}
	
}

