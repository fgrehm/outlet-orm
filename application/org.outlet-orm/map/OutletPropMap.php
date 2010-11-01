<?php
/**
 * File level comment
 * 
 * @package org.outlet-orm
 * @subpackage map
 * @author Alvaro Carrasco
 */

/**
 * OutletPropMap......
 * 
 * @package org.outlet-orm
 * @subpackage map
 * @author Alvaro Carrasco
 */
class OutletPropMap
{
	/**
	 * @var string
	 */
	private $prop;
	
	/**
	 * @var string
	 */
	private $column;
	
	/**
	 * @var string
	 */
	private $type;
	
	/**
	 * @var array
	 */
	private $options;

	/**
	 * @param string $prop
	 * @param string $column
	 * @param string $type
	 * @param array $options
	 */
	public function __construct($prop, $column, $type, array $options)
	{
		$this->prop = $prop;
		$this->column = $column;
		$this->type = $type;
		
		$this->options = $options;
	}

	public function getColumn()
	{
		return $this->column;
	}

	public function getType()
	{
		return $this->type;
	}

	public function getSQL()
	{
		return isset($this->options['sql']) ? $this->options['sql'] : null;
	}

	public function isAutoIncrement()
	{
		return isset($this->options['autoIncrement']) ? $this->options['autoIncrement'] : false;
	}

	public function getDefault()
	{
		return isset($this->options['default']) ? $this->options['default'] : null;
	}

	public function getDefaultExpr()
	{
		return isset($this->options['defaultExpr']) ? $this->options['defaultExpr'] : null;
	}

	public function __toString()
	{
		return "  - {$this->prop}\n";
	}
}