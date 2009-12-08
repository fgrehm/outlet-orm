<?php
require 'OutletConnection.php';
require 'OutletMapper.php';
require 'OutletProxy.php';
require 'OutletConfig.php';
require 'Collection.php';
require 'OutletCollection.php';

/**
 * Main entry point for client interaction with Outlet
 * @package outlet
 */
class Outlet {
	static $instance;

	private $config;

	/**
	 * @var OutletConnection
	 */
	private $con;
	
	/**
	 * @var OutletMapper
	 */
	private $mapper;

	/**
	 * Initialize outlet with an array configuration
	 * 
	 * @param array $conf configuration
	 */
	static function init ( array $conf ) {
		// instantiate
		self::$instance = new self( $conf );
	}

	/**
	 * @return Outlet instance
	 */
	static function getInstance () {
		if (!self::$instance) {
			throw new OutletException('You must first initialize Outlet by calling Outlet::init( $conf )');
		}
		return self::$instance;
	}

	/**
	 * Constructs a new instance of Outlet
	 * @param array $conf configuration 
	 * @return Outlet instance
	 */
	public function __construct (array $conf) {
		$this->config = new OutletConfig( $conf );

		$this->con = $this->config->getConnection();
		
		$this->mapper = new OutletMapper( $this->config );
	}

	/**
	 * Persist the passed entity to the database by executing an INSERT or an UPDATE
	 *
	 * @param object $obj
	 * @return OutletProxy object representing the Entity
	 */
	public function save (&$obj) {
		$con = $this->getConnection();

		$con->beginTransaction();

		$return = $this->mapper->save( $obj );
		
		$con->commit();

		return $return;
	}

	/**
	 * Perform a DELETE statement for the corresponding entity
	 * 
	 * @param string $clazz Class of the entity (not the proxy) 
	 * @param mixed $id Primary key of the entity
	 * @return bool true if delete succeeded, false otherwise
	 */
	public function delete ($clazz, $id) {
		if (!is_array($id)) $id = array($id);

		$pks = $this->config->getEntity($clazz)->getPkFields();

		$pk_q = array();
		foreach ($pks as $pk) {
			$pk_q[] = '{'.$clazz.'.'.$pk.'} = ?';
		}

		$q = "DELETE FROM {"."$clazz} WHERE " . implode(' AND ', $pk_q);

		$q = $this->mapper->processQuery($q);

		$stmt = $this->getConnection()->prepare($q);

		$res = $stmt->execute($id);

		// remove from identity map
		$this->mapper->clear($clazz, $id);

		return $res;
	}

	/**
	 * Quotes a value to protect against SQL injection attackes
	 * @see OutletConnection::quote($v)
	 * @param mixed $val value to quote
	 * @return mixed quoted value
	 */
	public function quote ($val) {
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
	public function select ( $clazz, $query='', $params=array()) {
		// select plus criteria
		$q = "SELECT {"."$clazz}.* FROM {".$clazz."} " . $query;

		$proxyclass = "{$clazz}_OutletProxy";
		$collection = array();

		$stmt = $this->query($q, $params);

		// get the pk column in order to check the map
		/** @todo It's not being used, maybe we could remove it */
		//		$pk = $this->getConfig()->getEntity($clazz)->getPkColumns();

        	$config = $this->getConfig()->getEntity($clazz);
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$collection[] = $this->getEntityForRow($config, $row);
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
	public function createProxies () {
		require_once 'OutletProxyGenerator.php';
		$gen = new OutletProxyGenerator($this->config);
		$c = $gen->generate();
		eval($c);
		
		// set outlet
		$c = '';
		foreach ($this->config->getEntities() as $en) {
			$cls = $en->clazz . '_OutletProxy';
			$c .= "$cls::\$_outlet = \$this;\n";
		}
		eval($c);
	}

	/**
	 * Select ONE entity from the database using it's primary key
	 * 
	 * @param string $cls Class to load
	 * @param mixed $pk primary key value
	 * @return object entity of class $cls
	 */
	public function load ($cls, $pk) {
		return $this->mapper->load($cls, $pk);
	}

	/**
	 * Retrieve the connection
	 * @see OutletConfig::getConnection()
	 * @return OutletConnection
	 */
	function getConnection () {
		return $this->config->getConnection();
	}

	/**
	 * Retrieve the configuration
	 * @return OutletConfig
	 */
	function getConfig () {
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
	function getLastInsertId ($sequenceName = '') {
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
	public function getEntityForRow ($entityCfg, array $row) {
//		$entityCfg = $this->getConfig()->getEntity($clazz);
        	$clazz = $entityCfg->clazz;

//		$entityCfg->castRow($row);

		// get the pk column in order to check the map
		$pks = $entityCfg->getPkColumns();

		$pkValues = array();
		foreach ($pks as $pk) {
			$pkValues[] = $row[$pk];
		}

		$data = $this->mapper->get($clazz, $pkValues);

		$proxyclass = "{$clazz}_OutletProxy";

		if ($data) {
			return $data['obj'];
		} else {
                        // TODO: cast values on populateObject
			$obj = $entityCfg->populateObject(new $proxyclass, $row);

			// add it to the cache
			$this->mapper->set($clazz, $pkValues, array(
				'obj' => $obj,
				'original' => $row// TODO: why this? $entityCfg->toRow($obj)
			));

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
	public function selectOne ($clazz, $query='', $params=array()) {
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
	private function getTable($clazz) {
		return $this->conf['classes'][$clazz]['table'];
	}

	/**
	 * Retrieve the fields for an entity class
	 * @param string $clazz entity class
	 * @return array properties array
	 */
	private function getFields ($clazz) {
		return $this->conf['classes'][$clazz]['props'];
	}

	/**
	 * Retrieve the primary key fields
	 * @see OutletEntityConfig::getPkFields()
	 * @see OutletEntityConfig::getPkColumns()
	 * @param string $clazz entity class
	 * @return array primary key field
	 */
	private function getPkFields( $clazz ) {
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
	private function removeAutoIncrement ( $fields ) {
		$newArr = array();
		foreach ($fields as $key=>$f) {
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
	public function clearCache () {
		$this->mapper->clearCache();
	}

	/**
	 * Executes a query
	 * @param string $query query to execute
	 * @param array $params values to replace parameterized placeholders with
	 * @return PDOStatement statement that was executed
	 */
	public function query ( $query='', array $params=array()) {
		// process the query
		$q = $this->mapper->processQuery($query);

		$stmt = $this->con->prepare($q);
		$stmt->execute($params);

		return $stmt;
	}
	
	/**
	 * Parse a query containing outlet entities and return a PDOStatement (not executed)
	 * 
	 * @param string $query
	 * @return PDOStatement
	 */
	public function prepare ($query) {
		$q = $this->mapper->processQuery($query);
		
		$stmt = $this->con->prepare($q);
		return $stmt;
	}

	/**
	 * Create an OutletQuery selecting from an entity table
	 * @param string $from entity table to select from
	 * @return OutletQuery unexecuted
	 */
	public function from ($from) {
		require_once 'OutletQuery.php';
		$q = new OutletQuery($this);
		$q->from($from);

		return $q;
	}
	
	/**
	 * @param $obj
	 * @return array
	 */
	public function toArray ($obj) {
		return $this->mapper->toArray($obj);
	}
	
	public function onHydrate($callback) {
		$this->mapper->onHydrate = $callback;
	}
}

/**
 * Exception to be thrown by Outlet
 */
class OutletException extends Exception {}

