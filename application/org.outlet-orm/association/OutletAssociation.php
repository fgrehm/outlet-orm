<?php
/**
 * File level comment
 * 
 * @package org.outlet-orm
 * @subpackage association
 * @author Alvaro Carrasco
 */

/**
 * Parent class for associations
 * 
 * @package org.outlet-orm
 * @subpackage association
 * @author Alvaro Carrasco
 */
abstract class OutletAssociation
{
	/**
	 * @var OutletEntityMap
	 */
	protected $entity;
	
	/**
	 * @var string
	 */
	protected $name;
	
	/**
	 * @var string
	 */
	protected $key;
	
	/**
	 * @var string
	 */
	protected $refKey;

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getType()
	{
		if ($this instanceof OutletManyToOneAssociation) {
			return 'many-to-one';
		} elseif ($this instanceof OutletManyToManyAssociation) {
			return 'many-to-many';
		} elseif ($this instanceof OutletOneToOneAssociation) {
			return 'one-to-one';
		} elseif ($this instanceof OutletOneToManyAssociation) {
			return 'one-to-many';
		}
	}

	/**
	 * @return string
	 */
	public function getKey()
	{
		return $this->key;
	}

	/**
	 * @return string
	 */
	public function getRefKey()
	{
		return $this->refKey;
	}

	/**
	 * @return OutletEntityMap
	 */
	public function getEntityMap()
	{
		return $this->entity;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		$s = '  > ' . $this->getName() . ' (' . $this->getType() . ', class=' . $this->getEntityMap()->getClass() . ')' . "\n";

		return $s;
	}
}