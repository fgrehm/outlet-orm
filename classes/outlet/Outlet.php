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

	public function select ( $clazz, $query='') {
		$table = $this->conf['classes'][$clazz]['table'];

		foreach ($this->conf['classes'] as $cls=>$settings) {
			//$query = str_replace($cls, $settings['table'], $query);
			foreach ($settings['props'] as $field=>$s) {
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
		$s = "<?php\n";
		foreach ($this->conf['classes'] as $clazz => $settings) {
			$c = "";
			$c .= "class {$clazz}_OutletProxy extends $clazz { \n";
			if (isset($settings['associations'])) {
				foreach ($settings['associations'] as $assoc) {	
					$type 	= $assoc[0];
					$entity = $assoc[1];

					//$fk_local 	= $assoc[3];

					switch ($type) {
						case 'many-to-one': 
							//$foreign_pk = $this->conf['classes'][$entity]['pk'];
							$local_key = $assoc[2]['local_key'];

							$c .= "  function get$entity() { \n";
							$c .= "    if (is_null(parent::get$entity())) { \n";
							$c .= "      parent::set$entity( Outlet::getInstance()->load('$entity', \$this->$local_key) ); \n";
							$c .= "    } \n";
							$c .= "    return parent::get$entity(); \n";
							$c .= "  } \n";
							$c .= "  function set$entity($entity \$ref) { \n";
							$c .= "    \$mapped = new OutletMapper(\$ref); \n";
							$c .= "    \$this->$local_key = \$mapped->getPK(); \n";
							$c .= "    return parent::set$entity(\$ref); \n";
							$c .= "  } \n";
							break;
						case 'one-to-many':
							$fk_foreign = $assoc[2]['foreign_key'];
							$pk_prop = OutletMapper::getPkProp($clazz);

							$c .= "  function get{$entity}s() { \n";
							$c .= "    \$args = func_get_args(); \n";
							$c .= "    if (count(\$args)) { \n";
							$c .= "      if (is_null(\$args[0])) return parent::get{$entity}s(); \n";
							$c .= "      \$q = \$args[0]; \n";
							$c .= "    } else { \n";
							$c .= "      \$q = ''; \n";
							$c .= "    } \n";
					
							//$c .= "      if (\$q===false) return parent::get$prop(); \n";
							
							// if there's a where clause
							$c .= "    if (stripos(\$q, 'where') !== false) { \n";
							$c .= "      \$q = 'where $entity.$fk_foreign = '.\$this->$pk_prop.' and ' . substr(\$q, 5); \n";
							$c .= "    } else { \n";
							$c .= "      \$q = 'where $entity.$fk_foreign = '.\$this->$pk_prop. ' ' . \$q; \n";
							$c .= "    }\n";
							$c .= "    parent::set{$entity}s( Outlet::getInstance()->select('$entity', \$q) ); \n";
							/** not sure if i need this
							$c .= "    if (!count(parent::get{$entity}s())) { \n";
							$c .= "      \$this->$prop = Outlet::getInstance()->select('$entity', 'where $entity.$fk_foreign = '.\$this->$fk_local); \n";
							$c .= "    } \n";
							*/
							$c .= "    return parent::get{$entity}s(); \n";
							$c .= "  } \n";
							break;
					}
				}
			}
			$c .= "} \n";

			//echo $c;

			eval($c);

			$s .= $c;
		}
		//file_put_contents(APPROOT.'/docroot/outlet-proxies.php', $s);
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
