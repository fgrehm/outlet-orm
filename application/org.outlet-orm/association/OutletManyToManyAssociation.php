<?php
/**
 * File level comment
 * 
 * @package org.outlet-orm
 * @subpackage association
 * @author Alvaro Carrasco
 */

/**
 * Class for many-to-many association
 * 
 * @package org.outlet-orm
 * @subpackage association
 * @author Alvaro Carrasco
 */
class OutletManyToManyAssociation extends OutletAssociation
{
	/**
	 * @var string
	 */
	protected $linkingTable;
	
	/**
	 * @var string
	 */
	protected $tableKeyLocal;
	
	/**
	 * @var string
	 */
	protected $tableKeyForeign;

	/**
	 * @param OutletEntityMap $entity
	 * @param string $name
	 * @param string $linkingTable
	 * @param string $key
	 * @param string $refKey
	 * @param string $tableKeyLocal
	 * @param string $tableKeyForeign
	 */
	public function __construct(OutletEntityMap $entity, $name, $linkingTable, $key, $refKey, $tableKeyLocal, $tableKeyForeign)
	{
		$this->entity = $entity;
		$this->name = $name;
		$this->linkingTable = $linkingTable;
		$this->key = $key;
		$this->refKey = $refKey;
		$this->tableKeyLocal = $tableKeyLocal;
		$this->tableKeyForeign = $tableKeyForeign;
	}

	/**
	 * @return string
	 */
	public function getLinkingTable()
	{
		return $this->linkingTable;
	}

	/**
	 * @return string
	 */
	public function getTableKeyLocal()
	{
		return $this->tableKeyLocal;
	}

	/**
	 * @return string
	 */
	public function getTableKeyForeign()
	{
		return $this->tableKeyForeign;
	}
}