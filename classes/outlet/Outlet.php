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
		if (!self::$instance) throw new OutletException('You must first initialize Outlet by calling Outlet::init( $conf )');
		return self::$instance;
	}

	private function __construct (array $conf) {
		// validate config
		if (!isset($conf['connection'])) throw new OutletConfigException('Element [connection] not found in configuration');
		if (!isset($conf['connection']['dsn'])) throw new OutletConfigException('Element [connection][dsn] not found in configuration');
		if (!isset($conf['connection']['dialect'])) throw new OutletConfigException('Element [connection][dialect] not found in configuration');
		if (!isset($conf['classes'])) throw new OutletConfigException('Element [classes] missing in configuration');

		$this->con = new OutletPDO($conf['connection']['dsn'], @$conf['connection']['username'], @$conf['connection']['password']);	
		$this->con->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		// prepare config
		$this->conf = $conf;

		foreach ($this->conf['classes'] as $key=>$cls) {
			if (!isset($cls['table'])) throw new OutletConfigException('Mapping mapping for entity ['.$key.'] is missing element [table]');
			if (!isset($cls['props'])) throw new OutletConfigException('Mapping mapping for entity ['.$key.'] is missing element [props]');

			foreach ($cls['props'] as $p=>$f) {
				if (@$f[2]['pk']) {
					$pk = $p;
					break;
				}
			}
			if (!isset($pk)) throw new OutletConfigException("Entity [$key] must have at least one column defined as a primary key in the configuration");
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
	
	
	function getLastInsertId () {
		if ($this->conf['connection']['dialect'] == 'mssql') {
			return $this->con->query('SELECT SCOPE_IDENTITY()')->fetchColumn(0);
		} else {
			return $this->con->lastInsertId();
		}
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

class OutletException extends Exception {}
class OutletConfigException extends OutletException {}

