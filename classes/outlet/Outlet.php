<?php
require 'OutletPDO.php';
require 'OutletMapper.php';

class Outlet {
	static $instance;
	
	private $conf;
	private $con;
	private $identity_map = array();
	
	static function init ( array $conf ) {
		// instantiate
		self::$instance = new self( $conf );
	}

	static function getInstance () {
		if (!self::$instance) throw new Exception('You must first initialize Outlet by calling Outlet::init( $conf )');
		return self::$instance;
	}

	private function __construct (array $conf) {
		$this->con = new OutletPDO($conf['connection']['dsn'], @$con['connection']['username'], @$con['connection']['password']);	
		$this->con->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->conf = $conf;

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

	public function select ( $clazz, $query ) {
		$table = $this->conf['classes'][$clazz]['table'];

		foreach ($this->conf['classes'] as $cls=>$settings) {
			//$query = str_replace($cls, $settings['table'], $query);
			foreach ($settings['fields'] as $field=>$s) {
				$query = str_replace("$cls.$field", "$table.$s[0]", $query);
			}
		}


		$q = "SELECT * FROM $table " . $query;

		$proxyclass = "{$clazz}_OutletProxy";
		$collection = array();
		foreach ($this->con->query($q, PDO::FETCH_ASSOC) as $row) {
			$obj = new $proxyclass();
			$this->populateObject($clazz, $obj, $row);
			$collection[] = $obj;
		}

		return $collection;
	}

	public function createProxies () {
		foreach ($this->conf['classes'] as $clazz => $settings) {
			$c = "";
			$c .= "class {$clazz}_OutletProxy extends $clazz { \n";
			if (isset($settings['associations'])) {
				foreach ($settings['associations'] as $assoc) {
					$type 	= $assoc[0];
					$prop 	= $assoc[1];
					$entity = $assoc[2];
					$fk_local 	= $assoc[3];
					$fk_foreign	= $assoc[4];

					switch ($type) {
						case 'many-to-one': 
							$c .= "  function get$prop() { \n";
							$c .= "    if (is_null(\$this->$prop)) { \n";
							$c .= "      \$this->$prop = Outlet::getInstance()->load('$entity', \$this->$fk_local); \n";
							$c .= "    } \n";
							$c .= "    return parent::get$prop(); \n";
							$c .= "  } \n";
							break;
						case 'one-to-many':
							$c .= "  function get$prop() { \n";
							$c .= "    \$args = func_get_args(); \n";
							$c .= "    if (count(\$args)) { \n";
							$c .= "      \$q = \$args[0]; \n";
							$c .= "      if (stripos(\$q, 'where') !== false) { \n";
							$c .= "        \$q = 'where $entity.$fk_foreign = '.\$this->$fk_local.' and ' . substr(\$q, 5); \n";
							$c .= "      } else { \n";
							$c .= "        \$q = 'where $entity.$fk_foreign = '.\$this->$fk_local. ' ' . \$q; \n";
							$c .= "      }\n";
							$c .= "      \$this->$prop = Outlet::getInstance()->select('$entity', \$q); \n";
							$c .= "    } \n";
							$c .= "    if (!count(parent::get$prop())) { \n";
							$c .= "      \$this->$prop = Outlet::getInstance()->select('$entity', 'where $entity.$fk_foreign = '.\$this->$fk_local); \n";
							$c .= "    } \n";
							$c .= "    return parent::get{$assoc['1']}(); \n";
							$c .= "  } \n";
							break;
					}
				}
			}
			$c .= "} \n";

			//echo $c;

			eval($c);
		}
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

		$obj = $this->populateObject($clazz, $obj, $row);

		// save in identity map
		$this->identity_map[$clazz][$this->getPK($clazz, $obj)] = $obj;
	
		return $obj;	
	}

	function getConnection () {
		return $this->con;
	}
	

	public function update(&$obj) {
		$clazz = str_replace('_OutletProxy', '', get_class($obj));
		$table = $this->conf['classes'][$clazz]['table'];

		$q = "UPDATE $table \n";
		$q .= "SET \n";

		$ups = array();
		foreach ($this->conf['classes'][$clazz]['fields'] as $key=>$f) {
			// skip primary key 
			if (@$f[2]['pk']) continue;

			$value = $this->con->quote( $obj->$key );
			$ups[] = "  $f[0] = $value";
		}
		$q .= implode(", \n", $ups);

		$q .= "\nWHERE ";

		$clause = array();
		foreach ($this->getPkFields($clazz) as $key=>$pk) {
			$value = $this->con->quote( $obj->$key );
			$clause[] = "$pk[0] = {$obj->$key}";
		}
		$q .= implode(' AND ', $clause);

		$this->con->exec($q);
	}

	private function populateObject($clazz, $obj, array $values) {
		$fields = $this->conf['classes'][$clazz]['fields'];
		foreach ($fields as $key=>$f) {
			$obj->$key = $values[$f[0]];
		}

		return $obj;
	}
	
	private function getTable($clazz) {
		return $this->conf['classes'][$clazz]['table'];
	}
		
	private function getFields ($clazz) {
		return $this->conf['classes'][$clazz]['fields'];
	}
	
	private function getPkFields( $clazz ) {
		$fields = $this->conf['classes'][$clazz]['fields'];
		
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
}
