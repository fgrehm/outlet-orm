<?php
/**
 * File level comment
 * 
 * @package org.outlet-orm
 * @subpackage core
 * @author Alvaro Carrasco
 */

/**
 * OutletCollection is a lazy-load specialized Collection containing the results of a query
 * 
 * @package org.outlet-orm
 * @subpackage core
 * @author Alvaro Carrasco
 */
class OutletCollection extends Collection
{
	/**
	 * @var OutletQuery
	 */
	private $q;
	
	/**
	 * @var bool
	 */
	private $loaded;
	
	/**
	 * @var bool
	 */
	private $removeAll;

	/**
	 * Constructs a new instance of OutletCollection
	 * 
	 * @param OutletQuery $q query
	 * @return OutletCollection instance
	 */
	public function __construct(OutletQuery $q)
	{
		$this->q = $q;
		$this->loaded = false;
		$this->removeAll = false;
	}

	/**
	 * Retrieves the query the OutletCollection wraps
	 * 
	 * @return OutletQuery query
	 */
	public function getQuery()
	{
		return $this->q;
	}

	/**
	 * Load the collection if necessary and return an Iterator
	 * 
	 * @return ArrayIterator
	 */
	public function getIterator()
	{
		$this->load();
		
		return parent::getIterator();
	}

	/**
	 * Get the iterator without loading remote values
	 * 
	 * @return ArrayIterator
	 */
	public function getLocalIterator()
	{
		return parent::getIterator();
	}

	/**
	 * Executes the query and loads the result into the collection
	 * This function is re-entrant in that it will only run the query if it hasn't been run before
	 */
	private function load()
	{
		if (!$this->loaded) {
			$this->exchangeArray($this->q->find());
			$this->loaded = true;
		}
	}

	/**
	 * Retrieves the query results as a native php array
	 * 
	 * @return array query results
	 */
	public function toArray()
	{
		$this->load();
		
		return parent::toArray();
	}

	/**
	 * Removes all entries from the array
	 */
	public function removeAll()
	{
		$this->removeAll = true;
		$this->loaded = false;
		
		parent::removeAll();
	}

	/**
	 * Retrieve the remove all flag
	 * 
	 * @return bool remove all flag 
	 */
	public function isRemoveAll()
	{
		return $this->removeAll;
	}

	/**
	 * Retrieve the number of objects in the collection
	 * 
	 * @return int number of objects in the collection
	 */
	public function count()
	{
		$this->load();
		
		return parent::count();
	}

	/**
	 * Returns the element located in the specified index
	 * 
	 * @param int $index
	 */
	public function offsetGet($index)
	{
		$this->load();
		
		return parent::offsetExists($index) ? parent::offsetGet($index) : null;
	}
}