<?php
/**
 * File level comment
 * 
 * @package org.outlet-orm
 * @subpackage core
 * @author Alvaro Carrasco
 */

/**
 * Outlet Collection used in the place of array objects
 * 
 * @package org.outlet-orm
 * @subpackage core
 * @author Alvaro Carrasco
 */
class Collection extends ArrayObject
{
	/**
	 * Add an object to the collection
	 * 
	 * @param object $obj object to add
	 */
	public function add($obj)
	{
		$this->append($obj);
	}
	
	/**
	 * Override the remove function to do nothing
	 * 
	 * @param object $obj object to remove
	 */
	public function remove($obj)
	{
	}
	
	/**
	 * Removes all entries from the collection by exchanging the array with a new empty array
	 */
	public function removeAll()
	{
		$this->exchangeArray(array());
	}
	
	/**
	 * Returns if the collection is empty
	 * 
	 * @return boolean
	 */
	public function isEmpty()
	{
		return $this->count() < 1;
	}
	
	/**
	 * Returns the first element of the collection
	 * 
	 * @return mixeds
	 */
	public function first()
	{
		if ($this->isEmpty()) {
			throw new Exception('Collection is empty.');
		}
		return $this->offsetGet(0);
	}
	
	/**
	 * Returns the las element of the collection
	 * 
	 * @return mixed
	 */
	public function last()
	{
		if ($this->isEmpty()) {
			throw new Exception('Collection is empty.');
		}
		
		return $this->offsetGet($this->count() - 1);
	}
	
	/**
	 * Returns an array copy of the collection
	 * 
	 * @return array
	 */
	public function toArray()
	{
		return $this->getArrayCopy();
	}
}