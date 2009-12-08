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

	private $entities = array();

	/**
	 * @var array
	 */
	private $classes;

	public $useGettersAndSetters = false;

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
		
		$this->classes = array_keys($conf['classes']);


		$conn = $conf['connection'];
		if (isset($conn['pdo'])) {
			$pdo = $conn['pdo'];
		} else {
			$pdo = new PDO($conn['dsn'], @$conn['username'], @$conn['password']);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}

		$this->con = new OutletConnection($pdo, $conn['dialect']);

		if (isset($conf['useGettersAndSetters'])) $this->useGettersAndSetters = $this->conf['useGettersAndSetters'];

		// create the entity configs
		foreach ($conf['classes'] as $key=>$cls) {
			$this->entities[$key] = new OutletEntityConfig($this, $key, $cls);
		}
	}	

	/**
	 * Retrieve the singleton connection as configured in the configuration
	 * @return OutletConnection
	 */
	function getConnection () {
		return $this->con;
	}

	/**
	 * Retrieves the singleton entities array as configured in the configuration
	 * @return array entities array
	 */
	function getEntities () {
		return $this->entities;
	}

	function getEntityClass ($obj) {
		foreach ($this->classes as $cls) {
			if ($obj instanceof $cls) return $cls;
		}
		throw new OutletException('Object ['.get_class($obj).'] not configured');
	}

	/**
	 * Retrieve the specified entity
	 * @param string $cls entity class to retrieve
	 * @return OutletEntityConfig
	 */
	function getEntity ($cls) {
		if (!isset($this->entities[$cls])) {
			throw new OutletException('Entity ['.$cls.'] has not been defined in the configuration');
		}
		
		return $this->entities[$cls];
	}

	public function getEntityForObject ($obj) {
		return $this->getEntity($this->getEntityClass($obj));
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

	public $clazz;
	private $props;
	private $associations = array();
	
	private $sequenceName = '';
	
	public $useGettersAndSetters;

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
		
		$this->useGettersAndSetters = isset($conf['useGettersAndSetters']) ? $conf['useGettersAndSetters'] : $config->useGettersAndSetters;

		// if there's a plural defined at the foreign entity
		// else use the entity plus an 's'
		$this->plural = isset($conf['plural']) ? $conf['plural'] : $this->clazz.'s';
		
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

		// load associations	
		if (isset($conf['associations'])) {
			foreach ($conf['associations'] as $assoc) {
				switch ($assoc[0]) {
					case 'one-to-many': 
						$a = new OutletOneToManyConfig($this->config, $this->clazz, $assoc[1], $assoc[2]);
						break;
					case 'many-to-one':
						$a = new OutletManyToOneConfig($this->config, $this->clazz, $assoc[1], $assoc[2]);
						break;
					case 'many-to-many':
						$a = new OutletManyToManyConfig($this->config, $this->clazz, $assoc[1], $assoc[2]);
						break;
					case 'one-to-one':
						$a = new OutletOneToOneConfig($this->config, $this->clazz, $assoc[1], $assoc[2]);
						break;
					default:
						$a = new OutletAssociationConfig($this->config, $assoc[0], $this->clazz, $assoc[1], $assoc[2]);
				}
				$this->associations[] = $a;
			}
		}
	}

	/**
	 * Get the PK values for the entity, casted to the type defined in the config
	 * 
	 * @param object $obj the entity to get the primary key values for
	 * @return array the primary key values
	 */
	public function getPkValues ($obj) {
		$pks = array();

		foreach ($this->props as $key=>$p) {
			if (isset($p[2]['pk']) && $p[2]['pk']) {
				$value = $this->getProp($obj, $key);

				// cast it if the property is defined to be an int
				if ($p[1]=='int') $value = (int) $value;
				
				$pks[$key] = $value;
			}
		}
		return $pks;
	}

	/**
	 * Get the value of an entity property using the method specified in the config: public prop or getter
	 * 
	 * @param object $obj entity to retrieve value from
	 * @param string $prop property to retrieve
	 * @return mixed the value of the property on the entity
	 */

	public function getProp ($obj, $prop) {
		if ($this->useGettersAndSetters) {
			$getter = "get$prop";
			return $obj->$getter();
		} else {
			return $obj->$prop;
		}
	}

	/**
	 * Set the value of an entity property using the method specified in the config: public prop or setter
	 * 
	 * @param object $obj entity to set property on
	 * @param string $prop property to set
	 * @param mixed $value value to set property to 
	 * @param bool $useSetter whether to use a setter
	 */
	public function setProp ($obj, $prop, $value) {
		if ($this->useGettersAndSetters) {
			$setter = "set$prop";
			$obj->$setter( $value );
		} else {
			$obj->$prop = $value;
		}
	}

	/**
	 * Translates an entity into an associative array, applying OutletMapper::toSqlValue($conf, $v) on all values
	 * 
	 * @see OutletMapper::toSqlValue($conf, $v)
	 * @param object $entity entity to translate into an array
	 * @return array entity values
	 */
	public function toRow ($entity) {
		if (!$entity) throw new OutletException('You must pass an entity');

		$arr = array();
		foreach ($this->props as $key=>$p) {
			$arr[$key] = OutletMapper::toSqlValue($p[1], $this->getProp($entity, $key));
		}
		return $arr;
	}

	/**
	 * Cast the values of a row coming from the database using the types defined in the config
	 * 
	 * @see OutletMapper::toPhpValue($conf, $v)
	 * @param string $clazz Entity class
	 * @param array $row Row to cast
	 */
	public function castRow (array &$row) {
		foreach ($this->props as $key=>$p) {
			$column = $p[0];

			if (!array_key_exists($column, $row)) {
				throw new OutletException('No value found for ['.$column.'] in row ['.var_export($row, true).']');
			} 

			// cast if it's anything other than a string
			$row[$column] = OutletMapper::toPhpValue($p[1], $row[$column]);
		}
	}

	/**
	 * Populate an object with the values from an associative array indexed by column names
	 * 
	 * @param object $obj Instance of the entity (probably brand new) or a subclass
	 * @param array $values Associative array indexed by column name, it must already be casted
	 * @return object populated entity 
	 */
	public function populateObject ($obj, array $values) {
		foreach ($this->props as $key=>$f) {
			if (!array_key_exists($f[0], $values)) {
				throw new OutletException("Field [$f[0]] defined in the config is not defined in table [".$this->table."]");
			}

			$this->setProp($obj, $key, OutletMapper::toPhpValue($f[1], $values[$f[0]]));
		}

		return $obj;
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
	}

	/**
	 * Retrieve the foreign getters and setters setting
	 * @return bool whether or not to use getters and setters for the foreign table instead of properties
	 */
    function getForeignUseGettersAndSetters(){
        return $this->config->getEntity($this->foreign)->useGettersAndSetters;
    }
	
	/**
	 * Retrieve the local getters and setters setting
	 * @return bool whether or not to use getters and setters for the local table instead of properties
	 */
    function getLocalUseGettersAndSetters(){
        return $this->config->getEntity($this->local)->useGettersAndSetters;
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
			$foreignCfg = $this->config->getEntity($this->foreign);

			$plural = $foreignCfg->plural;
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
