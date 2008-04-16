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

		if ( $this->getPK() && $outlet->get($this->clazz, $this->getPK()) ) return false;

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
		foreach ((array) @$this->conf['associations'] as $assoc) {
			$type = $assoc[0];
			$entity = $assoc[1];

			if ($type != 'one-to-many') continue;

			$key = $assoc[2]['key'];

			$getter = "get{$entity}s";

			$children = $this->obj->$getter(null);
			foreach ($children as &$child) {
				$child->$key = $this->getPK();

				$mapped = new self($child);
				if ($mapped->isNew()) {
					$mapped->save();
				}
			}

			$setter = "set{$entity}s";
			$this->obj->$setter( $children );
		}
	}


	private function saveManyToMany () {
		foreach ((array) @$this->conf['associations'] as $assoc) {
			$type = $assoc[0];
			$entity = $assoc[1];

			if ($type != 'many-to-many') continue;

			$key_column = $assoc[2]['key_column'];
			$ref_column = $assoc[2]['ref_column'];
			$table = $assoc[2]['table'];
			$name = $assoc[2]['name'];

			$getter = "get{$name}s";

			$children = $this->obj->$getter(null);
			foreach ($children as &$child) {
				$mapped = new self($child);
				if ($mapped->isNew()) {
					$mapped->save();
				}

				$q = "
					INSERT INTO $table ($key_column, $ref_column) 
					VALUES (?, ?)
				";	

				$stmt = $this->con->prepare($q);
				
				$stmt->executeUpdate(array($this->getPK(), $mapped->getPK()));	
			}

			$setter = "set{$name}s";
			$this->obj->$setter( $children );
		}
	}

	private function saveManyToOne ($entity, array $settings) {
		$key = $settings['key'];
		$name = (@$settings['name'] ? $settings['name'] : $entity);

		$method = "get$name";
		$ent = $this->obj->$method();

		if ($ent) {
			// wrap with a mapper
			$mapped = new self($ent);

			if ($mapped->isNew()) {
				$mapped->save();
				$this->obj->$key = $mapped->getPK();
			}
		}
	}

	private function insert () {
		foreach (@$this->conf['associations'] as $assoc) {
			if ($assoc[0] == 'many-to-one') $this->saveManyToOne($assoc[1], $assoc[2]);
		}

		$props = array_keys($this->conf['props']);
		$table = $this->conf['table'];

		// grab insert fields
		$insert_fields = array();
		$insert_props = array();
		$insert_defaults = array();
		foreach ($this->conf['props'] as $prop=>$f) {
			// skip autoIncrement fields
			if (@$f[2]['autoIncrement']) continue;

			$insert_props[] = $prop;
			$insert_fields[] = $f[0];
			$insert_defaults[] = @$f[2]['defaultExpr'];
		}
		
		$q = "INSERT INTO $table ";
		$q .= "(" . implode(', ', $insert_fields) . ")";
		$q .= " VALUES ";

		// question marks for each value
		// except for defaults
		$values = array();
		foreach ($insert_fields as $key=>$f) {
			if ($insert_defaults[$key]) $values[] = $insert_defaults[$key];
			else $values[] = '?';
		}	
		$q .="(" . implode(', ', $values) . ")";
	
		$stmt = $this->con->prepare($q);
	
		// get the values
		$values = array();
		foreach ($insert_props as $key=>$p) {
			// skip the defaults
			if ($insert_defaults[$key]) continue;

			$values[] = $this->obj->$p;
		}
	
		$stmt->execute($values);

		// create a proxy
		$proxy_class = "{$this->clazz}_OutletProxy";
		$proxy = new $proxy_class;
		
		// copy the properties to the proxy
		foreach ($this->conf['props'] as $key=>$f) {
			$field = $key;
			if (@$f[2]['autoIncrement']) {
				$id = $this->con->lastInsertId();
				$proxy->$field = $id;
			} else {
				$proxy->$field = $this->obj->$field;
			}
		}
	
		// copy the associated objects to the proxy
		foreach ((array) @$this->conf['associations'] as $a) {
			if ($a[0] == 'one-to-many' || $a[0] == 'many-to-many') {
				$name = (@$a[2]['name'] ? $a[2]['name'] : $a[1]);
				$setter = "set{$name}s";
				$getter = "get{$name}s";

				$ref = $this->obj->$getter();
				if ($ref) $proxy->$setter( $this->obj->$getter() );
			}
		}
		$this->obj = $proxy;

		$this->saveOneToMany();
	}

	public function update() {
		// this first since this references the key
		foreach (@$this->conf['associations'] as $assoc) {
			if ($assoc[0] == 'many-to-one') $this->saveManyToOne($assoc[1], $assoc[2]);
		}

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

		// these last since they reference the key
		$this->saveOneToMany();
		$this->saveManyToMany();
	}

	function toArray () {
		return (array) $this->obj;
	}
	
}

