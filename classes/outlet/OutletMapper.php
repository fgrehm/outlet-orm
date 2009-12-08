<?php
/**
 * Data Mapper that acts as the interface between Outlet and the Database
 * @package outlet
 */
class OutletMapper {
	const IDENTIFIER_PATTERN = '/\{[a-zA-Z0-9_]+(( |\.)[a-zA-Z0-9_]+)*\}/';

	//static $conf;
	
	/**
	 * @var array
	 */
	public $map = array();
	
	/**
	 * @var OutletConfig
	 */
	private $config;
	

	
	public $onHydrate;

	/**
	 * Constructs a new instance of OutletMapper
	 * @param OutletConfig $config Configuration to use
	 * @return OutletMapper instance
	 */
	function __construct (OutletConfig $config) {
		$this->config = $config;
	}

	/**
	 * Return the entity class of an object as defined in the config
	 * 
	 * For example, it will return 'User' when passed an instance of User or User_OutletProxy
	 * 
	 * @param object $obj The object to introspect
	 * @return string the entity classname
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
	 * @see OutletMapper::insert(&$obj)
	 * @see OutletMapper::update(&$obj)
	 * @param object $obj the entity to save
	 */
	public function save (&$obj) {
		$entityCfg = $this->config->getEntityForObject($obj);

		if (self::isNew($obj)) {
			return $this->insert($obj, $entityCfg);
		} else {
			return $this->update($obj, $entityCfg);
		}
	}

	/**
	 * Determine if an object has been saved by seeing if it's actually a proxy
	 * 
	 * @param object $obj the entity to check
	 * @return bool true if entity is new, false otherwise
	 */
	static function isNew($obj) {
		return ! $obj instanceof OutletProxy;
	}

	/**
	 * Set the primary key of an entity
	 * 
	 * @param object $obj the entity on which to set the primary key
	 * @param mixed $pk scalar or array for columnar or composite primary key
	 */
	public function setPk ($obj, $pk) {
		if (!is_array($pk)) $pk = array($pk);

		$pk_props = $this->config->getEntity($this->config->getEntityClass($obj))->getPkFields();

		if (count($pk)!=count($pk_props)) throw new OutletException('You must pass the following pk: ['.implode(',', $pk_props).'], you passed: ['.implode(',', $pk).']');

		foreach ($pk_props as $key=>$prop) {
			$obj->$prop = $pk[$key];
		}
	}

    /**
     * Loads an entity by its primary key
     * 
     * @param string $cls Entity class
     * @param mixed $pk Primary key
     * @return object the entity, if the entity can't be found returns null
     */
	public function load ($cls, $pk) {
		if (!$pk) {
			throw new OutletException("Must pass a valid primary key value, passed: ".var_export($pk, true));
		}
	   
		if (!is_array($pk)) {
			$pks = array($pk);
		} else {
			$pks = $pk;
		} 

		// try to retrieve it from the cache first
		$data = $this->get($cls, $pks);

		// if it's there
		if ($data) {
			$obj = $data['obj'];

		// else, populate it from the database
		} else {
			$entityCfg = $this->config->getEntity($cls);

			// create a proxy
			$proxyclass = "{$cls}_OutletProxy";

			$obj = new $proxyclass;

			$props_conf = $entityCfg->getProperties();
			
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

			$pk_props = $entityCfg->getPkFields();

			$pk_q = array();
			foreach ($pk_props as $pkp) {
				$pk_q[] = '{'.$cls.'.'.$pkp.'} = ?';
			}

			$q .= "WHERE " . implode(' AND ', $pk_q);

			$q = $this->processQuery($q);

			$stmt = $this->config->getConnection()->prepare($q);

			$stmt->execute(array_values($pks));

			$row = $stmt->fetch(PDO::FETCH_ASSOC);

			// if there's no matching row,
			// return null
			if (!$row) {
				return null;
			}
		
			$entityCfg->castRow($row);	
			$this->populateObject($cls, $obj, $row);

			// add it to the cache
			$this->set($cls, $entityCfg->getPkValues($obj), array(
				'obj' => $obj,
				'original' => $entityCfg->toRow($obj)
			));
		}

		return $obj;
	}
	
	/**
	 * Populate an object with the values from an associative array indexed by column names
	 * 
	 * @param array $clazz Class of the entity
	 * @param object $obj Instance of the entity (probably brand new) or a subclass
	 * @param array $values Associative array indexed by column name, it must already be casted
	 * @return object populated entity 
	 */
	public function populateObject ($clazz, $obj, array $values) {
		$entityCfg = $this->config->getEntity($clazz);

		$entityCfg->populateObject ($obj, $values);

		// trigger onHydrate callback
		if ($this->onHydrate) {
			call_user_func($this->onHydrate, $obj);
		}

		return $obj;
	}

	/**
	 * Retrieve the primary key property of an entity
	 * @param object $obj the entity
	 * @return object the primary key property
	 */
	public function getPkProp ( $obj ) {
		return $this->config->getEntity($this->config->getEntityClass($obj))->getPkField();
	}

	/**
	 * Saves the one to many relationships for an entity
	 * @param object $obj entity to save one to many relationship for
	 */
	public function saveOneToMany ($obj, OutletEntityConfig $entityCfg) {
		$assocs = $entityCfg->getAssociations();

		if (count($assocs)) {
			$pks = $entityCfg->getPkValues($obj);

			foreach ($assocs as $assoc) {
				
				if ($assoc->getType() != 'one-to-many') {
					// only process one-to-many relationships
					continue;
				}

				$key 		= $assoc->getKey();
				$getter 	= $assoc->getGetter();
				$setter		= $assoc->getSetter();
				$foreign	= $assoc->getForeign();

				/** @var $children Collection */
				$children = $obj->$getter(null);

				if (is_null($children)) {
					continue;
				}
				
				// if we don't have an OutletCollection yet
				if (! $children instanceof OutletCollection) {
					$arr = $children->getArrayCopy();

					/** @var $children OutletCollection */
					$children = $obj->$getter();
					$children->exchangeArray($arr);
				}

				// if removing all connections
				if ($children->isRemoveAll()) {	
					/** @todo Make it work with composite keys */
					$q = $this->processQuery('DELETE FROM {'.$foreign.'} WHERE {'.$foreign.'.'.$assoc->getKey().'} = ?');
					$stmt = $this->config->getConnection()->prepare($q);
					$stmt->execute($pks);
				}

				foreach (array_keys($children->getArrayCopy()) as $k) {
					/** @todo make it work with composite keys */
					$foreignEntityCfg = $this->config->getEntity($foreign);
					$foreignEntityCfg->setProp($children[$k], $key, current($pks));
					$this->save($children[$k]);
				}

				$obj->$setter( $children );
			}
		}
	}

	/**
	 * Saves the many to many relationships for an entity
	 * @param object $obj entity to save many to many relationship for
	 */
	private function saveManyToMany ($obj, OutletEntityConfig $entityCfg) {
		$con = $this->config->getConnection();
		$assocs = $entityCfg->getAssociations();

		if (count($assocs)) {
			$pks = $entityCfg->getPkValues($obj);

			foreach ($assocs as $assoc) {
				if ($assoc->getType() != 'many-to-many') {
					// only process 'many-to-many' relationships
					continue;
				}

				$key_column = $assoc->getTableKeyLocal();
				$ref_column = $assoc->getTableKeyForeign();
				$table      = $assoc->getLinkingTable();
				$name       = $assoc->getForeignName();

				$getter	= $assoc->getGetter();
				$setter	= $assoc->getSetter();

				$children = $obj->$getter();

				// if removing all connections
				if ($children->isRemoveAll()) {
					/** @todo Make it work with composite keys */
					$q = "DELETE FROM $table WHERE $key_column = ?";

					$stmt = $con->prepare($q);

					$stmt->execute(array_values($pks));
				}

				$new = $children->getLocalIterator();

				foreach ($new as $child) {
					if ($child instanceof OutletProxy) {
						$child_pks = $this->config->getEntityForObject($child)->getPkValues($child);
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
	}

	/**
	 * Saves the many to one relationships for an entity
	 * @param object $obj entity to save many to one relationship for
	 */ 
	private function saveManyToOne ($obj, OutletEntityConfig $entityCfg) {
		foreach ($entityCfg->getAssociations() as $assoc) {
			if ($assoc->getType() != 'many-to-one') {
				// only process 'many-to-one' relationships
				continue;
			}

			$key    = $assoc->getKey();
			$refKey	= $assoc->getRefKey();
			$getter = $assoc->getGetter();

			$ent = $obj->$getter();

			if ($ent) {
				if (self::isNew($ent)) {
					$this->save($ent);
				} 

				$foreignEntityCfg = $this->config->getEntityForObject($ent);

				$entityCfg->setProp($obj, $key, $foreignEntityCfg->getProp($ent, $refKey));
			}
		}
	}

	/**
	 * Saves the one to one relationships for an entity
	 * @param object $obj entity to save one to one relationship for
	 */ 
	function saveOneToOne ($obj, OutletEntityConfig $entityCfg) {
		foreach ($entityCfg->getAssociations() as $assoc) {
			if ($assoc->getType() != 'one-to-one') {
				// only process 'one-to-one' relationships
				continue;
			}

			$key    = $assoc->getKey();
			$refKey	= $assoc->getRefKey();
			$getter = $assoc->getGetter();

			$ent = $obj->$getter();

			if ($ent) {
				if (self::isNew($ent)) { 
					$this->save($ent);
				}

				$obj->$key = $ent->$refKey;
			}
		}
	}

	/**
	 * Inserts an entity into the database including relationships.
	 * 
	 * It is safer to use the save function, it will take into account newness of the entity to determine insert vs update
	 * 
	 * @see OutletMapper::save(&$obj)
	 * @param object $obj entity to insert
	 */
	public function insert (&$obj, OutletEntityConfig $entityCfg) {
		$con = $this->config->getConnection();

		$this->saveOneToOne( $obj, $entityCfg );
		$this->saveManyToOne( $obj, $entityCfg );

		$properties = $entityCfg->getProperties();

		$props = array_keys($properties);
		$table = $entityCfg->table;

		// grab insert fields
		$insert_fields = array();
		$insert_props = array();
		$insert_defaults = array();

		foreach ($entityCfg->getProperties() as $prop=>$f) {
			
			if (isset($f[2]) && isset($f[2]['autoIncrement']) && $f[2]['autoIncrement']) {
				// skip autoIncrement fields
				continue;
			}

			$insert_props[] = $prop;
			$insert_fields[] = $f[0];

			// if there's options
			/** @todo Clean this up */
			if (isset($f[2])) {
				if (is_null( $entityCfg->getProp( $obj, $prop ) )) {
					if (isset($f[2]['default'])) {
						$entityCfg->setProp( $obj, $prop, $f[2]['default']);
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
			if ($insert_defaults[$key]) {
				$values[] = $insert_defaults[$key];
			} else {
				$values[] = '?';
			}
		}
		$q .="(" . implode(', ', $values) . ")";

		$stmt = $con->prepare($q);

		// get the values
		$values = array();
		foreach ($insert_props as $key=>$p) {
			if ($insert_defaults[$key]) {
				// skip the defaults
				continue;
			}

			$values[] = self::toSqlValue( $properties[$p][1], $entityCfg->getProp($obj, $p) );
		}

		$stmt->execute($values);

		// create a proxy
		$proxy_class = $entityCfg->clazz . '_OutletProxy';
		$proxy = new $proxy_class;

		// copy the properties to the proxy
		foreach ($entityCfg->getProperties() as $key=>$f) {
			$field = $key;
			if (@$f[2]['autoIncrement']) {
				// Sequence name will be set and is needed for Postgres
				$id = $con->lastInsertId($entityCfg->getSequenceName());
				$entityCfg->setProp( $proxy, $field , self::toPhpValue($f, $id) );
			} else {
				$entityCfg->setProp( $proxy, $field , $entityCfg->getProp( $obj, $field ) );
			}
		}

		// copy the associated objects to the proxy
		foreach ($entityCfg->getAssociations() as $a) {
			$type = $a->getType();
			if ($type == 'one-to-many' || $type == 'many-to-many') {
				$getter = $a->getGetter();
				$setter	= $a->getSetter();

				$ref = $obj->$getter();
				if ($ref) $proxy->$setter( $obj->$getter() );
			}
		}
		$obj = $proxy;

		$this->saveOneToMany($obj, $entityCfg);
		$this->saveManyToMany($obj, $entityCfg);
			
		// trigger onHydrate callback
		if ($this->onHydrate) {
			call_user_func($this->onHydrate, $obj);
		}

		// add it to the cache
		self::set($entityCfg->clazz, $entityCfg->getPkValues($obj), array(
			'obj' => $obj,
			'original' => $entityCfg->toRow($obj)
		));
	}

	/**
	 * Check to see if an entity values (row) have been modified
	 *
	 * @param object $obj entity to inspect
	 * @return array fields that have changed
	 */
	public function getModifiedFields ($obj) {
		$entityCfg = $this->config->getEntityForObject($obj);

		$data = $this->get( $entityCfg->clazz, $entityCfg->getPkValues($obj) );

		/* not sure about this yet
		// if this entity hasn't been saved to the map
		if (!$data) return self::toArray($this->obj);
		*/

		$new = $entityCfg->toRow($data['obj']);

		$diff = array_diff_assoc($data['original'], $new);

		return array_keys($diff);
	}

	/**
	 * Updates an entity in the database including relationships.
	 * 
	 * It is safer to use the save function, it will take into account newness of the entity to determine insert vs update
	 *
	 * @see OutletMapper::save(&$obj)
	 * @param object $obj entity to update
	 */
	public function update(&$obj, OutletEntityConfig $entityCfg) {
		// this first since this references the key
		$this->saveManyToOne($obj, $entityCfg);

		if ($mod = $this->getModifiedFields($obj, $entityCfg)) {
			$con = $this->config->getConnection();
			$cls = $entityCfg->clazz;

			$q = "UPDATE {".$cls."} \n";
			$q .= "SET \n";

			$ups = array();
			foreach ($entityCfg->getProperties() as $key=>$f) {
				if (!in_array($key, $mod)) {
					// skip fields that were not modified
					continue;
				}

				if (@$f[2]['pk']) {
					// skip primary key
					continue;
				}

				$value = $entityCfg->getProp($obj, $key);
				if (is_null($value)) {
					$value = 'NULL';
				} else {
					$value = $con->quote( self::toSqlValue( $f[1], $value ) );
				}

				$ups[] = "  {".$cls.'.'.$key."} = $value";
			}
			$q .= implode(", \n", $ups);

			$q .= "\nWHERE ";

			$clause = array();
			foreach ($entityCfg->getProperties() as $key=>$pk) {
				
				if (!@$pk[2]['pk']) { 
					// if it's not a primary key, skip it
					continue;
				}

				$value = $con->quote( self::toSqlValue( $pk[1], $entityCfg->getProp($obj, $key) ) );
				$clause[] = "$pk[0] = $value";
			}
			$q .= implode(' AND ', $clause);

			$q = $this->processQuery($q);

			$con->exec($q);
		}

		// these last since they reference the key
		$this->saveOneToMany($obj, $entityCfg);
		$this->saveManyToMany($obj, $entityCfg);
	}

	/**
	 * Translates an entity into an associative array, applying OutletMapper::toSqlValue($conf, $v) on all values
	 * 
	 * @see OutletMapper::toSqlValue($conf, $v)
	 * @param object $entity entity to translate into an array
	 * @return array entity values
	 */
	public function toArray ($entity) {
		if (!$entity) throw new OutletException('You must pass an entity');

		$entityCfg = $this->config->getEntityForObject($entity);

		return $entityCfg->toRow($entity);
	}

	/**
	 * Translates a PHP value to a SQL Value
	 * @param array $conf configuration entry for the value
	 * @param mixed $v value to translate
	 * @return mixed translated value
	 */
	static function toSqlValue ($type, $v) {
		if (is_null($v)) {
			return NULL;
		}

		switch ($type) {
			case 'date': return $v->format('Y-m-d');
			case 'datetime': return $v->format('Y-m-d H:i:s');

			case 'int': return (int) $v;

			case 'float': return (float) $v;

			// Strings
			default: return $v;
		}
	}

	/**
	 * Translates an value to the expected value as defined in the configuration
	 * @param object $conf configuration entry for the value
	 * @param mixed $v value to translate
	 * @return mixed translated and casted value
	 */
	static function toPhpValue ($type, $v) {
		if (is_null($v)) {
			return NULL;
		}

		switch ($type) {
			case 'date':
			case 'datetime':
				if ($v instanceof DateTime) {
					return $v;
				}
				return new DateTime($v);

			case 'int': return (int) $v;

			case 'float': return (float) $v;

			// Strings
			default: return $v;
		}
	}
	
	/**
	 * Processes a subquery interpolating properties
	 * @param string $q query to process 
	 * @param string $class entity class
	 * @param string $alias alias
	 * @return string processed query
	 */
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
	 * Processes a query interpolating properties
	 * @param string $q query to process
	 * @return string processed query
	 */
	public function processQuery ( $q ) {
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

				$q = str_replace($m[0], $this->config->getEntity($tmp[0])->table.' '.$tmp[1], $q);

			// if it's a non-aliased class
			} elseif (strpos($str, '.')===false) {
			// if it's a non-aliased class
				$table = $this->config->getEntity($str)->table;
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
					$alias = $this->config->getEntity($entity)->table;
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

        private function hash ( $pks ) {
        	if (is_array($pks))
			return join(';', $pks);
		else
			return $pks;
        }

	/**
	 * Save object to the identity map
	 *
	 * @param string $clazz Class to save as
	 * @param array $pks Primary key values
	 * @param array $data Data to save
	 */
	public function set ( $clazz, array $pks, array $data ) {
//		$entityCfg = $this->config->getEntity($clazz);

		/** @todo Library should handle PK updates */
		// store on the map using the write type for the key (int, string)
//		$pks = $entityCfg->getPkValues($data['obj']);

		// just in case
		reset($pks);

		// if there's only one pk, use it instead of the array
		if (is_array($pks) && count($pks)==1) {
			$pks = current($pks);
		}

		// initialize map for this class
		if (!isset($this->map[$clazz])) {
			$this->map[$clazz] = array();
		}

		$this->map[$clazz][$this->hash($pks)] = $data;
	}

	/**
	 * Remove an entity from the cache
	 * 
	 * @param $clazz Class entity is stored as
	 * @param $pk primary key (not used, but required)
	 */
	public function clear ( $clazz, $pk ) {
		if (isset($this->map[$clazz])) {
			unset($this->map[$clazz]);
		} 
	}
	
	/**
	 * Clears the cache 
	 */
	public function clearCache () {
		$this->map = array();
	}

	/**
	 * Gets a class by primary key from the cache
	 * @param string $clazz Class to look up
	 * @param mixed $pk Primary key
	 * @return array array('obj'=>Entity, 'original'=>Original row used to populate entity)
	 */
	public function get ( $clazz, array $pk ) {
		/** @todo Library should handle PK updates */
		// if there's only one pk, use instead of the array
		if (is_array($pk) && count($pk)==1) { 
			$pk = array_shift($pk);
		}

		$hash = $this->hash($pk);
		if (isset($this->map[$clazz]) && isset($this->map[$clazz][$hash])) {
			return $this->map[$clazz][$hash];
		}
		return null;
	}

}

