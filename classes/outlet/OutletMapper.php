<?php
/**
 * Data Mapper that acts as the interface between Outlet and the Database
 * 
 * @package outlet
 */
class OutletMapper
{
	const IDENTIFIER_PATTERN = '/\{[a-zA-Z0-9_]+(( |\.)[a-zA-Z0-9_]+)*\}/';
	
	//static $conf;

	/**
	 * @var array
	 */
	public $map;	

	/**
	 * @var OutletConnection
	 */
	public $con;
	
	/**
	 * @var string
	 */
	public $onHydrate;

	/**
	 * Constructs a new instance of OutletMapper
	 * 
	 * @param OutletConfig $config Configuration to use
	 * @return OutletMapper instance
	 */
	public function __construct(OutletConnection $con, array $outletMap)
	{
		// should be renamed to cache
		$this->map = array();

		$this->con = $con;
		$this->outletMap = $outletMap;
	}

	/**
	 * Return the entity class of an object as defined in the config
	 * 
	 * For example, it will return 'User' when passed an instance of User or User_OutletProxy
	 * 
	 * @param object $obj The object to introspect
	 * @return string the entity classname
	 */
	public function getEntityClass($obj)
	{
		foreach (array_keys($this->outletMap) as $cls) {
			if ($obj instanceof $cls) {
				return $cls;
			}
		}
		
		throw new OutletException('Object [' . get_class($obj) . '] not configured');
	}

	/**
	 * Persist an entity to the database by performing either an INSERT or an UPDATE
	 * 
	 * @see OutletMapper::insert(&$obj)
	 * @see OutletMapper::update(&$obj)
	 * @param object $obj the entity to save
	 */
	public function save(&$obj)
	{
		$map = $this->outletMap[$this->getEntityClass($obj)];
		
		if (self::isNew($obj)) {
			return $this->insert($obj, $map);
		} else {
			return $this->update($obj, $map);
		}
	}

	/**
	 * Determine if an object has been saved by seeing if it's actually a proxy
	 * 
	 * @param object $obj the entity to check
	 * @return bool true if entity is new, false otherwise
	 */
	public static function isNew($obj)
	{
		return !$obj instanceof OutletProxy;
	}

	/**
	 * Set the primary key of an entity
	 * 
	 * @param object $obj the entity on which to set the primary key
	 * @param mixed $pk scalar or array for columnar or composite primary key
	 */
	public function setPk($obj, $pk)
	{
		if (!is_array($pk)) {
			$pk = array($pk);
		}

		$pk_props = $this->outletMap[$this->getEntityClass($obj)]->getPKs();
		
		if (count($pk) != count($pk_props)) {
			throw new OutletException('You must pass the following pk: [' . implode(',', $pk_props) . '], you passed: [' . implode(',', $pk) . ']');
		}
		
		foreach ($pk_props as $key => $prop) {
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
	public function load($cls, $pk)
	{
		if (!$pk) {
			throw new OutletException("Must pass a valid primary key value, passed: " . var_export($pk, true));
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
			$map = $this->outletMap[$cls];
			
			// create a proxy
			$proxyclass = "{$cls}_OutletProxy";
			
			$obj = new $proxyclass();
			
			$props = array();	
			foreach ($map->getPropMaps() as $key => $prop) {
				// if it's sql we must specify an alias
				if ($prop->getSQL()) {
					$props[] = '{' . $cls . '.' . $key . '} as ' . $prop->getColumn();
				} else {
					$props[] = '{' . $cls . '.' . $key . '}';
				}
			}
			
			// craft select
			$q = "SELECT ";
			$q .= implode(', ', $props) . "\n";
			$q .= "FROM {" . $cls . "} \n";
			
			$pk_props = $map->getPKs();
			
			$pk_q = array();
			
			foreach ($pk_props as $pkp) {
				$pk_q[] = '{' . $cls . '.' . $pkp . '} = ?';
			}
			
			$q .= "WHERE " . implode(' AND ', $pk_q);
			
			$q = $this->processQuery($q);
			
			$stmt = $this->con->prepare($q);
			
			$stmt->execute(array_values($pks));
			
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			
			// if there's no matching row,
			// return null
			if (!$row) {
				return null;
			}
			
			$map->castRow($row);
			$this->populateObject($cls, $obj, $row);
			
			// add it to the cache
			$this->set($cls, $map->getPkValues($obj), array('obj' => $obj, 'original' => $map->toRow($obj)));
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
	public function populateObject($clazz, $obj, array $values)
	{
		$this->outletMap[$clazz]->populate($obj, $values);
		
		// trigger onHydrate callback
		if ($this->onHydrate) {
			call_user_func($this->onHydrate, $obj);
		}
		
		return $obj;
	}

	/**
	 * Retrieve the primary key property of an entity
	 * 
	 * @param object $obj the entity
	 * @return object the primary key property
	 */
	public function getPkProp($obj)
	{
		return $this->config->getEntity($this->config->getEntityClass($obj))->getPkField();
	}

	/**
	 * Saves the one to many relationships for an entity
	 * 
	 * @param object $obj entity to save one to many relationship for
	 */
	public function saveOneToMany($obj, OutletEntityMap $map)
	{
		$assocs = $map->getAssociations();
		
		if (count($assocs)) {
			$pks = $map->getPkValues($obj);
			
			foreach ($assocs as $assoc) {
				if ($assoc->getType() != 'one-to-many') {
					// only process one-to-many relationships
					continue;
				}
				
				$key = $assoc->getKey();
				$getter = 'get'.$assoc->getName();
				$setter = 'set'.$assoc->getName();

				$foreignMap = $assoc->getEntityMap();
				
				/** @var $children Collection */
				$children = $obj->$getter(null);
				
				if (is_null($children)) {
					continue;
				}
				
				// if we don't have an OutletCollection yet
				if (!$children instanceof OutletCollection) {
					$arr = $children->getArrayCopy();
					
					/** @var $children OutletCollection */
					$children = $obj->$getter();
					$children->exchangeArray($arr);
				}
				
				// if removing all connections
				if ($children->isRemoveAll()) {
					//TODO Make it work with composite keys
					$q = $this->processQuery('DELETE FROM {' . $foreign . '} WHERE {' . $foreign . '.' . $assoc->getKey() . '} = ?');
					$stmt = $this->con->prepare($q);
					$stmt->execute($pks);
				}
				
				$newArr = array();
				
				foreach ($children->getArrayCopy() as $child) {
					//TODO make it work with composite keys
					$foreignMap->setProp($child, $key, current($pks));
					$this->save($child);
					$newArr[] = $child;
				}
				
				$children->exchangeArray($newArr);
				$obj->$setter($children);
			}
		}
	}

	/**
	 * Saves the many to many relationships for an entity
	 * @param object $obj entity to save many to many relationship for
	 */
	private function saveManyToMany($obj, OutletEntityMap $map)
	{
		$assocs = $map->getAssociations();
		
		if (count($assocs)) {
			$pks = $map->getPkValues($obj);
			
			foreach ($assocs as $assoc) {
				if ($assoc->getType() != 'many-to-many') {
					// only process 'many-to-many' relationships
					continue;
				}
				
				$key_column = $assoc->getTableKeyLocal();
				$ref_column = $assoc->getTableKeyForeign();

				$table = $assoc->getLinkingTable();
				$foreignMap = $assoc->getEntityMap();
				//$name = $assoc->getForeignName();
				
				$getter = 'get'.$assoc->getName();
				$setter = 'set'.$assoc->getName();
				
				$children = $obj->$getter();
				
				// if removing all connections
				if ($children->isRemoveAll()) {
					//TODO Make it work with composite keys
					$q = "DELETE FROM $table WHERE $key_column = ?";
					
					$stmt = $this->con->prepare($q);
					
					$stmt->execute(array_values($pks));
				}
				
				$new = $children->getLocalIterator();
				
				foreach ($new as $child) {
					if ($child instanceof OutletProxy) {
						$child_pks = $this->outletMap[$this->getEntityClass($child)]->getPkValues($child);
						$id = current($child_pks);
					} else {
						$id = $child;
					}
					
					$q = "
						INSERT INTO $table ($key_column, $ref_column) 
						VALUES (?, ?)
					";
					
					$stmt = $this->con->prepare($q);
					
					$stmt->execute(array(current($pks), $id));
				}
				
				$obj->$setter($children);
			}
		}
	}

	/**
	 * Saves the many to one relationships for an entity
	 * @param object $obj entity to save many to one relationship for
	 */
	private function saveManyToOne($obj, OutletEntityMap $map)
	{
		foreach ($map->getAssociations() as $assoc) {
			if ($assoc->getType() != 'many-to-one') {
				// only process 'many-to-one' relationships
				continue;
			}
			
			$key = $assoc->getKey();
			$refKey = $assoc->getRefKey();
			$getter = 'get'.$assoc->getName();
			
			$ent = $obj->$getter();
			
			if ($ent) {
				if (self::isNew($ent)) {
					$this->save($ent);
				}
				
				$foreignMap = $this->outletMap[$this->getEntityClass($ent)];

				$map->setProp($obj, $key, $foreignMap->getProp($ent, $refKey));
			}
		}
	}

	/**
	 * Saves the one to one relationships for an entity
	 * @param object $obj entity to save one to one relationship for
	 */
	public function saveOneToOne($obj, OutletEntityMap $map)
	{
		foreach ($map->getAssociations() as $assoc) {
			if ($assoc->getType() != 'one-to-one') {
				// only process 'one-to-one' relationships
				continue;
			}
			
			$key = $assoc->getKey();
			$refKey = $assoc->getRefKey();
			$getter = 'get'.$assoc->getName();
			
			$ent = $obj->$getter();
			
			if ($ent) {
				if (self::isNew($ent)) {
					$this->save($ent);
				}
				
				$foreignMap = $this->outletMap[$this->getEntityClass($ent)];
				$map->setProp($obj, $key, $foreignMap->getProp($ent, $refKey));
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
	public function insert(&$obj, OutletEntityMap $map)
	{
		$this->saveOneToOne($obj, $map);
		$this->saveManyToOne($obj, $map);
		
		$properties = $map->getPropMaps();
		
		$props = array_keys($properties);
		$table = $map->getTable();
		
		// grab insert fields
		$insert_fields = array();
		$insert_props = array();
		$insert_defaults = array();
		
		foreach ($properties as $prop => $f) {
			if ($f->isAutoIncrement()) {
				// skip autoIncrement fields
				continue;
			}
			
			$insert_props[] = $prop;
			$insert_fields[] = $f->getColumn();
			
			// options
			if (is_null($map->getProp($obj, $prop))) {
				if (!is_null($f->getDefault())) {
					$map->setProp($obj, $prop, $f->getDefault());
					$insert_defaults[] = false;
				} elseif (!is_null($f->getDefaultExpr())) {
					$insert_defaults[] = $f->getDefaultExpr();
				} else {
					$insert_defaults[] = false;
				}
			} else {
				$insert_defaults[] = false;
			}
			continue;
		}
		
		$q = "INSERT INTO $table ";
		$q .= "(" . implode(', ', $insert_fields) . ")";
		$q .= " VALUES ";
		
		// question marks for each value
		// except for defaults
		$values = array();
		foreach ($insert_fields as $key => $f) {
			if ($insert_defaults[$key]) {
				$values[] = $insert_defaults[$key];
			} else {
				$values[] = '?';
			}
		}
		$q .= "(" . implode(', ', $values) . ")";
		
		$stmt = $this->con->prepare($q);
		
		// get the values
		$values = array();
		foreach ($insert_props as $key => $p) {
			if ($insert_defaults[$key]) {
				// skip the defaults
				continue;
			}
			
			$values[] = self::toSqlValue($properties[$p]->getType(), $map->getProp($obj, $p));
		}
		
		$stmt->execute($values);
		
		// create a proxy
		$proxy_class = $map->getClass() . '_OutletProxy';
		$proxy = new $proxy_class();
		
		// copy the properties to the proxy
		foreach ($properties as $key => $f) {
			$field = $key;
			if ($f->isAutoIncrement()) {
				// Sequence name will be set and is needed for Postgres
				$id = $this->con->lastInsertId($map->getSequenceName());
				$map->setProp($proxy, $field, self::toPhpValue($f, $id));
			} else {
				$map->setProp($proxy, $field, $map->getProp($obj, $field));
			}
		}
		
		// copy the associated objects to the proxy
		foreach ($map->getAssociations() as $a) {
			$type = $a->getType();
			if ($type == 'one-to-many' || $type == 'many-to-many') {
				$getter = 'get'.$a->getName();
				$setter = 'set'.$a->getName();
				
				$ref = $obj->$getter();
				
				if ($ref) {
					$proxy->$setter($obj->$getter());
				}
			}
		}
		$obj = $proxy;
		
		$this->saveOneToMany($obj, $map);
		$this->saveManyToMany($obj, $map);
		
		// trigger onHydrate callback
		if ($this->onHydrate) {
			call_user_func($this->onHydrate, $obj);
		}
		
		// add it to the cache
		self::set($map->getClass(), $map->getPkValues($obj), array('obj' => $obj, 'original' => $map->toRow($obj)));
	}

	/**
	 * Check to see if an entity values (row) have been modified
	 *
	 * @param object $obj entity to inspect
	 * @return array fields that have changed
	 */
	public function getModifiedFields($obj)
	{
		$map = $this->outletMap[$this->getEntityClass($obj)];
		
		$data = $this->get($map->getClass(), $map->getPkValues($obj));
		
		/* not sure about this yet
		// if this entity hasn't been saved to the map
		if (!$data) return self::toArray($this->obj);
		*/
		
		$new = $map->toRow($data['obj']);
		
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
	public function update(&$obj, OutletEntityMap $map)
	{
		// this first since this references the key
		$this->saveManyToOne($obj, $map);

		if ($mod = $this->getModifiedFields($obj, $map)) {
			$cls = $map->getClass();
			
			$q = "UPDATE {" . $cls . "} \n";
			$q .= "SET \n";
			
			$ups = array();
			foreach ($map->getPropMaps() as $key => $f) {
				if (!in_array($key, $mod)) {
					// skip fields that were not modified
					continue;
				}
				
				if (in_array($key, $map->getPKs())) {
					// skip primary key
					continue;
				}
				
				$value = $map->getProp($obj, $key);
				if (is_null($value)) {
					$value = 'NULL';
				} else {
					$value = $this->con->quote(self::toSqlValue($f->getType(), $value));
				}
				
				$ups[] = "  {" . $cls . '.' . $key . "} = $value";
			}
			$q .= implode(", \n", $ups);
			
			$q .= "\nWHERE ";
			
			$clause = array();
			
			foreach ($map->getPKs() as $pk) {
				$prop = $map->getPropMap($pk);
				$value = $this->con->quote(self::toSqlValue($prop->getType(), $map->getProp($obj, $pk)));
				$clause[] = "{$prop->getColumn()} = $value";
			}
			
			$q .= implode(' AND ', $clause);
			
			$q = $this->processQuery($q);
	
			$this->con->exec($q);
		}
		
		// these last since they reference the key
		$this->saveOneToMany($obj, $map);
		$this->saveManyToMany($obj, $map);
		
		// update cache
		// add it to the cache
		self::set($map->getClass(), $map->getPkValues($obj), array('obj' => $obj, 'original' => $map->toRow($obj)));
	}

	/**
	 * Translates an entity into an associative array, applying OutletMapper::toSqlValue($conf, $v) on all values
	 * 
	 * @see OutletMapper::toSqlValue($conf, $v)
	 * @param object $entity entity to translate into an array
	 * @return array entity values
	 */
	public function toArray($entity)
	{
		if (!$entity) {
			throw new OutletException('You must pass an entity');
		}
		$entityMap = $this->outletMap[$this->getEntityClass($entity)];
		
		return $entityMap->toRow($entity);
	}

	/**
	 * Translates a PHP value to a SQL Value
	 * @param array $conf configuration entry for the value
	 * @param mixed $v value to translate
	 * @return mixed translated value
	 */
	public static function toSqlValue($type, $v)
	{
		if (is_null($v)) {
			return NULL;
		}
		
		switch ($type) {
			case 'date':
				return $v->format('Y-m-d');
			case 'datetime':
				return $v->format('Y-m-d H:i:s');
			case 'int':
			case 'bool':
				return (int) $v;
			case 'float':
				return (float) $v;
			default: //strings
				return $v;
		}
	}

	/**
	 * Translates an value to the expected value as defined in the configuration
	 * 
	 * @param object $conf configuration entry for the value
	 * @param mixed $v value to translate
	 * @return mixed translated and casted value
	 */
	public static function toPhpValue($type, $v)
	{
		if (is_null($v)) {
			return NULL;
		}
		
		switch ($type) {
			case 'date':
			case 'datetime':
				return $v instanceof DateTime ? $v : new DateTime($v);
			case 'int':
				return (int) $v;
			case 'float':
				return (float) $v;
			case 'bool':
				return $v == 1;
			default: //strings
				return $v;
		}
	}

	/**
	 * Processes a subquery interpolating properties
	 * 
	 * @param string $q query to process 
	 * @param string $class entity class
	 * @param string $alias alias
	 * @return string processed query
	 */
	public function processSubQuery($q, $class, $alias)
	{
		preg_match_all(self::IDENTIFIER_PATTERN, $q, $matches, PREG_SET_ORDER);
		
		foreach ($matches as $key => $m) {
			// clear braces
			$str = substr($m[0], 1, -1);
			
			$propconf = $this->config->getEntity($class)->getProperty($str);
			
			$q = str_replace($m[0], $alias . '.' . $propconf[0], $q);
		}
		
		return $q;
	}

	/**
	 * Processes a query interpolating properties
	 * 
	 * @param string $q query to process
	 * @return string processed query
	 */
	public function processQuery($q)
	{
		preg_match_all(self::IDENTIFIER_PATTERN, $q, $matches, PREG_SET_ORDER);
		
		// check if it's an update statement
		$update = (stripos(trim($q), 'UPDATE') === 0);
		
		// get the table names
		$aliased = array();
		
		foreach ($matches as $key => $m) {
			// clear braces
			$str = substr($m[0], 1, -1);
			
			// if it's an aliased class
			if (strpos($str, ' ') !== false) {
				$tmp = explode(' ', $str);
				$aliased[$tmp[1]] = $tmp[0];
				
				$q = str_replace($m[0], $this->outletMap[$tmp[0]]->getTable() . ' ' . $tmp[1], $q);
				
			// if it's a non-aliased class
			} elseif (strpos($str, '.') === false) {
				// if it's a non-aliased class
				$table = $this->outletMap[$str]->getTable(); 
				$aliased[$table] = $str;
				$q = str_replace($m[0], $table, $q);
			}
		
		}
		
		// update references to the properties
		foreach ($matches as $key => $m) {
			// clear braces
			$str = substr($m[0], 1, -1);
			
			// if it's a property
			if (strpos($str, '.') !== false) {
				list($en, $prop) = explode('.', $str);
				
				// if it's an alias
				if (isset($aliased[$en])) {
					$entity = $aliased[$en];
					$alias = $en;
				} else {
					$entity = $en;
					$alias = $this->outletMap[$entity]->getTable();
				}
				
				$propconf = $this->outletMap[$entity]->getPropMap($prop);
				
				// if it's an update statement,
				// we must not include the table
				if ($update) {
					// skip if it's an sql field
					if (!$propconf->getSql()) {
						$col = $propconf->getColumn();
					}
				} else {
					// if it's an sql field
					if ($propconf->getSQL()) {
						$col = '(' . $this->processSubQuery($propconf->getSQL(), $entity, $alias) . ')';
					} else {
						$col = $alias . '.' . $propconf->getColumn();
					}
				}
				
				$q = str_replace($m[0], $col, $q);
			}
		}
		
		return $q;
	}

	private function hash($pks)
	{
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
	public function set($clazz, array $pks, array $data)
	{
		//		$entityCfg = $this->config->getEntity($clazz);
		
		//TODO Library should handle PK updates
		// store on the map using the write type for the key (int, string)
		//		$pks = $entityCfg->getPkValues($data['obj']);
		

		// just in case
		reset($pks);
		
		// if there's only one pk, use it instead of the array
		if (is_array($pks) && count($pks) == 1) {
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
	public function clear($clazz, $pk)
	{
		if (isset($this->map[$clazz])) {
			unset($this->map[$clazz]);
		}
	}

	/**
	 * Clears the cache 
	 */
	public function clearCache()
	{
		$this->map = array();
	}

	/**
	 * Gets a class by primary key from the cache
	 * @param string $clazz Class to look up
	 * @param mixed $pk Primary key
	 * @return array array('obj'=>Entity, 'original'=>Original row used to populate entity)
	 */
	public function get($clazz, array $pk)
	{
		//TODO Library should handle PK updates
		// if there's only one pk, use instead of the array
		if (is_array($pk) && count($pk) == 1) {
			$pk = array_shift($pk);
		}
		
		$hash = $this->hash($pk);
		
		if (isset($this->map[$clazz]) && isset($this->map[$clazz][$hash])) {
			return $this->map[$clazz][$hash];
		}
		
		return null;
	}
}
