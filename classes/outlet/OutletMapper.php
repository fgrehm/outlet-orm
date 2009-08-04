<?php

class OutletMapper {
	const IDENTIFIER_PATTERN = '/\{[a-zA-Z0-9_]+(( |\.)[a-zA-Z0-9_]+)*\}/';

	//static $conf;
	public $map = array();
	
	/**
	 * @var OutletConfig
	 */
	private $config;

	function __construct (OutletConfig $config) {
		$this->config = $config;
	}

	/**
	 * Return the entity class of an object as defined in the config
	 * 
	 * For example, it will return 'User' when passed an instance of User or User_OutletProxy
	 * 
	 * @param $obj
	 * @return string
	 */
	static function getEntityClass ($obj) {
		if ($obj instanceof OutletProxy) {
			return substr(get_class($obj), 0, -(strlen('_OutletProxy')));
		} else {
			return get_class($obj);
		}
	}

	/**
	 * Persist an entity to the database by performing either an INSERT or an UPDATE
	 * 
	 * @param $obj
	 */
	public function save (&$obj) {
		if (self::isNew($obj)) {
			return $this->insert($obj);
		} else {
			return $this->update($obj);
		}
	}

	/**
	 * Determine if an object has been saved by seeing if it's actually a proxy
	 * 
	 * @param $obj
	 * @return bool
	 */
	static function isNew($obj) {
		return ! $obj instanceof OutletProxy;
	}

	/**
	 * Set the primary key of an entity
	 * 
	 * @param $obj
	 * @param $pk
	 * @return unknown_type
	 */
	public function setPk ($obj, $pk) {
		if (!is_array($pk)) $pk = array($pk);

		$pk_props = $this->config->getEntity(self::getEntityClass($obj))->getPkFields();

		if (count($pk)!=count($pk_props)) throw new OutletException('You must pass the following pk: ['.implode(',', $pk_props).'], you passed: ['.implode(',', $pk).']');

		foreach ($pk_props as $key=>$prop) {
			$obj->$prop = $pk[$key];
		}
	}

	/**
	 * Get the PK values for the entity, casted to the type defined on the config
	 *
	 * @return array
	 */
	public function getPkValues ($obj) {
		return $this->getPkValuesForObject($obj);
	}

	/**
	 * @param $obj
	 * @return array
	 */
	public function getPkValuesForObject ($obj) {
		$pks = array();
		foreach ($this->config->getEntity(self::getEntityClass($obj))->getProperties() as $key=>$p) {
			if (isset($p[2]['pk']) && $p[2]['pk']) {
				$value = self::getProp($obj, $key);

				// cast it if the property is defined to be an int
				if ($p[1]=='int') $value = (int) $value;
				
				$pks[$key] = $value;
			}
		}
		return $pks;
	}

    /**
     * @param string $cls Entity class
     * @param mixed $pk Primary key
     * @return Object
     */
	public function load ($cls, $pk) {
	   if (!$pk) throw new OutletException("Must pass a valid primary key value, passed: ".var_export($pk, true));

		if (!is_array($pk)) $pks = array($pk);
		else $pks = $pk;

		// try to retrieve it from the cache first
		$data = $this->get($cls, $pks);

		// if it's there
		if ($data) {
			$obj = $data['obj'];

		// else, populate it from the database
		} else {
			// create a proxy
			$proxyclass = "{$cls}_OutletProxy";

			$obj = new $proxyclass;

			$props_conf = $this->config->getEntity($cls)->getProperties();
			
			$props = array();
			foreach ($props_conf as $key=>$conf) {
				// if it's sql we must specify an alias
				if (isset($conf[2]) && isset($conf[2]['sql'])) {
					$props[] = '{'.$cls.'.'.$key.'} as ' . $conf[0];
				} else {
					$props[] = '{'.$cls.'.'.$key.'}';
				}
			}

			// craft select
			$q = "SELECT ";
			$q .= implode(', ', $props) . "\n";
			$q .= "FROM {".$cls."} \n";

			$pk_props = $this->config->getEntity(self::getEntityClass($obj))->getPkFields();

			$pk_q = array();
			foreach ($pk_props as $pkp) {
				$pk_q[] = '{'.$cls.'.'.$pkp.'} = ?';
			}

			$q .= "WHERE " . implode(' AND ', $pk_q);

			$q = $this->processQuery($q);

			$stmt = $this->config->getConnection()->prepare($q);

			$stmt->execute(array_values($pks));

			$row = $stmt->fetch(PDO::FETCH_ASSOC);

			// if there's matching row,
			// return null
			if (!$row) return null;
			
			$this->populateObject($cls, $obj, $row);

			// add it to the cache
			$this->set($cls, $this->getPkValues($obj), array(
				'obj' => $obj,
				'original' => $this->toArray($obj)
			));
		}

		return $obj;
	}
	
	/**
	 * Populate an object with the values from an associative array indexed by column names
	 * 
	 * @param array $clazz Class of the entity
	 * @param $obj Instance of the entity (probably brand new) or a subclass
	 * @param array $values Associative array indexed by column name
	 * @return object 
	 */
	public function populateObject ($clazz, $obj, array $values) {
		$this->castRow($clazz, $values);

		$entity = $this->config->getEntity($clazz);
		$fields = $entity->getProperties();
		foreach ($fields as $key=>$f) {
			if (!array_key_exists($f[0], $values)) throw new OutletException("Field [$f[0]] defined in the config is not defined in table [".$entity->getTable()."]");

			$this->setProp($obj, $key, $values[$f[0]]);
		}

		return $obj;
	}

	/**
	 * Cast the values of a row coming from the database using the types defined in the config
	 * 
	 * @param string $clazz
	 * @param array $row
	 */
	public function castRow ($clazz, array &$row) {
		foreach ($this->config->getEntity($clazz)->getProperties() as $key=>$p) {
			$column = $p[0];

			if (!array_key_exists($column, $row)) throw new OutletException('No value found for ['.$column.'] in row ['.var_export($row, true).']');

			// cast if it's anything other than a string
			$row[$column] = self::toPhpValue($p, $row[$column]);
		}
	}

	public function getPkProp ( $obj ) {
		return $this->config->getEntity(self::getEntityClass($obj))->getPkField();
	}

	public function saveOneToMany ($obj) {
		$conf = $this->config->getEntity(self::getEntityClass($obj));

		$pks = $this->getPkValues($obj);

		foreach ($conf->getAssociations() as $assoc) {
			if ($assoc->getType() != 'one-to-many') continue;

			$key 		= $assoc->getKey();
			$getter 	= $assoc->getGetter();
			$setter		= $assoc->getSetter();
			$foreign	= $assoc->getForeign();

			/* @var $children Collection */
			$children = $obj->$getter(null);

			if (is_null($children)) continue;

			// if we don't have an OutletCollection yet
			if (! $children instanceof OutletCollection) {
				$arr = $children->getArrayCopy();

				/* @var $children OutletCollection */
				$children = $obj->$getter();
				$children->exchangeArray($arr);
			}

			// if removing all connections
			if ($children->isRemoveAll()) {
			// TODO: Make it work with composite keys
				$q = $this->processQuery('DELETE FROM {'.$foreign.'} WHERE {'.$foreign.'.'.$assoc->getKey().'} = ?');
				$stmt = $this->config->getConnection()->prepare($q);
				$stmt->execute($pks);
			}

			foreach (array_keys($children->getArrayCopy()) as $k) {
			// TODO: make it work with composite keys
				$this->setProp($children[$k], $key, current($pks));
				$this->save($children[$k]);
			}

			$obj->$setter( $children );
		}
	}


	private function saveManyToMany ($obj) {
		$con = $this->config->getConnection();
		$pks = $this->getPkValues($obj);

		foreach ($this->config->getEntity(self::getEntityClass($obj))->getAssociations() as $assoc) {
			if ($assoc->getType() != 'many-to-many') continue;

			$key_column = $assoc->getTableKeyLocal();
			$ref_column = $assoc->getTableKeyForeign();
			$table = $assoc->getLinkingTable();
			$name = $assoc->getForeignName();

			$getter	 = $assoc->getGetter();
			$setter		= $assoc->getSetter();

			$children = $obj->$getter();

			// if removing all connections
			if ($children->isRemoveAll()) {
			// TODO: Make it work with composite keys
				$q = "DELETE FROM $table WHERE $key_column = ?";

				$stmt = $con->prepare($q);

				$stmt->execute(array_values($pks));
			}

			$new = $children->getLocalIterator();

			foreach ($new as $child) {
				if ($child instanceof OutletProxy) {
					$child_pks = $this->getPkValues($child);
					$id = current($child_pks);
				} else {
					$id = $child;
				}

				$q = "
					INSERT INTO $table ($key_column, $ref_column) 
					VALUES (?, ?)
					";

				$stmt = $con->prepare($q);

				$stmt->execute(array(current($pks), $id));
			}

			$obj->$setter( $children );
		}
	}

	private function saveManyToOne ($obj) {
		$conf = $this->config->getEntity(self::getEntityClass($obj));

		foreach ($conf->getAssociations() as $assoc) {
			if ($assoc->getType() != 'many-to-one') continue;

			$key 	= $assoc->getKey();
			$refKey	= $assoc->getRefKey();
			$getter = $assoc->getGetter();

			$ent = $obj->$getter();

			if ($ent) {
				if (self::isNew($ent)) self::save($ent);

				self::setProp($obj, $key, self::getProp($ent, $refKey));
			}
		}
	}

	function saveOneToOne ($obj) {
		$conf = $this->config->getEntity(self::getEntityClass($obj));

		foreach ($conf->getAssociations() as $assoc) {
			if ($assoc->getType() != 'one-to-one') continue;

			$key 	= $assoc->getKey();
			$refKey	= $assoc->getRefKey();
			$getter = $assoc->getGetter();

			$ent = $obj->$getter();

			if ($ent) {
				if (self::isNew($ent)) $this->save($ent);

				$obj->$key = $ent->$refKey;
			}
		}
	}

	public function insert (&$obj) {
		$con = $this->config->getConnection();

		$entity = $this->config->getEntity(self::getEntityClass($obj));

		$this->saveOneToOne( $obj );
		$this->saveManyToOne( $obj );

		$properties = $entity->getProperties();

		$props = array_keys($properties);
		$table = $entity->getTable();

		// grab insert fields
		$insert_fields = array();
		$insert_props = array();
		$insert_defaults = array();

		foreach ($entity->getProperties() as $prop=>$f) {
		// skip autoIncrement fields
			if (isset($f[2]) && isset($f[2]['autoIncrement']) && $f[2]['autoIncrement']) continue;

			$insert_props[] = $prop;
			$insert_fields[] = $f[0];

			// if there's options
			// TODO: Clean this up
			if (isset($f[2])) {
				if (is_null( $this->getProp( $obj, $prop ) )) {
					if (isset($f[2]['default'])) {
						$this->setProp( $obj, $prop, $f[2]['default']);
						$insert_defaults[] = false;
					} elseif (isset($f[2]['defaultExpr'])) {
						$insert_defaults[] = $f[2]['defaultExpr'];
					} else {
						$insert_defaults[] = false;
					}
				} else {
					$insert_defaults[] = false;
				}
				continue;
			} else {
				$insert_defaults[] = false;
			}
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

		$stmt = $con->prepare($q);

		// get the values
		$values = array();
		foreach ($insert_props as $key=>$p) {
		// skip the defaults
			if ($insert_defaults[$key]) continue;

			$values[] = self::toSqlValue( $properties[$p], $this->getProp($obj, $p) );
		}

		$stmt->execute($values);

		// create a proxy
		$proxy_class = self::getEntityClass($obj) . '_OutletProxy';
		$proxy = new $proxy_class;

		// copy the properties to the proxy
		foreach ($entity->getProperties() as $key=>$f) {
			$field = $key;
			if (@$f[2]['autoIncrement']) {
				// Sequence name will be set and is needed for Postgres
				$id = $con->lastInsertId($entity->getSequenceName());
				$this->setProp( $proxy, $field , self::toPhpValue($f, $id));
			} else {
				$this->setProp( $proxy, $field , $this->getProp( $obj, $field ));
			}
		}

		// copy the associated objects to the proxy
		foreach ($entity->getAssociations() as $a) {
			$type = $a->getType();
			if ($type == 'one-to-many' || $type == 'many-to-many') {
				$getter = $a->getGetter();
				$setter	= $a->getSetter();

				$ref = $obj->$getter();
				if ($ref) $proxy->$setter( $obj->$getter() );
			}
		}
		$obj = $proxy;

		$this->saveOneToMany($obj);
		$this->saveManyToMany($obj);

		// add it to the cache
		self::set(self::getEntityClass($obj), self::getPkValues($obj), array(
			'obj' => $obj,
			'original' => self::toArray($obj)
		));
	}

	/**
	 * Get the value of an entity property using the method specified in the config: public prop or getter
	 * 
	 * @param $obj
	 * @param string $prop
	 * @return mixed
	 */
	public function getProp ($obj, $prop) {
		$config = $this->config->getEntity(self::getEntityClass($obj));

		if ($config->useGettersAndSetters()) {
			$getter = "get$prop";
			return $obj->$getter();
		} else {
			return $obj->$prop;
		}
	}

	/**
	 * Set the value of an entity property using the method specified in the config: public prop or setter
	 * 
	 * @param $obj
	 * @param string $prop
	 * @param mixed $value
	 */
	public function setProp ($obj, $prop, $value) {
		$config = $this->config->getEntity(self::getEntityClass($obj));

		if ($config->useGettersAndSetters()) {
			$setter = "set$prop";
			$obj->$setter( $value );
		} else {
			$obj->$prop = $value;
		}
	}

	/**
	 * Check to see if an entity values (row) have been modified
	 *
	 * @param object $obj
	 * @return boolean
	 */
	public function getModifiedFields ($obj) {
		$data = $this->get( self::getEntityClass($obj), $this->getPkValues($obj) );

		/* not sure about this yet
		// if this entity hasn't been saved to the map
		if (!$data) return self::toArray($this->obj);
		*/

		$new = $this->toArray($data['obj']);

		$diff = array_diff_assoc($data['original'], $new);

		return array_keys($diff);
	}

	public function update(&$obj) {
	// get the class
		$cls = self::getEntityClass($obj);

		// this first since this references the key
		$this->saveManyToOne($obj);

		if ($mod = $this->getModifiedFields($obj)) {
			$con = $this->config->getConnection();

			$q = "UPDATE {".$cls."} \n";
			$q .= "SET \n";

			$ups = array();
			foreach ($this->config->getEntity($cls)->getProperties() as $key=>$f) {
			// skip fields that were not modified
				if (!in_array($key, $mod)) continue;

				// skip primary key
				if (@$f[2]['pk']) continue;

				$value = $this->getProp($obj, $key);
				if (is_null($value)) {
					$value = 'NULL';
				} else {
					$value = $con->quote( self::toSqlValue( $f, $value ) );
				}

				$ups[] = "  {".$cls.'.'.$key."} = $value";
			}
			$q .= implode(", \n", $ups);

			$q .= "\nWHERE ";

			$clause = array();
			foreach ($this->config->getEntity($cls)->getProperties() as $key=>$pk) {
			// if it's not a primary key, skip it
				if (!@$pk[2]['pk']) continue;

				$value = $con->quote( self::toSqlValue( $pk, self::getProp($obj, $key) ) );
				$clause[] = "$pk[0] = $value";
			}
			$q .= implode(' AND ', $clause);

			$q = $this->processQuery($q);

			$con->exec($q);
		}

		// these last since they reference the key
		$this->saveOneToMany($obj);
		$this->saveManyToMany($obj);
	}

	public function toArray ($entity) {
		if (!$entity) throw new OutletException('You must pass an entity');

		$class = self::getEntityClass($entity);

		$arr = array();
		foreach ($this->config->getEntity($class)->getProperties() as $key=>$p) {
			$arr[$key] = self::toSqlValue($p, $this->getProp($entity, $key));
		}
		return $arr;
	}

	static function toSqlValue ($conf, $v) {
		if (is_null($v)) return NULL;

		switch ($conf[1]) {
			case 'date': return $v->format('Y-m-d');
			case 'datetime': return $v->format('Y-m-d H:i:s');

			case 'int': return (int) $v;

			case 'float': return (float) $v;

			// Strings
			default: return $v;
		}
	}

	static function toPhpValue ($conf, $v) {
		if (is_null($v)) return NULL;

		switch ($conf[1]) {
			case 'date':
			case 'datetime':
				if ($v instanceof DateTime) return $v;
				return new DateTime($v);

			case 'int': return (int) $v;

			case 'float': return (float) $v;

			// Strings
			default: return $v;
		}
	}
	
	function processSubQuery ($q, $class, $alias) {
		preg_match_all(self::IDENTIFIER_PATTERN, $q, $matches, PREG_SET_ORDER);
		
		foreach ($matches as $key=>$m) {
			// clear braces
			$str = substr($m[0], 1, -1);
			
			$propconf = $this->config->getEntity($class)->getProperty($str);

			$q = str_replace($m[0], $alias.'.'. $propconf[0], $q);
		}
		
		return $q;
	}
	
	/**
	 * @param string $q
	 * @return string
	 */
	function processQuery ( $q ) {
		preg_match_all(self::IDENTIFIER_PATTERN, $q, $matches, PREG_SET_ORDER);

		// check if it's an update statement
		$update = (stripos(trim($q), 'UPDATE')===0);

		// get the table names
		$aliased = array();
		foreach ($matches as $key=>$m) {
		// clear braces
			$str = substr($m[0], 1, -1);

			// if it's an aliased class
			if (strpos($str, ' ')!==false) {
				$tmp = explode(' ', $str);
				$aliased[$tmp[1]] = $tmp[0];

				$q = str_replace($m[0], $this->config->getEntity($tmp[0])->getTable().' '.$tmp[1], $q);

			// if it's a non-aliased class
			} elseif (strpos($str, '.')===false) {
			// if it's a non-aliased class
				$table = $this->config->getEntity($str)->getTable();
				$aliased[$table] = $str;
				$q = str_replace($m[0], $table, $q);
			}

		}

		// update references to the properties
		foreach ($matches as $key=>$m) {
		// clear braces
			$str = substr($m[0], 1, -1);

			// if it's a property
			if (strpos($str, '.')!==false) {
				list($en, $prop) = explode('.', $str);
				
				
				// if it's an alias
				if (isset($aliased[$en])) {
					$entity = $aliased[$en];
					$alias = $en;
				} else {
					$entity = $en;
					$alias = $this->config->getEntity($entity)->getTable();
				}
				
				$propconf = $this->config->getEntity($entity)->getProperty($prop);

				// if it's an update statement,
				// we must not include the table
				if ($update) {
					// skip if it's an sql field
					if (!isset($propconf[2]) || !isset($propconf[2]['sql'])) {
						$col = $propconf[0];
					}
				} else {
					// if it's an sql field
					if (isset($propconf[2]) && isset($propconf[2]['sql'])) {
						$col = '('. $this->processSubQuery($propconf[2]['sql'], $entity, $alias) .')';
					} else {
						$col = $alias.'.'.$propconf[0];
					}
				}

				$q = str_replace(
					$m[0],
					$col,
					$q
				);
			}
		}

		return $q;
	}

	/**
	 * Save object to the identity map
	 *
	 * @param string $clazz
	 * @param array $pks Primary key values
	 * @param array $data
	 */
	public function set ( $clazz, array $pks, array $data ) {
		// FIXME: Library should handle PK updates
		// store on the map using the write type for the key (int, string)
		$pks = $this->getPkValuesForObject($data['obj']);

		// just in case
		reset($pks);

		// if there's only one pk, use it instead of the array
		if (is_array($pks) && count($pks)==1) $pks = current($pks);

		// initialize map for this class
		if (!isset($this->map[$clazz])) $this->map[$clazz] = array();

		$this->map[$clazz][serialize($pks)] = $data;
	}

	/**
	 * Remove an entity from the cache
	 * 
	 * @param $clazz
	 * @param $pk
	 * @return unknown_type
	 */
	public function clear ( $clazz, $pk ) {
		if (isset($this->map[$clazz])) unset($this->map[$clazz]);
	}

	public function clearCache () {
		$this->map = array();
	}

	/**
	 * @param string $clazz
	 * @param mixed $pk Primary key
	 * @return array array('obj'=>Entity, 'original'=>Original row used to populate entity)
	 */
	public function get ( $clazz, array $pk ) {
		// FIXME: Library should handle PK updates
		// if there's only one pk, use instead of the array
		if (is_array($pk) && count($pk)==1) $pk = array_shift($pk);

		if (isset($this->map[$clazz]) && isset($this->map[$clazz][serialize($pk)])) {
			return $this->map[$clazz][serialize($pk)];
		}
		return null;
	}

}

