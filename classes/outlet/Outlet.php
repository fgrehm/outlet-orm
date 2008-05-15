<?php
require 'OutletPDO.php';
require 'OutletMapper.php';
require 'OutletProxy.php';

class Outlet {
	static $instance;
	
	private $conf;
	
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
		if (!self::$instance) throw new Exception('You must first initialize Outlet by calling Outlet::init( $conf )');
		return self::$instance;
	}

	private function __construct (array $conf) {
		$this->con = new OutletPDO($conf['connection']['dsn'], @$conf['connection']['username'], @$conf['connection']['password']);	
		$this->con->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		// prepare config
		$this->conf = $conf;

		foreach ($this->conf['classes'] as $key=>$cls) {
			$pk;
			foreach ($cls['props'] as $p=>$f) {
				if (@$f[2]['pk']) {
					$pk = $p;
					break;
				}
			}
			if (!$pk) throw new Exception("Class $key must have a primary key defined in the configuration");
			$this->conf['classes'][$key]['pk'] = $pk;
		}
		
		OutletMapper::$conf = &$conf['classes'];
	}

	
	public function save (&$obj) {
		$mapper = new OutletMapper($obj);
		return $mapper->save();
	}

	public function delete ($clazz, $id) {
		$table = $this->conf['classes'][$clazz]['table'];
		$pks = $this->getPkFields($clazz);
		$pk = array_shift($pks);

		$q = "DELETE FROM $table WHERE ".$pk[0]." = '" . $id . "'";

		return $this->con->exec($q);
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
		$c = OutletProxyGenerator::generate($this->conf);
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
	function &getConnection () {
		return $this->con;
	}

	function &getConfiguration () {
		return $this->conf;
	}
	


	private function populateObject($clazz, $obj, array $values) {
		$fields = $this->conf['classes'][$clazz]['props'];
		foreach ($fields as $key=>$f) {
			$obj->$key = $values[$f[0]];
		}

		return $obj;
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
