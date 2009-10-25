<?php
/**
 * Configuration used to perform data mapping
 * @package outlet
 */
class OutletConfig {
	/**
	 * @var array
	 */
	public $conf;

	private $con;

	private $entities;

	/**
	 * Constructs a new instance of OutletConfig
	 * @param object $conf configuration
	 * @return OutletConfig instance
	 */
	function __construct (array $conf) {
		// validate config
		if (!isset($conf['connection'])) {
			throw new OutletConfigException('Element [connection] not found in configuration');
		}

		if (!isset($conf['connection']['dsn']) && !isset($conf['connection']['pdo'])) {
			throw new OutletConfigException('You must set either [connection][pdo] or [connection][dsn] in configuration');
		}

		if (!isset($conf['connection']['dialect'])) {
			throw new OutletConfigException('Element [connection][dialect] not found in configuration');
		}
		
		if (!isset($conf['classes'])) {
			throw new OutletConfigException('Element [classes] missing in configuration');
		}

		$this->conf = $conf;
	}	

	/**
	 * Retrieve the singleton connection as configured in the configuration
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
	 * Retrieves the singleton entities array as configured in the configuration
	 * @return array entities array
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
	 * Retrieve the specified entity
	 * @param string $cls entity class to retrieve
	 * @return OutletEntityConfig
	 */
	function getEntity ($cls) {
		if (is_null($this->entities)) {
			$this->getEntities();
		}
		
		if (!isset($this->entities[$cls])) {
			throw new OutletException('Entity ['.$cls.'] has not been defined in the configuration');
		}
		
		return $this->entities[$cls];
	}
	
	/**
	 * Retrieves the getters and setters setting from the configuration
	 * @return bool true if outlet should use getters and setters, false otherwise
	 */
	function useGettersAndSetters () {
		return isset($this->conf['useGettersAndSetters']) ? $this->conf['useGettersAndSetters'] : false;
	}
}

/**
 * Configuration entity
 * @package outlet
 */
class OutletEntityConfig {
	private $data;

	private $config;

	private $clazz;
	private $props;
	private $associations;
	
	private $sequenceName = '';
	
	private $useGettersAndSetters;

	/**
	 * Construct a new instance of OutletEntityConfig
	 * @param OutletConfig $config outlet configuration
	 * @param object       $entity entity
	 * @param object       $conf configuration
	 * @return OutletEntityConfig instance
	 */
	function __construct (OutletConfig $config, $entity, array $conf) {
		$this->config = $config;

		if (!isset($conf['table'])) {
			throw new OutletConfigException('Mapping for entity ['.$entity.'] is missing element [table]');
		}
		
		if (!isset($conf['props'])) {
			throw new OutletConfigException('Mapping for entity ['.$entity.'] is missing element [props]');
		}

		// i need to leave this for for the outletgen script
		//if (!class_exists($entity)) throw new OutletConfigException('Class does not exist for mapped entity ['.$entity.']');

		// validate that there's a pk
		foreach ($conf['props'] as $p=>$f) {
			if (@$f[2]['pk']) {
				$pk = $p;
				break;
			}
		}
		if (!isset($pk)) {
			throw new OutletConfigException("Entity [$entity] must have at least one column defined as a primary key in the configuration");
		}

		// save basic data
		$this->table = $conf['table'];
		$this->clazz = $entity;
		$this->props = $conf['props'];
		$this->sequenceName = isset($conf['sequenceName']) ? $conf['sequenceName'] : '';
		
		$this->useGettersAndSetters = isset($conf['useGettersAndSetters']) ? $conf['useGettersAndSetters'] : $config->useGettersAndSetters();
		
		// Adjusts sequence name for postgres if it is not specified
		if (($config->getConnection()->getDialect() == 'pgsql') && ($this->sequenceName == ''))
		{
			foreach ($this->props as $key=>$d) {
				// Property needs to be primary key and auto increment
				if ((isset($d[2]['pk']) && $d[2]['pk']) && (isset($d[2]['autoIncrement']) && $d[2]['autoIncrement'])){
					// default name for sequence = {table}_{column}_seq
					$this->sequenceName = $this->table.'_'.$d[0].'_seq';
					break;					
				}
			}
		}
	}

	/**
	 * Retrieves the class name
	 * @return string class name
	 */
	function getClass () {
		return $this->clazz;
	}

	/**
	 * Retrieves the table name
	 * @return string table name
	 */
	function getTable () {
		return $this->table;
	}

	/**
	 * Retrieves the properties
	 * @return array properties
	 */
	function getProperties () {
		return $this->props;
	}
	
	/**
	 * Retrieves a specific propert, if the property doesn't exist throws an OutletConfigException
	 * @param string $prop property to retrieve
	 * @return array property
	 */
	function getProperty ($prop) {
		if (!isset($this->props[$prop])) {
			throw new OutletConfigException('Entity ['.$this->getClass().'] does not have a property ['.$prop.'] defined in the configuration');
		}
		
		return $this->props[$prop];
	}
	
	/**
	 * Retrieves the primary key columns for the entity, if there are no primary keys defined, returns an empty array
	 * @return array Primary key columns for this entity
	 */
	function getPkColumns () {
		// get the pk column in order to check the map
		$pk = array();
		foreach ($this->props as $key=>$d) {
			if (isset($d[2]['pk']) && $d[2]['pk']) {
				$pk[] = $d[0];
			}
		}
		return $pk;
	}

	/**
	 * Retrieves the assocations for the entity
	 * @return array OutletAssociationConfig collection
	 */
	function getAssociations () {
		if (is_null($this->associations)) {
			$this->associations = array();
			$conf = $this->config->conf['classes'][$this->clazz];
			if (isset($conf['associations'])) {
				foreach ($conf['associations'] as $assoc) {
					switch ($assoc[0]) {
						case 'one-to-many': 
							$a = new OutletOneToManyConfig($this->config, $this->getClass(), $assoc[1], $assoc[2]);
							break;
						case 'many-to-one':
							$a = new OutletManyToOneConfig($this->config, $this->getClass(), $assoc[1], $assoc[2]);
							break;
						case 'many-to-many':
							$a = new OutletManyToManyConfig($this->config, $this->getClass(), $assoc[1], $assoc[2]);
							break;
						case 'one-to-one':
							$a = new OutletOneToOneConfig($this->config, $this->getClass(), $assoc[1], $assoc[2]);
							break;
						default:
							$a = new OutletAssociationConfig($this->config, $assoc[0], $this->getClass(), $assoc[1], $assoc[2]);
					}
					$this->associations[] = $a;
				}
			}
		}
		return $this->associations;
	}
	
	/**
	 * Retrieves a specific association by name, if it cannot be found returns null
	 * @param string $name the association to retrieve
	 * @return OutletAssociationConfig association
	 */
	function getAssociation ($name) {
		foreach ($this->getAssociations() as $assoc) {
			//$assoc = new OutletAssociationConfig();
			if ($assoc->getForeignName() == $name) {
				return $assoc;
			}
		}
	}
	
	/**
	 * Retrieves all the primary key fields
	 * 
	 * This function is identical to OutletEntityConfig::getPkColumns()
	 * @see OutletEntityConfig::getPkColumns()
	 * @todo Determine if both getPkColumns() and getPkFields() are needed, if not, remove one of them.
	 * @return array the array of primary key fields, may be empty if none are found
	 */
	function getPkFields () {
		$fields = array();
		foreach ($this->props as $prop=>$def) {
			if (isset($def[2]['pk']) && $def[2]['pk']) {
				$fields[] = $prop;
			} 
		}
		return $fields;
	}
	
	/**
	 * Retrieves the sequence name
	 * @return string sequence name
	 */
	function getSequenceName(){
		return $this->sequenceName;
	}
	
	/**
	 * Retrieves the use getters and setters setting
	 * @return bool whether or not to use getters and setters instead of properties
	 */
	function useGettersAndSetters () {
		return $this->useGettersAndSetters;
	}
}

/**
 * Abstract base class for all outlet associations.
 * 
 * @see OutletOneToManyConfig
 * @see OutletManyToOneConfig
 * @see OutletOneToOneConfig
 * @see OutletManyToManyConfig
 */
abstract class OutletAssociationConfig {
	protected $config;

	protected $local;
	protected $pk;
	protected $foreign;
	protected $type;
	protected $key;
    protected $localUseGettersAndSetters;
    protected $foreignUseGettersAndSetters;

	/**
	 * Abstract constructor
	 * @param OutletConfig $config configuration
	 * @param string $type Type of association: one-to-many, many-to-one, etc
	 * @param string $local Name of the entity where the association is defined
	 * @param string $foreign Name of the entity that is referenced by the association
	 * @param array $options
	 */
	function __construct (OutletConfig $config, $local, $foreign, array $options) {
		$this->config 	= $config;

        $this->local 	= $local;
		$this->foreign 	= $foreign;
		$this->options	= $options;
        $this->localUseGettersAndSetters = $this->config->getEntity($local)->useGettersAndSetters();
        $this->foreignUseGettersAndSetters = $this->config->getEntity($foreign)->useGettersAndSetters();
	}

	/**
	 * Retrieve the foreign getters and setters setting
	 * @return bool whether or not to use getters and setters for the foreign table instead of properties
	 */
    function getForeignUseGettersAndSetters(){
        return $this->foreignUseGettersAndSetters;
    }
	
	/**
	 * Retrieve the local getters and setters setting
	 * @return bool whether or not to use getters and setters for the local table instead of properties
	 */
    function getLocalUseGettersAndSetters(){
        return $this->localUseGettersAndSetters;
    }

	/**
	 * Retrieve the local entity name
	 * @return string local entity name
	 */
	function getLocal () {
		return $this->local;
	}

	/**
	 * Retrieve the type
	 * @return string type 
	 */
	function getType () {
		return $this->type;
	}

	/**
	 * Retrieve the optional flag
	 * @return bool true if optional, false otherwise
	 */
	function isOptional () {
		return (isset($this->options['optional']) && $this->options['optional']);
	}

	/**
	 * Retrieve the foreign entity name
	 * @return string foreign entity name
	 */
	function getForeign () {
		return $this->foreign;
	}

	/**
	 * Retrieve the getter for the foreign entity
	 * @return string getter function name
	 */
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
	 * Retrieve the setter for the foreign entity
	 * @return string setter function name
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
	 * Retrieves the name of the association
	 * @return string name of the association
	 */
	function getForeignName () {
		if (isset($this->options['name'])) {
			$name = $this->options['name'];
		} else {
			$name = $this->foreign;
		}
		return $name;
	}

	/**
	 * Retrieves the pluralized foreign entity name
	 * 
	 * if plural is defined in the configuration it will return that value
	 * otherwise it will take the entity name and append an 's'
	 * 
	 * if the foreign entity cannot be found the function throws an OutletConfigException
	 * 
	 * @return string pluralized foreign entity
	 */
	function getForeignPlural () {
		// if this association has a name
		if (isset($this->options['name'])) {
			// if this association has a plural, use that
			// else use the name plus an 's' 
			if (isset($this->options['plural'])) {
				$plural = $this->options['plural'];
			} else {
				$plural = $this->options['name'].'s';
			} 
		// else check the entity definition
		} else {
			if (!isset($this->config->conf['classes'][$this->foreign])) { 
				throw new OutletConfigException("Entity [{$this->foreign}] not found in configuration");
			}
			
			$foreign_def = $this->config->conf['classes'][$this->foreign];
			// if there's a plural defined at the foreign entity
			// else use the entity plus an 's'
			if (isset($foreign_def['plural'])) {
				$plural = $foreign_def['plural'];
			} else {
				$plural = $this->foreign.'s';
			}
		}
		return $plural;
	}
}

/**
 * Concrete One to Many Association
 * @see OutletAssociationConfig
 */
class OutletOneToManyConfig extends OutletAssociationConfig {
	protected $type = 'one-to-many';
	
	/**
	 * Constructs a new instance of OutletOneToManyConfig
	 * @see OutletAssociationConfig::__construct($config, $local, $foreign, $options)
	 * @param OutletConfig $config configuration
	 * @param object       $local local entity
	 * @param object       $foreign foreign entity
	 * @param array        $options options 
	 * @return OutletOneToManyConfig instance
	 */
	public function __construct (OutletConfig $config, $local, $foreign, array $options) {
		// one-to-many requires a key
		if (!isset($options['key'])) {
			throw new OutletConfigException("Entity $local, association with $foreign: You must specify a 'key' when defining a one-to-many relationship");
		}

		parent::__construct($config, $local, $foreign, $options);
	}
	
	/**
	 * Retrieves the local key
	 * @return mixed local key
	 */
	public function getKey() {
		return $this->options['key'];
	}
	
	/**
	 * Retrieves the reference key
	 * @return mixed reference key
	 */
	function getRefKey () {
		if (isset($this->options['refKey'])) {
			return $this->options['refKey'];
		} else {
			return current($this->config->getEntity($this->local)->getPkFields());
		}
	}
}

/**
 * Concrete Many to One Association
 * @see OutletAssociationConfig
 */
class OutletManyToOneConfig extends OutletAssociationConfig {
	protected $type = 'many-to-one';
	
	/**
	 * Constructs a new instance of OutletManyToOneConfig
	 * @param OutletConfig $config configuration
	 * @param object       $local local entity
	 * @param object       $foreign foreign entity
	 * @param array       $options options
	 * @return OutletManyToOneConfig instance
	 */
	public function __construct (OutletConfig $config, $local, $foreign, array $options) {
		// many-to-one requires a key
		if (!isset($options['key'])) {
			throw new OutletConfigException("Entity $local, association with $foreign: You must specify a 'key' when defining a many-to-one relationship");
		}
		
		parent::__construct($config, $local, $foreign, $options);
	}
	
	/**
	 * Retrieves the local key
	 * @return mixed local key
	 */
	public function getKey() {
		return $this->options['key'];
	}
	
	/**
	 * Retrieves the reference key
	 * @return mixed reference key
	 */
	function getRefKey () {
		if (isset($this->options['refKey'])) {
			return $this->options['refKey'];
		} else {
			return current($this->config->getEntity($this->foreign)->getPkFields());
		}
	}
}

/**
 * Concrete One to One Association
 * @see OutletAssociationConfig
 */
class OutletOneToOneConfig extends OutletAssociationConfig {
	protected $type = 'one-to-one'; 
	
	/**
	 * Construct a new instance of OutletOneToOneConfig
	 * @param OutletConfig $config configuration
	 * @param object       $local local entity
	 * @param object       $foreign foreign entity
	 * @param array        $options options
	 * @return OutletOneToOneConfig instance
	 */
	public function __construct (OutletConfig $config, $local, $foreign, array $options) {
		if (!isset($options['key'])) {
			throw new OutletConfigException("Entity $local, association with $foreign: You must specify a 'key' when defining a one-to-one relationship");
		}
	
		parent::__construct($config, $local, $foreign, $options);
	}
	
	/**
	 * Retrieves the local key
	 * @return mixed local key
	 */
	public function getKey () {
		return $this->options['key'];
	}
	
	/**
	 * Retrieves the reference key
	 * @return mixed reference key
	 */
	function getRefKey () {
		if (isset($this->options['refKey'])) {
			return $this->options['refKey'];
		} else {
			return current($this->config->getEntity($this->foreign)->getPkFields());
		}
	}
}

/**
 * Concrete Many to Many Association
 * @see OutletAssociationConfig
 */
class OutletManyToManyConfig extends OutletAssociationConfig {
	protected $type = 'many-to-many';
	protected $table;
	protected $tableKeyLocal;
	protected $tableKeyForeign;
	
	/**
	 * Constructs a new instance of OutletManyToManyConfig
	 * @param OutletConfig $config configuration
	 * @param object       $local local entity
	 * @param object       $foreign foreign entity
	 * @param array        $options options
	 * @return OutletManyToManyConfig instance
	 */
	public function __construct (OutletConfig $config, $local, $foreign, array $options) {
		if (!isset($options['table'])) throw new OutletConfigException("Entity $local, association with $foreign: You must specify a table when defining a many-to-many relationship");
		
		$this->table 			= $options['table'];
		$this->tableKeyLocal 	= $options['tableKeyLocal'];
		$this->tableKeyForeign 	= $options['tableKeyForeign'];
		
		parent::__construct($config, $local, $foreign, $options);
	}
	
	/**
	 * Retrieves the local table key
	 * @return mixed local table key
	 */
	public function getTableKeyLocal () {
		return $this->tableKeyLocal;
	}
	
	/**
	 * Retrieves the foreign table key
	 * @return mixed foreign table key
	 */
	public function getTableKeyForeign () {
		return $this->tableKeyForeign;
	}
	
	/**
	 * Retrieves the linking table
	 * @return string linking table
	 */
	public function getLinkingTable () {
		return $this->table;
	}
	
	/**
	 * Retrieves the local key
	 * @return mixed local key
	 */
	function getKey () {
		if (isset($this->options['key'])) {
			return $this->options['key'];
		} else {
			return current($this->config->getEntity($this->foreign)->getPkFields());
		}
	}

	/**
	 * Retrieves the reference key
	 * @return mixed reference key
	 */
	function getRefKey () {
		if (isset($this->options['refKey'])) {
			return $this->options['refKey'];
		} else {
			return current($this->config->getEntity($this->local)->getPkFields());
		}
	}
}

/**
 * Exception to be thrown by the Outlet Configuration family of classes
 * @see OutletConfig
 * @see OutletEntityConfig
 * @see OutletAssociationConfig
 * @see OutletOneToManyConfig
 * @see OutletManyToOneConfig
 * @see OutletOneToOneConfig
 * @see OutletManyToManyConfig
 */
class OutletConfigException extends OutletException {}
