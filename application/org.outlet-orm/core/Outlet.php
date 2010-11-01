<?php
class Outlet
{
	/**
	 * @var Outlet
	 */
	private static $instance;
	
	/**
	 * @var OutletConfig
	 */
	private $config;
	
	/**
	 * @var array
	 */
	private $connectionConfig;
	
	/**
	 * @var OutletConnection
	 */
	private $con;
	
	/**
	 * @var OutletMapper
	 */
	private $mapper;
	
	/**
	 * 
	 * @var array
	 */
	private $map = array();

	/**
	 * Initialize outlet with an array configuration
	 * 
	 * @param array $conf configuration
	 */
	public static function init(array $conf)
	{
		// instantiate
		self::$instance = new self($conf);
	}

	/**
	 * @return Outlet instance
	 */
	public static function getInstance()
	{
		if (!self::$instance) {
			throw new OutletException('You must first initialize Outlet by calling Outlet::init($conf)');
		}
		
		return self::$instance;
	}
	
	/**
	 * Just for tests
	 * 
	 * @param Outlet $o
	 */
	public static function setInstance (Outlet $o = null)
	{
		self::$instance = $o;
	}

	/**
	 * Constructs a new instance of Outlet
	 * 
	 * @param array $conf configuration 
	 * @return Outlet instance
	 */
	public function __construct(array $conf)
	{
		self::validateConfig($conf);
		
		$this->connectionConfig = $conf['connection'];
	
		$this->createMap($conf);
		
		/*
		$this->config = new OutletConfig($conf);
		$this->con = $this->config->getConnection();
		*/
		$this->mapper = new OutletMapper($this->getConnection(), $this->map);
	}
	
	private static function validateConfig ($conf) {
		if (!isset($conf['connection'])) {
			throw new OutletConfigException('Element [connection] not found in configuration');
		}
		
		if (!isset($conf['connection']['dsn']) && !isset($conf['connection']['pdo'])) {
			throw new OutletConfigException('You must set either [connection][pdo] or [connection][dsn] in configuration');
		}
		
		if (!isset($conf['connection']['dialect'])) {
			throw new OutletConfigException('Element [connection][dialect] not found in configuration');
		}
		
		if (!isset($conf['classes'])) {
			throw new OutletConfigException('Element [classes] missing in configuration');
		}
	}
	
	private function createMap ($conf) {
		// create all of the entity maps
		foreach ($conf['classes'] as $c => $config) {
			$props = array();
			$pks = array();
			
			// props
			foreach ($config['props'] as $name => $prop) {	
				$props[$name] = new OutletPropMap($name, $prop[0], $prop[1], isset($prop[2]) ? $prop[2] : array() );
				
				// pks
				if (isset($prop[2]) && isset($prop[2]['pk']) && $prop[2]['pk'] == true) $pks[] = $name;
			}

			// use global useGettersAndSetters if there isn't an entity useGetterAndSetters set
			$config['useGettersAndSetters'] = isset($config['useGettersAndSetters']) ? $config['useGettersAndSetters'] : (isset($conf['useGettersAndSetters']) ? $conf['useGettersAndSetters'] : false);
			
			$this->map[$c] = new OutletEntityMap($c, $config['table'], $props, $pks, $config);
		}
		
		// add the associations
		foreach ($conf['classes'] as $c => $config) {
			if (isset($config['associations']))
			foreach ($config['associations'] as $assoc) {
				// options
				$opt = $assoc[2];
				
				// entity
				$en = $this->map[$assoc[1]];
				
				// assoc name
				switch ($assoc[0]) {
					case 'many-to-one':
						$name = isset($opt['name']) ? $opt['name'] : $assoc[1];
						$refKey = isset($opt['refKey']) ? $opt['refKey'] : current($en->getPKs());
						$optional = isset($opt['optional']) ? $opt['optional'] : false;
						
						$a = new OutletManyToOneAssociation($en, $name, $opt['key'], $refKey, $optional);
						break;
					case 'one-to-one':
						$name = isset($opt['name']) ? $opt['name'] : $assoc[1];
						$refKey = isset($opt['refKey']) ? $opt['refKey'] : current($en->getPKs());
						$optional = isset($opt['optional']) ? $opt['optional'] : false;
						
						$a = new OutletOneToOneAssociation($this->map[$assoc[1]], $name, $assoc[2]['key'], $refKey, $optional);
						break;
					case 'one-to-many':
						$name = isset($opt['plural']) ? $opt['plural'] : (isset($opt['name']) ? $opt['name'].'s' : $en->getPlural());
						$refKey = isset($opt['refKey']) ? $opt['refKey'] : current($this->map[$c]->getPKs());
						
						$a = new OutletOneToManyAssociation($en, $name, $opt['key'], $refKey);
						break;
					case 'many-to-many':
						$name = isset($opt['plural']) ? $opt['plural'] : (isset($opt['name']) ? $opt['name'].'s' : $en->getPlural());
						$key = isset($opt['key']) ? $opt['key'] : current($en->getPKs());
						$refKey = isset($opt['refKey']) ? $opt['refKey'] : current($this->map[$c]->getPKs());
						
						$a = new OutletManyToManyAssociation($en, $name, $opt['table'], $key, $refKey, $opt['tableKeyLocal'], $opt['tableKeyForeign']);
						break;
				}
				
				$this->map[$c]->addAssociation($a);
			}
		}
	}
	
	/**
	 * 
	 * @param string $class
	 * @return OutletEntityMap
	 */
	public function getEntityMap ($class) {
		if (!isset($this->map[$class])) throw new OutletConfigException("Entity [$class] has not been defined in the configuration.");
		
		return $this->map[$class];
	}

	/**
	 * Persist the passed entity to the database by executing an INSERT or an UPDATE
	 *
	 * @param object $obj
	 * @return OutletProxy object representing the Entity
	 */
	public function save(&$obj)
	{
		$con = $this->getConnection();
		
		$con->beginTransaction();
		$return = $this->mapper->save($obj);
		$con->commit();
		
		return $return;
	}
	
	public function refresh (&$obj) {
		return $this->mapper->refresh($obj);
	}

	/**
	 * Perform a DELETE statement for the corresponding entity
	 * 
	 * @param string $clazz Class of the entity (not the proxy) 
	 * @param mixed $id Primary key of the entity
	 * @return bool true if delete succeeded, false otherwise
	 */
	public function delete($clazz, $id)
	{
		if (!is_array($id)) {
			$id = array($id);
		}
		
		$pks = $this->map[$clazz]->getPKs();
		
		$pk_q = array();
		
		foreach ($pks as $pk) {
			$pk_q[] = '{' . $clazz . '.' . $pk . '} = ?';
		}
		
		$q = "DELETE FROM {" . "$clazz} WHERE " . implode(' AND ', $pk_q);
		
		$q = $this->mapper->processQuery($q);
		
		$stmt = $this->getConnection()->prepare($q);
		
		$res = $stmt->execute($id);
		
		// remove from identity map
		$this->mapper->clear($clazz, $id);
		
		return $res;
	}

	/**
	 * Quotes a value to protect against SQL injection attackes
	 * 
	 * @see OutletConnection::quote($v)
	 * @param mixed $val value to quote
	 * @return mixed quoted value
	 */
	public function quote($val)
	{
		return $this->getConnection()->quote($val);
	}

	/**
	 * Select entities from the database.
	 *
	 * @param string $clazz Name of the class as mapped on the configuration
	 * @param string $query Optional query to execute as a prepared statement
	 * @param string $params Optional parameters to bind to the query
	 * @return array Collection returned by the query
	 */
	public function select($clazz, $query = '', $params = array())
	{
		// select plus criteria
		$q = "SELECT {" . "$clazz}.* FROM {" . $clazz . "} " . $query;
		
		$proxyclass = "{$clazz}_OutletProxy";
		$collection = array();
		
		$stmt = $this->query($q, $params);
		
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$collection[] = $this->getEntityForRow($clazz, $row);
		}
		
		return $collection;
	}

	/**
	 * Generate the proxy classes that will perform the actual work
	 * 
	 * This method creates a string with the class definitions and then
	 * uses eval to create them. For better performance it's recommented
	 * that, instead of calling this method, you use the outletgen.php 
	 * script to generate the proxies and include them directly. This will
	 * allow byte-code caches to cache the proxies code. 
	 */
	public function createProxies()
	{
		$gen = $this->getProxyGenerator();
		$c = $gen->generate();
		
		eval($c);
	
		$this->attachProxies();	
	}

	public function attachProxies () {
		// set outlet
		$c = '';
		
		foreach ($this->map as $en) {
			$cls = $en->getClass() . '_OutletProxy';
			$c .= "$cls::\$_outlet = \$this;\n";
		}
		
		eval($c);	
	}

	public function getProxyGenerator () {
		return new OutletProxyGenerator($this->map);
	}

	/**
	 * Select ONE entity from the database using it's primary key
	 * 
	 * @param string $cls Class to load
	 * @param mixed $pk primary key value
	 * @return object entity of class $cls
	 */
	public function load($cls, $pk)
	{
		return $this->mapper->load($cls, $pk);
	}

	/**
	 * Retrieve the connection
	 * @see OutletConfig::getConnection()
	 * @return OutletConnection
	 */
	function getConnection()
	{
		if (!$this->con) {
			$conn = $this->connectionConfig;
						
			if (isset($conn['pdo'])) {
				$pdo = $conn['pdo'];
			} else {
				$pdo = new PDO($conn['dsn'], @$conn['username'], @$conn['password']);
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			}
			
			$this->con = new OutletConnection($pdo, $conn['dialect']);
		}
		return $this->con;
	}

	/**
	 * Retrieve the configuration
	 * @return OutletConfig
	 */
	function getConfig()
	{
		return $this->config;
	}

	/**
	 * Returns last generated ID
	 *
	 * If using PostgreSQL the $sequenceName needs to be specified
	 * 
	 * @param string $sequenceName sequence name to look for the last insert id in, required for PostgreSQL
	 * @return int the last insert id
	 */
	function getLastInsertId($sequenceName = '')
	{
		return $this->con->lastInsertId($sequenceName);
	}

	/**
	 * Return the entity for a database row
	 * This method checks the identity map
	 *
	 * @param string $clazz Class to populate
	 * @param array $row database row
	 * @return object populated entity
	 */
	public function getEntityForRow($clazz, array $row)
	{
		$map = $this->map[$clazz];
		
		// get the pk values in order to check the map		
		$pkValues = array();
		foreach ($map->getPKColumns() as $pk) {
			$pkValues[] = $row[$pk];
		}
		
		$data = $this->mapper->get($clazz, $pkValues);
		
		$proxyclass = "{$clazz}_OutletProxy";
		
		if ($data) {
			return $data['obj'];
		} else {
			// TODO: cast values on populateObject
			$obj = $map->populate(new $proxyclass(), $row);
			
			if ($this->mapper->onHydrate) {
				if (!function_exists($this->mapper->onHydrate)) {
					throw new OutletException('The function ' . $this->mapper->onHydrate . ' does not exists');
				}
				
				call_user_func($this->mapper->onHydrate, $obj);
			}
			
			// add it to the cache
			$this->mapper->set($clazz, $pkValues, array('obj' => $obj, 'original' => $map->toRow($obj)));
			
			return $obj;
		}
	}

	/**
	 * Execute a full select but only return the first result
	 * 
	 * @param string $clazz entity class
	 * @param string $query query to filter by
	 * @param array $params values to replace parameterized values in $query
	 * @return mixed first result row, null if no results are returned
	 */
	public function selectOne($clazz, $query = '', $params = array())
	{
		$res = $this->select($clazz, $query, $params);
		
		if (count($res)) {
			return $res[0];
		} else {
			return null;
		}
	}

	/**
	 * Retrieves the table for an entity class
	 * @param string $clazz entity class
	 * @return string table name
	 */
	private function getTable($clazz)
	{
		return $this->conf['classes'][$clazz]['table'];
	}

	/**
	 * Retrieve the fields for an entity class
	 * @param string $clazz entity class
	 * @return array properties array
	 */
	private function getFields($clazz)
	{
		return $this->conf['classes'][$clazz]['props'];
	}

	/**
	 * Retrieve the primary key fields
	 * @see OutletEntityConfig::getPkFields()
	 * @see OutletEntityConfig::getPkColumns()
	 * @param string $clazz entity class
	 * @return array primary key field
	 */
	private function getPkFields($clazz)
	{
		$fields = $this->conf['classes'][$clazz]['props'];
		
		$pks = array();
		
		foreach ($fields as $key => $f) {
			if (isset($f[2]) && isset($f[2]['pk']) && $f[2]['pk']) {
				$pks[$key] = $f;
			}
		}
		
		return $pks;
	}

	/**
	 * Filters any auto incremented fields out of the fields array
	 * @param array $fields array of fields
	 * @return array field array with auto incremented fields filtered out
	 */
	private function removeAutoIncrement($fields)
	{
		$newArr = array();
		
		foreach ($fields as $key => $f) {
			if (isset($f[2]) && isset($f[2]['autoIncrement']) && $f[2]['autoIncrement']) {
				// auto incremented fields should be skipped 
				continue;
			}
			$newArr[$key] = $f;
		}
		
		return $newArr;
	}

	/**
	 * Clears the mappers cache
	 * @see OutletMapper::clearCache()
	 */
	public function clearCache()
	{
		$this->mapper->clearCache();
	}

	/**
	 * Executes a query
	 * @param string $query query to execute
	 * @param array $params values to replace parameterized placeholders with
	 * @return PDOStatement statement that was executed
	 */
	public function query($query = '', array $params = array())
	{
		// process the query
		$q = $this->mapper->processQuery($query);
		
		$stmt = $this->getConnection()->prepare($q);
		$stmt->execute($params);
		
		return $stmt;
	}

	/**
	 * Parse a query containing outlet entities and return a PDOStatement (not executed)
	 * 
	 * @param string $query
	 * @return PDOStatement
	 */
	public function prepare($query)
	{
		$q = $this->mapper->processQuery($query);
		
		return $this->getConnection()->prepare($q);
	}

	/**
	 * Create an OutletQuery selecting from an entity table
	 * @param string $from entity table to select from
	 * @return OutletQuery unexecuted
	 */
	public function from($from)
	{
		$q = new OutletQuery();
		$q->from($from);
		
		return $q;
	}

	/**
	 * @param $obj
	 * @return array
	 */
	public function toArray($obj)
	{
		return $this->mapper->toArray($obj);
	}

	/**
	 * Sets onHydrate method
	 * 
	 * @param string $callback Callback function
	 */
	public function onHydrate($callback)
	{
		$this->mapper->onHydrate = $callback;
	}
}