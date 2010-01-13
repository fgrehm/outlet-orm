<?php
/**
 * Configuration entity
 * 
 * @package outlet
 */
class OutletEntityConfig
{
	/**
	 * @var unknown_type
	 */
	private $data;
	
	/**
	 * @var OutletConfig
	 */
	private $config;
	
	/**
	 * @var string
	 */
	public $clazz;
	
	/**
	 * @var array
	 */
	private $props;
	
	/**
	 * @var array
	 */
	private $associations;
	
	/**
	 * @var string
	 */
	private $sequenceName;
	
	/**
	 * @var bool
	 */
	public $useGettersAndSetters;

	/**
	 * Construct a new instance of OutletEntityConfig
	 * @param OutletConfig $config outlet configuration
	 * @param object       $entity entity
	 * @param object       $conf configuration
	 * @return OutletEntityConfig instance
	 */
	public function __construct(OutletConfig $config, $entity, array $conf)
	{
		$this->associations = array();
		$this->sequenceName = '';
		
		$this->config = $config;
		
		if (!isset($conf['table'])) {
			throw new OutletConfigException('Mapping for entity [' . $entity . '] is missing element [table]');
		}
		
		if (!isset($conf['props'])) {
			throw new OutletConfigException('Mapping for entity [' . $entity . '] is missing element [props]');
		}
		
		// i need to leave this for for the outletgen script
		//if (!class_exists($entity)) throw new OutletConfigException('Class does not exist for mapped entity ['.$entity.']');
		

		// validate that there's a pk
		foreach ($conf['props'] as $p => $f) {
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
		$this->plural = isset($conf['plural']) ? $conf['plural'] : $this->clazz . 's';
		
		// Adjusts sequence name for postgres if it is not specified
		if (($config->getConnection()->getDialect() == 'pgsql') && ($this->sequenceName == '')) {
			foreach ($this->props as $key => $d) {
				// Property needs to be primary key and auto increment
				if ((isset($d[2]['pk']) && $d[2]['pk']) && (isset($d[2]['autoIncrement']) && $d[2]['autoIncrement'])) {
					// default name for sequence = {table}_{column}_seq
					$this->sequenceName = $this->table . '_' . $d[0] . '_seq';
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
	public function getPkValues($obj)
	{
		$pks = array();
		
		foreach ($this->props as $key => $p) {
			if (isset($p[2]['pk']) && $p[2]['pk']) {
				$value = $this->getProp($obj, $key);
				
				// cast it if the property is defined to be an int
				if ($p[1] == 'int') {
					$value = (int) $value;
				}
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
	
	public function getProp($obj, $prop)
	{
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
	public function setProp($obj, $prop, $value)
	{
		/*
		TODO may use the method_exists and don't get anymore from the config, caching the result obviously
		
		Something like:
		
		public function setProp($obj, $prop, $value)
		{
			$setter = "set" . $prop;
			
			if ($this->useGettersAndSetters($setter)) {
				$obj->$setter($value);
			} else {
				$obj->$prop = $value;
			}
		}
		
		protected function useGettersAndSetters($obj, $setter)
		{
			if (is_null($this->useGettersAndSetter)) {
				$this->useGettersAndSetter = method_exists($obj, $setter);
			}
			
			return $this->useGettersAndSetter;
		}
		 */
		
		if ($this->useGettersAndSetters) {
			$setter = "set$prop";
			$obj->$setter($value);
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
	public function toRow($entity)
	{
		if (!$entity) {
			throw new OutletException('You must pass an entity');
		}
		
		$arr = array();
		
		foreach ($this->props as $key => $p) {
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
	public function castRow(array &$row)
	{
		foreach ($this->props as $key => $p) {
			$column = $p[0];
			
			if (!array_key_exists($column, $row)) {
				throw new OutletException('No value found for [' . $column . '] in row [' . var_export($row, true) . ']');
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
	public function populateObject($obj, array $values)
	{
		foreach ($this->props as $key => $f) {
			if (!array_key_exists($f[0], $values)) {
				throw new OutletException("Field [$f[0]] defined in the config is not defined in table [" . $this->table . "]");
			}
			
			$this->setProp($obj, $key, OutletMapper::toPhpValue($f[1], $values[$f[0]]));
		}
		
		return $obj;
	}

	/**
	 * Retrieves the properties
	 * 
	 * @return array properties
	 */
	public function getProperties()
	{
		return $this->props;
	}

	/**
	 * Retrieves a specific propert, if the property doesn't exist throws an OutletConfigException
	 * 
	 * @param string $prop property to retrieve
	 * @return array property
	 */
	public function getProperty($prop)
	{
		if (!isset($this->props[$prop])) {
			throw new OutletConfigException('Entity [' . $this->getClass() . '] does not have a property [' . $prop . '] defined in the configuration');
		}
		
		return $this->props[$prop];
	}

	/**
	 * Retrieves the primary key columns for the entity, if there are no primary keys defined, returns an empty array
	 * 
	 * @return array Primary key columns for this entity
	 */
	public function getPkColumns()
	{
		// get the pk column in order to check the map
		$pk = array();
		
		foreach ($this->props as $key => $d) {
			if (isset($d[2]['pk']) && $d[2]['pk']) {
				$pk[] = $d[0];
			}
		}
		
		return $pk;
	}

	/**
	 * Retrieves the assocations for the entity
	 * 
	 * @return array OutletAssociationConfig collection
	 */
	function getAssociations()
	{
		return $this->associations;
	}

	/**
	 * Retrieves a specific association by name, if it cannot be found returns null
	 * 
	 * @param string $name the association to retrieve
	 * @return OutletAssociationConfig association
	 */
	public function getAssociation($name)
	{
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
	 * @return array the array of primary key fields, may be empty if none are found
	 */
	public function getPkFields()
	{
		//TODO Determine if both getPkColumns() and getPkFields() are needed, if not, remove one of them.
		
		$fields = array();
		
		foreach ($this->props as $prop => $def) {
			if (isset($def[2]['pk']) && $def[2]['pk']) {
				$fields[] = $prop;
			}
		}
		
		return $fields;
	}

	/**
	 * Retrieves the sequence name
	 * 
	 * @return string sequence name
	 */
	public function getSequenceName()
	{
		return $this->sequenceName;
	}
}