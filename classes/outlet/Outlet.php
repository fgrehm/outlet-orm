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
		if (!self::$instance) throw new OutletException('You must first initialize Outlet by calling Outlet::nit( $conf )');
		return self::$instance;
	}

	private function __construct (array $conf) {
		$this->config = new OutletConfig( $conf );
		$this->con = $this->config->getConnection();

		OutletMapper::$conf = &$conf['classes'];
	}

	
	public function save (&$obj) {
		$mapper = new OutletMapper($obj);
		return $mapper->save();
	}

	public function delete ($clazz, $id) {
		$pk = $this->config->getEntity($clazz)->getPkField();
		$q = "DELETE FROM {"."$clazz} WHERE {"."$clazz.$pk} = '" . $id . "'";

		$q = OutletMapper::processQuery($q);

		$res = $this->con->exec($q);

		// remove from identity map
		OutletMapper::clear($clazz, $id);

		return $res;
	}

	public function select ( $clazz, $query='', $params=array()) {
		// select plus criteria
		$q = "SELECT {"."$clazz}.* FROM {".$clazz."} " . $query;

		// process the query
		$q = OutletMapper::processQuery($q);

		$proxyclass = "{$clazz}_OutletProxy";
		$collection = array();

		$stmt = $this->con->prepare($q);
		$stmt->execute($params);

		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$obj = new $proxyclass();
			$this->populateObject($clazz, $obj, $row);
			$collection[] = $obj;
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


	private function populateObject($clazz, $obj, array $values) {
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
	
}

class OutletException extends Exception {}

