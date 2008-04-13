<?php
require 'OutletPDO.php';
require 'OutletMapper.php';

class Outlet {
	static $instance;
	
	private $conf;
	private $con;
	private $identity_map = array();
	
	static function init ( $conf ) {
		// instantiate
		self::$instance = new self( include($conf) );
	}

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

		// setup the identity map
		foreach ($conf['classes'] as $key=>$val) {
			$this->identity_map[$key] = array();			
		}
	}

	function set ($clazz, $pk, array $data) {
		$this->identity_map[$clazz][$pk] = $data;
	}

	function get ($clazz, $pk) {
		return @$this->identity_map[$clazz][$pk];
	}
	
	public function save (&$obj) {	
		$clazz = str_replace('_OutletProxy', '', get_class($obj));

		$mapper = new OutletMapper($obj, $this->conf['classes'][$clazz]);

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
		$q = $this->processQuery($q);

		$proxyclass = "{$clazz}_OutletProxy";
		$collection = array();

		$stmt = $this->con->prepare($q);
		$stmt->execute($params);

		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$obj = new $proxyclass();
			$this->populateObject($clazz, $obj, $row);
			$collection[] = $obj;
		}

		return $collection;
	}

	private function processQuery ( $q ) {
		preg_match_all('/\{[a-zA-Z0-9]+(( |\.)[a-zA-Z0-9]+)*\}/', $q, $matches, PREG_SET_ORDER);

		// get the aliased classes
		$aliased = array();
		foreach ($matches as $key=>$m) {
			// clear braces
			$str = substr($m[0], 1, -1);

			// if it's an aliased class
			if (strpos($str, ' ')!==false) {
				$tmp = explode(' ', $str);
				$aliased[$tmp[1]] = $tmp[0];

				$q = str_replace($m[0], $this->conf['classes'][$tmp[0]]['table'].' '.$tmp[1], $q);

			// if it's a property
			} elseif (strpos($str, '.')!==false) {
				$tmp = explode('.', $str);

				// if it's an alias
				if (isset($aliased[$tmp[0]])) {
					$col = $tmp[0].'.'.$this->conf['classes'][$aliased[$tmp[0]]]['props'][$tmp[1]][0];
				} else {
					$table = $this->conf['classes'][$tmp[0]]['table'];
					$col = $table.'.'.$this->conf['classes'][$tmp[0]]['props'][$tmp[1]][0];
				}

				$q = str_replace(
					$m[0], 
					$col,
					$q
				);

			// if it's a non-aliased class
			} else {
				$table = $this->conf['classes'][$str]['table'];
				$aliased[$table] = $str;
				$q = str_replace($m[0], $table, $q);
			}

		}

		return $q;
	}

	public function createProxies () {
		require_once 'OutletProxyGenerator.php';
		$c = OutletProxyGenerator::generate($this->conf);
		eval($c);
	}

	public function wrap ($obj) {
		$clazz = get_class($obj);

		// check to see if
	}

	public function load ($clazz, $pk) {
		$proxyclass = "{$clazz}_OutletProxy";
		$obj = new $proxyclass();
	
		$table = $this->getTable($clazz);	
		$fields = $this->getFields($clazz);
		$dbfields = array();
		foreach ($fields as $f) {
			$dbfields[] = $table.'.'.$f[0];
		}

		$pks = $this->getPkFields($clazz);

		// craft select
		$q = "SELECT ";
		$q .= implode(', ', $dbfields) . "\n";
		$q .= "FROM $table \n";
		$q .= "WHERE ";

		if (!is_array($pk)) $pk = array($pk);

		if (count($pk) != count($pks))
			throw new Exception('Number of primary keys must be '. count($pks));

		$w = array();
		$counter = 0;
		foreach ($pks as $f) {
			$w[] = $f[0] . " = '" . $pk[$counter++] . "'";
		}
		$q .= implode(' AND ', $w);

		$res = $this->con->query($q);

		$row = $res->fetch(PDO::FETCH_ASSOC);

		// if there's matching row, 
		// return null
		if (!$row) return null;

		$obj = $this->populateObject($clazz, $obj, $row);

		// save in identity map
		$mapped = new OutletMapper( $obj );
		$arr = $mapped->toArray();
		$this->identity_map[$clazz][$mapped->getPK()] = $mapped->toArray();

		return $obj;	
	}

	function getConnection () {
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
	
	private function getPK ($clazz, $obj) {
		$fields = $this->getPkFields($clazz);

	}	

	function getIdentityMap () {
		return $this->identity_map;
	}
}
