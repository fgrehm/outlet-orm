<?php
/**
 * File level comment
 * 
 * @package org.outlet-orm
 * @subpackage map
 * @author Alvaro Carrasco
 */

/**
 * OutletEntityMap....
 * 
 * @package org.outlet-orm
 * @subpackage map
 * @author Alvaro Carrasco
 */
class OutletEntityMap
{
	private $class;
	private $table;
	private $props;
	private $pks;
	private $associations = array();
	// options
	private $plural;
	public $useGettersAndSetters;
	private $sequenceName;

	public function __construct($class, $table, array $props, array $pks, $options)
	{
		$this->class = $class;
		$this->table = $table;
		$this->props = $props;
		$this->pks = $pks;
		$this->plural = isset($options['plural']) ? $options['plural'] : $class . 's';
		$this->useGettersAndSetters = isset($options['useGettersAndSetters']) ? $options['useGettersAndSetters'] : false;
		$this->sequenceName = isset($options['sequenceName']) ? $options['sequenceName'] : '';
	}

	public function getClass()
	{
		return $this->class;
	}

	public function getTable()
	{
		return $this->table;
	}

	public function getPlural()
	{
		return $this->plural;
	}

	public function getSequenceName()
	{
		return $this->sequenceName;
	}

	public function getPropMap($name)
	{
		if (!isset($this->props[$name])) {
			throw new OutletException("Property [$name] has not been defined in entity [$this->class]");
		}
		
		return $this->props[$name];
	}

	public function getPropMaps()
	{
		return $this->props;
	}

	public function getPKs()
	{
		return $this->pks;
	}

	public function getPKColumns()
	{
		$cols = array();
		
		foreach ($this->getPKs() as $p) {
			$cols[] = $this->props[$p]->getColumn();
		}
		
		return $cols;
	}

	/**
	 * Populate an object with the values from an associative array indexed by column names
	 * 
	 * @param object $obj Instance of the entity (probably brand new) or a subclass
	 * @param array $values Associative array indexed by column name, it must already be casted
	 * @return object populated entity 
	 */
	public function populate($obj, array $values)
	{
		//TODO Collections objects must be changed to OutletCollections

		foreach ($this->props as $key => $f) {
			if (!array_key_exists($f->getColumn(), $values)) {
				throw new OutletException("Field [{$f->getColumn()}] defined in the config is not defined in table [" . $this->table . "]");
			}
			$this->setProp($obj, $key, OutletMapper::toPhpValue($f->getType(), $values[$f->getColumn()]));
		}
		
		return $obj;
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
		if ($this->useGettersAndSetters) {
			$setter = "set$prop";
			$obj->$setter($value);
		} else {
			$obj->$prop = $value;
		}
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
	 * Get the PK values for the entity, casted to the type defined in the config
	 * 
	 * @param object $obj the entity to get the primary key values for
	 * @return array the primary key values
	 */
	public function getPkValues($obj)
	{
		$pks = array();
		
		foreach ($this->getPKs() as $f) {
			$value = $this->getProp($obj, $f);

			if ($this->props[$f]->getType() == 'int') {
				$value = (int) $value;
			}
			
			$pks[$f] = $value;
		}
		return $pks;
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
			$arr[$key] = OutletMapper::toSqlValue($p->getType(), $this->getProp($entity, $key));
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
			$column = $p->getColumn();
			
			if (!array_key_exists($column, $row)) {
				throw new OutletException('No value found for [' . $column . '] in row [' . var_export($row, true) . ']');
			}
			
			// cast if it's anything other than a string
			$row[$column] = OutletMapper::toPhpValue($p->getType(), $row[$column]);
		}
	}

	/**
	 * @param string $name
	 * @return OutletAssociation
	 */
	public function getAssociation($name)
	{
		return $this->associations[$name];
	}

	public function addAssociation(OutletAssociation $assoc)
	{
		$this->associations[$assoc->getName()] = $assoc;
	}

	public function getAssociations()
	{
		return $this->associations;
	}

	public function __toString()
	{
		$s = " {$this->class} (table={$this->table})\n";
		
		foreach ($this->props as $prop) {
			$s .= $prop;
		}
		
		foreach ($this->associations as $assoc) {
			$s .= $assoc;
		}
		
		$s .= "\n";
		
		return $s;
	}
}