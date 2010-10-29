<?php
/**
 * File level comment
 * 
 * @package org.outlet-orm
 * @subpackage association
 * @author Alvaro Carrasco
 */

/**
 * Class for one-to-many association
 * 
 * @package org.outlet-orm
 * @subpackage association
 * @author Alvaro Carrasco
 */
class OutletOneToManyAssociation extends OutletAssociation
{
	/**
	 * @param OutletEntityMap $entity
	 * @param string $name
	 * @param string $key
	 * @param string $refKey
	 */
	public function __construct(OutletEntityMap $entity, $name, $key, $refKey)
	{
		$this->entity = $entity;
		$this->name = $name;
		$this->key = $key;
		$this->refKey = $refKey;
	}
}