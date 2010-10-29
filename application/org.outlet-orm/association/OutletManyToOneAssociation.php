<?php
/**
 * File level comment
 * 
 * @package org.outlet-orm
 * @subpackage association
 * @author Alvaro Carrasco
 */

/**
 * Class for many-to-one association
 * 
 * @package org.outlet-orm
 * @subpackage association
 * @author Alvaro Carrasco
 */
class OutletManyToOneAssociation extends OutletAssociation
{
	/**
	 * @var boolean
	 */
	protected $optional;

	/**
	 * @param OutletEntityMap $entity
	 * @param string $name
	 * @param string $key
	 * @param string $refKey
	 * @param string $optional
	 */
	public function __construct(OutletEntityMap $entity, $name, $key, $refKey, $optional)
	{
		$this->entity = $entity;
		$this->name = $name;
		$this->key = $key;
		$this->refKey = $refKey;
		$this->optional = $optional;
	}

	/**
	 * @return boolean
	 */
	public function isOptional()
	{
		return $this->optional;
	}
}