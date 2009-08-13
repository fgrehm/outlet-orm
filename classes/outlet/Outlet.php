<?php
require 'OutletConnection.php';
require 'OutletMapper.php';
require 'OutletProxy.php';
require 'OutletConfig.php';
require 'Collection.php';
require 'OutletCollection.php';

/**
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
	 * Initialize outlet with an array configuration
	 * 
	 * @param array $conf
	 */
	static function init ( array $conf ) {
		// instantiate
		self::$instance = new self( $conf );
	}

	/**
	 * @return Outlet instance
	 */
	static function getInstance () {
		if (!self::$instance) throw new OutletException('You must first initialize Outlet by calling Outlet::init( $conf )');
		return self::$instance;
	}

	public function __construct (array $conf) {
		$this->config = new OutletConfig( $conf );

		$this->con = $this->config->getConnection();
		
		$this->mapper = new OutletMapper( $this->config );
		
		//OutletMapper::$conf = &$conf['classes'];
	}

	/**
	 * Persist the passed entity to the database by executing an INSERT or an UPDATE
	 *
	 * @param object $obj
	 * @return object OutletProxy object representing the Entity
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
	 * @return mixed
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
		// TODO: It's not being used, maybe we could remove it
		//		$pk = $this->getConfig()->getEntity($clazz)->getPkColumns();

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
	public function createProxies () {
		require_once 'OutletProxyGenerator.php';
		$gen = new OutletProxyGenerator($this->config);
		$c = $gen->generate();
		eval($c);
		
		// set outlet
		$c = '';
		foreach ($this->config->getEntities() as $en) {
			$cls = $en->getClass() . '_OutletProxy';
			$c .= "$cls::\$_outlet = \$this;\n";
		}
		eval($c);
	}

	/**
	 * Select ONE entity from the database using it's primary key
	 * 
	 * @param $clazz
	 * @param $pk
	 * @return unknown_type
	 */
	public function load ($cls, $pk) {
		return $this->mapper->load($cls, $pk);
	}

	/**
	 * @return OutletConnection
	 */
	function getConnection () {
		return $this->config->getConnection();
	}

	/**
	 * @return OutletConfig
	 */
	function getConfig () {
		return $this->config;
	}

	/**
	 * Returns last generated ID
	 *
	 * If using PostgreSQL the $sequenceName needs to be specified
	 */
	function getLastInsertId ($sequenceName = '') {
		return $this->con->lastInsertId($sequenceName);
	}

	/**
	 * Return the entity for a database row
	 * This method checks the identity map
	 *
	 * @param string $clazz
	 * @param array $row
	 */
	public function getEntityForRow ($clazz, array $row) {
		$this->mapper->castRow($clazz, $row);

		// get the pk column in order to check the map
		$pks = $this->getConfig()->getEntity($clazz)->getPkColumns();

		$values = array();
		foreach ($pks as $pk) {
			$values[] = $row[$pk];
		}

		$data = $this->mapper->get($clazz, $values);

		$proxyclass = "{$clazz}_OutletProxy";

		if ($data) {
			return $data['obj'];
		} else {
			$obj = $this->mapper->populateObject($clazz, new $proxyclass, $row);

			// add it to the cache
			$this->mapper->set($clazz, $values, array(
				'obj' => $obj,
				'original' => $this->mapper->toArray($obj)
			));

			return $obj;
		}
	}

	/**
	 * Execute a full select but only return the first result
	 * 
	 * @param $clazz
	 * @param $query
	 * @param $params
	 */
	public function selectOne ($clazz, $query='', $params=array()) {
		$res = $this->select($clazz, $query, $params);
		if (count($res)) {
			return $res[0];
		} else {
			return null;
		}
	}

	private function getTable($clazz) {
		return $this->conf['classes'][$clazz]['table'];
	}

	private function getFields ($clazz) {
		return $this->conf['classes'][$clazz]['props'];
	}

	private function getPkFields( $clazz ) {
		$fields = $this->conf['classes'][$clazz]['props'];

		$pks = array();
		foreach ($fields as $key => $f) {
			if (isset($f[2]) && isset($f[2]['pk']) && $f[2]['pk']) $pks[$key] = $f;
		}

		return $pks;
	}

	private function removeAutoIncrement ( $fields ) {
		$newArr = array();
		foreach ($fields as $key=>$f) {
			if (isset($f[2]) && isset($f[2]['autoIncrement']) && $f[2]['autoIncrement']) continue;
			$newArr[$key] = $f;
		}
		return $newArr;
	}

	public function clearCache () {
		$this->mapper->clearCache();
	}

	/**
	 * @param string $query
	 * @param array $params
	 * @return PDOStatement
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
		$q = OutletMapper::processQuery($query);
		
		$stmt = $this->con->prepare($q);
		return $stmt;
	}

	/**
	 * @param string $from
	 * @return OutletQuery
	 */
	public function from ($from) {
		require_once 'OutletQuery.php';
		$q = new OutletQuery($this);
		$q->from($from);

		return $q;
	}
}

class OutletException extends Exception {}

