<?php
require 'OutletPDO.php';
require 'OutletMapper.php';
require 'OutletProxy.php';
require 'OutletConfig.php';

class Outlet {
	static $instance;
	
	private $config;
	
	/**
	 * @var OutletPDO
	 */
	private $con;
	
	/**
	 * @var OutletIdentityMap
	 */
	private $identity_map;
	
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

	private function __construct (array $conf) {
		$this->config = new OutletConfig( $conf );
		$this->con = $this->config->getConnection();

		OutletMapper::$conf = &$conf['classes'];
	}

	
	public function save (&$obj) {
		$con = $this->getConnection();

		$con->beginTransaction();

		$mapper = new OutletMapper($obj);
		$return = $mapper->save();
	
		$con->commit();

		return $return;
	}

	public function delete ($clazz, $id) {
		$pk = $this->config->getEntity($clazz)->getPkField();
		//TODO Replace $this->quote with something that will work with an ODBC Connection
		//$q = "DELETE FROM {"."$clazz} WHERE {"."$clazz.$pk} = " . $this->quote($id);
		$q = "DELETE FROM {"."$clazz} WHERE {"."$clazz.$pk} = ?";

		$q = OutletMapper::processQuery($q);
		
		$stmt = $this->getConnection()->prepare($q);

		$res = $stmt->execute(array($id));

		//$res = $this->con->exec($q);

		// remove from identity map
		OutletMapper::clear($clazz, $id);

		return $res;
	}

	public function quote ($val) {
		return $this->getConnection()->quote($val);
	}

	/**
	 * @param string $clazz Name of the class as mapped on the configuration
	 * @param string $query Query to execute as a prepared statement
	 * @param string $params Parameters to bind to the query
	 * @return array Collection returned by the query
	 */
	public function select ( $clazz, $query='', $params=array()) {
		// select plus criteria
		$q = "SELECT {"."$clazz}.* FROM {".$clazz."} " . $query;

		$proxyclass = "{$clazz}_OutletProxy";
		$collection = array();
		
		$stmt = $this->query($q, $params);
		
		// get the pk column in order to check the map
		$props = $this->getConfig()->getEntity($clazz)->getProperties();
		$pk = array();
		foreach ($props as $key=>$d) {
			if (isset($d[2]['pk']) && $d[2]['pk']) $pk[] = $d[0]; 
		}
			
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			
			// if the object is found on the map, use it
			if (isset(OutletMapper::$map[$clazz][$row[$pk[0]]])) {
				$collection[] = OutletMapper::$map[$clazz][$row[$pk[0]]]['obj'];
				
			// else use the one from the query
			} else {
				$obj = new $proxyclass();
				$this->populateObject($clazz, $obj, $row);
				$collection[] = $obj;
			}
		}
		
		// save in identity map
		foreach ($collection as $o) {
			$mapped = new OutletMapper($o);
			$this->identity_map[$clazz][$mapped->getPK()] = $mapped->toArray();
		}

		return $collection;
	}

	public function createProxies () {
		require_once 'OutletProxyGenerator.php';
		$gen = new OutletProxyGenerator($this->config);
		$c = $gen->generate();
		eval($c);
	}


	public function load ($clazz, $pk) {
		// create a proxy
		$proxyclass = "{$clazz}_OutletProxy";
		
		// create a mapper
		$mapper = new OutletMapper( new $proxyclass );
		$mapper->setPk( $pk );
		$mapper->load();

		return $mapper->getObj();
	}

	/**
	 * @return OutletPDO
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
	
	
	function getLastInsertId () {
		if ($this->getConnection()->getDialect() == 'mssql') {
			return $this->con->query('SELECT SCOPE_IDENTITY()')->fetchColumn(0);
		} else {
			return $this->con->lastInsertId();
		}
	}


	public function populateObject($clazz, $obj, array $values) {
		$entity = $this->config->getEntity($clazz);
		$fields = $entity->getProperties();
		foreach ($fields as $key=>$f) {
			if (!array_key_exists($f[0], $values)) throw new OutletException("Field [$f[0]] defined in the config is not defined in table [".$entity->getTable()."]");

			$obj->$key = $values[$f[0]];
		}

		return $obj;
	}

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
		OutletMapper::clearCache();
	}
	
	/**
	 * @param string $query
	 * @param array $params
	 * @return PDOStatement
	 */
	public function query ( $query='', $params=array()) {
		// process the query
		$q = OutletMapper::processQuery($query);

		$stmt = $this->con->prepare($q);
		$stmt->execute($params);

		return $stmt;
	}
	
	/**
	 * @param string $from
	 * @return OutletQuery
	 */
	public function from ($from) {
		require_once 'OutletQuery.php';
		$q = new OutletQuery;
		$q->from($from);
		
		return $q;
	}
}

class OutletException extends Exception {}

