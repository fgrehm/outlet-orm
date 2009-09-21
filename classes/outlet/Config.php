<?php

class OutletConfig {
	public $conf;

	private $con;

	private $entities;

	function __construct (array $conf) {
		// validate config
		if (!isset($conf['connection'])) throw new OutletConfigException('Element [connection] not found in configuration');

		if (!isset($conf['connection']['dsn']) && !isset($conf['connection']['pdo'])) {
			throw new OutletConfigException('You must set either [connection][pdo] or [connection][dsn] in configuration');
		}

		if (!isset($conf['connection']['dialect'])) throw new OutletConfigException('Element [connection][dialect] not found in configuration');
		if (!isset($conf['classes'])) throw new OutletConfigException('Element [classes] missing in configuration');

		$this->conf = $conf;
	}	

	function getDialect() {
		return $this->conf['connection']['dialect'];
	}

	/**
	 * @return OutletConnection
	 */
	function getConnection () {
		if (!$this->con) {
			$conn = $this->conf['connection'];

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
	 * @return array
	 */
	function getEntities () {
		if (is_null($this->entities)) {
			$this->entities = array();
			foreach ($this->conf['classes'] as $key=>$cls) {
				$this->entities[$key] = new OutletEntityConfig($this, $key, $cls);
			}
		}
		return $this->entities;
	}

	/**
	 * @param string $cls
	 * @return OutletEntityConfig
	 */
	function getEntity ($cls, $throwsException = true) {
		if (is_null($this->entities)) $this->getEntities();

		if (is_object($cls)) {
			if ($cls instanceof OutletProxy)
				$cls = substr(get_class($cls), 0, -(strlen('_OutletProxy')));
			else
				$cls = get_class($cls);
		}

		if (!isset($this->entities[$cls])) {
			if ($throwsException)
				throw new OutletException('Entity ['.$cls.'] has not been defined in the configuration');
			else
				return null;
		}

		return $this->entities[$cls];
	}
	
	function useGettersAndSetters () {
		return isset($this->conf['useGettersAndSetters']) ? $this->conf['useGettersAndSetters'] : false;
	}
}

class OutletEntityConfig {
//	private $config;

	private $clazz;
	private $props;
	
	private $sequenceName = '';
	
	private $useGettersAndSetters;

	function __construct (OutletConfig $config, $entity, array $conf) {
//		$this->config = $config;

		if (!isset($conf['table']))
			throw new OutletConfigException('Mapping for entity ['.$entity.'] is missing element [table]');
		if (!isset($conf['props']))
			throw new OutletConfigException('Mapping for entity ['.$entity.'] is missing element [props]');

		// i need to leave this for for the outletgen script
		//if (!class_exists($entity)) throw new OutletConfigException('Class does not exist for mapped entity ['.$entity.']');

		// validate that there's a pk
		$this->props = array();
		$this->pks = array();
		foreach ($conf['props'] as $propName => $propConf) {
			$propConf = new OutletPropertyConfig($propName, $propConf);
			$this->props[$propName] = $propConf;
			if ($propConf->isPK()) $this->pks[$propName] = $propConf;
		}
		if (count($this->pks) == 0) throw new OutletConfigException("Entity [$entity] must have at least one column defined as a primary key in the configuration");

		// save basic data
		$this->table = $conf['table'];
		$this->clazz = $entity;
//		$this->props = $conf['props'];
		$this->sequenceName = isset($conf['sequenceName']) ? $conf['sequenceName'] : '';
		
		$this->useGettersAndSetters = isset($conf['useGettersAndSetters']) ? $conf['useGettersAndSetters'] : $config->useGettersAndSetters();
	}

	function getClass () {
		return $this->clazz;
	}

	function getTable () {
		return $this->table;
	}

	function getProperties () {
		return $this->props;
	}

	function getProperty($prop, $throwsException = true) {
		if (!isset($this->props[$prop])) {
			if ($throwsException)
				throw new OutletConfigException("Property [{$this->clazz}.{$prop}] not found in configuration");
			else
				return null;
		}
		return $this->props[$prop];
	}

	function getPkProperties () {
		return $this->pks;
	}
	
	function useGettersAndSetters () {
		return $this->useGettersAndSetters;
	}
}

class OutletPropertyConfig {
	private $name;
	private $config;
	public function __construct($name, $config) {
		$this->name = $name;
		$this->config = $config;
	}

	public function getName() {
		return $this->name;
	}

	public function getField() {
		return $this->config[0];
	}

	public function getType() {
		return $this->config[1];
	}

	public function isPK() {
		return isset($this->config[2]['pk']) && $this->config[2]['pk'];
	}
}

class OutletConfigException extends OutletException {}
