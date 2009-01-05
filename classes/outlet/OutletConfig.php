<?php

class OutletConfig {
	public $conf;

	private $con;

	private $entities;

	function __construct (array $conf) {
		// validate config
		if (!isset($conf['connection'])) throw new OutletConfigException('Element [connection] not found in configuration');
		if (!isset($conf['connection']['dsn'])) throw new OutletConfigException('Element [connection][dsn] not found in configuration');
		if (!isset($conf['connection']['dialect'])) throw new OutletConfigException('Element [connection][dialect] not found in configuration');
		if (!isset($conf['classes'])) throw new OutletConfigException('Element [classes] missing in configuration');

		$this->conf = $conf;
	}	

	function getConnection () {
		if (!$this->con) {
			$conn = $this->conf['connection'];

			$dsn = $conn['dsn'];
			$driver = substr($dsn, 0, strpos($dsn, ':'));

			$pdo = new PDO($conn['dsn'], @$conn['username'], @$conn['password']);

			$this->con = new OutletConnection($pdo, $driver, $conn['dialect']);
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
	function getEntity ($cls) {
		if (is_null($this->entities)) $this->getEntities();

		return $this->entities[$cls];
	}
}

class OutletEntityConfig {
	private $data;

	private $config;

	private $clazz;
	private $props;
	private $associations;

	function __construct (OutletConfig $config, $entity, array $conf) {
		$this->config = $config;

		if (!isset($conf['table'])) throw new OutletConfigException('Mapping for entity ['.$entity.'] is missing element [table]');
		if (!isset($conf['props'])) throw new OutletConfigException('Mapping for entity ['.$entity.'] is missing element [props]');

		// i need to leave this for for the outletgen script
		//if (!class_exists($entity)) throw new OutletConfigException('Class does not exist for mapped entity ['.$entity.']');

		// validate that there's a pk
		foreach ($conf['props'] as $p=>$f) {
			if (@$f[2]['pk']) {
				$pk = $p;
				break;
			}
		}
		if (!isset($pk)) throw new OutletConfigException("Entity [$entity] must have at least one column defined as a primary key in the configuration");

		// save basic data
		$this->table = $conf['table'];
		$this->clazz = $entity;
		$this->props = $conf['props'];
	}

	function getClass () {
		return $this->clazz;
	}

	function getTable () {
		return $this->table;
	}

	/**
	 * @return array
	 */
	function getProperties () {
		return $this->props;
	}
	
	/**
	 * @return array Primary key columns for this entity
	 */
	function getPkColumns () {
		// get the pk column in order to check the map
		$pk = array();
		foreach ($this->props as $key=>$d) {
			if (isset($d[2]['pk']) && $d[2]['pk']) $pk[] = $d[0]; 
		}
		return $pk;
	}

	/**
	 * @return array OutletAssociationConfig collection
	 */
	function getAssociations () {
		if (is_null($this->associations)) {
			$this->associations = array();
			$conf = $this->config->conf['classes'][$this->clazz];
			if (isset($conf['associations'])) {
				foreach ($conf['associations'] as $assoc) {
					$this->associations[] = new OutletAssociationConfig($this->config, $assoc[0], $this->getClass(), $assoc[1], $assoc[2]);
				}
			}
		}
		return $this->associations;
	}
	
	/**
	 * @param string $name
	 * @return OutletAssociationConfig
	 */
	function getAssociation ($name) {
		foreach ($this->getAssociations() as $assoc) {
			//$assoc = new OutletAssociationConfig();
			if ($assoc->getForeignName() == $name) return $assoc;
		}
	}

	function getPkFields () {
		$fields = array();
		foreach ($this->props as $prop=>$def) {
			if (@$def[2]['pk']) $fields[] = $prop;
		}
		return $fields;
	}
	
}

class OutletAssociationConfig {
	private $config;

	private $local;
	private $pk;
	private $foreign;
	private $type;
	private $key;

	/**
	 * @param OutletConfig $config
	 * @param string $type Type of association: one-to-many, many-to-one, etc
	 * @param string $local Name of the entity where the association is defined
	 * @param string $foreign Name of the entity that is referenced by the association
	 * @param array $options
	 */
	function __construct (OutletConfig $config, $type, $local, $foreign, array $options) {
		// all associations require a key
		if (!isset($options['key'])) throw new OutletConfigException("Entity $local, association with $foreign: You must specify a key when defining a $type relationship");

		$this->config 	= $config;

		$this->local 	= $local;
		$this->foreign 	= $foreign;
		$this->type 	= $type;
		$this->options	= $options;
	}

	function getLocal () {
		return $this->local;
	}

	function getType () {
		return $this->type;
	}

	/**
	 * @return string Association key
	 */
	function getKey () {
		return $this->options['key'];
	}

	function getRefKey () {
		if (isset($this->options['refKey'])) {
			return $this->options['refKey'];
		} else {
			if ($this->type == 'one-to-many') {
				return current($this->config->getEntity($this->local)->getPkFields());
			} else {
				return current($this->config->getEntity($this->foreign)->getPkFields());
			}
		}
	}

	function isOptional () {
		return (isset($this->options['optional']) && $this->options['optional']);
	}

	/**
	 * @return string Foreign entity name
	 */
	function getForeign () {
		return $this->foreign;
	}

	function getGetter () {
		switch ($this->type) {
			case 'many-to-one':
			case 'one-to-one':
				return "get".$this->getForeignName();
			default:
				return "get".$this->getForeignPlural();
		}
	}
	
	/**
	 * @return string
	 */
	function getSetter () {
		switch ($this->type) {
			case 'many-to-one':
			case 'one-to-one':
				return "set".$this->getForeignName();
			default: 
				return "set".$this->getForeignPlural();
		}
	}

	/**
	 * @return string Name of the association
	 */
	function getForeignName () {
		if (isset($this->options['name'])) {
			$name = $this->options['name'];
		} else {
			$name = $this->foreign;
		}
		return $name;
	}

	function getForeignPlural () {
		// if this association has a name
		if (isset($this->options['name'])) {
			// if this association has a plural, use that
			// else use the name plus an 's' 
			if (isset($this->options['plural'])) $plural = $this->options['plural'];
			else $plural = $this->options['name'].'s';
		// else check the entity definition
		} else {
			if (!isset($this->config->conf['classes'][$this->foreign])) 
				throw new OutletConfigException("Entity [{$this->foreign}] not found in configuration");	
			
			$foreign_def = $this->config->conf['classes'][$this->foreign];
			// if there's a plural defined at the foreign entity
			// else use the entity plus an 's'
			if (isset($foreign_def['plural'])) $plural = $foreign_def['plural'];
			else $plural = $this->foreign.'s';
		}
		return $plural;
	}
}

class OutletConfigException extends OutletException {}
