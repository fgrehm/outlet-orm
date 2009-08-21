<?php
/**
 * OutletCollection is a specialized Collection containing the results of a query
 * OutletCollection is a lazy-loading class, query is not executed until values are needed
 * @package outlet
 */
class OutletCollection extends Collection {
	/**
	 * @var OutletQuery
	 */
	private $q;
	
	private $loaded = false;
	private $removeAll = false;
	
	/**
	 * Constructs a new instance of OutletCollection
	 * @param OutletQuery $q query
	 * @return OutletCollection instance
	 */
	function __construct (OutletQuery $q) {
		$this->q = $q;
	}
	
	/**
	 * Retrieves the query the OutletCollection wraps
	 * @return OutletQuery query
	 */
	function getQuery () {
		return $this->q;
	}
	
	/**
	 * Load the collection if necessary and return an Iterator
	 * 
	 * @return ArrayIterator
	 */
	function getIterator () {
		$this->load();
		return parent::getIterator();
	}
	
	/**
	 * Get the iterator without loading remote values
	 * 
	 * @return ArrayIterator
	 */
	function getLocalIterator () {
		return parent::getIterator();
	}
	
	/**
	 * Executes the query and loads the result into the collection
	 * This function is re-entrant in that it will only run the query if it hasn't been run before
	 */
	private function load () {
		if (!$this->loaded) {
			$this->exchangeArray( $this->q->find() );
			$this->loaded = true;
		}
	} 
	
	/**
	 * Retrieves the query results as a native php array
	 * @return array query results
	 */
	function toArray () {
		$this->load();
		return $this->getArrayCopy();
	}
	
	/**
	 * Removes all entries from the array
	 * @todo This function sets loaded to true, should it set it to false
	 * @todo There is a function Collection::removeAll(), it should probably replace the call to exchangeArray($array) 
	 */
	function removeAll () {
		$this->removeAll = true;
		$this->exchangeArray( array() );
		$this->loaded = true;
	}
	
	/**
	 * Retrieve the remove all flag
	 * @return bool remove all flag 
	 */
	function isRemoveAll () {
		return $this->removeAll;
	}
	
	/**
	 * Adds an object to the collection
	 * @todo There is a function Collection::add($obj), it should probably replace the explicit addition
	 * @param object $obj object to add
	 */
	function add ($obj) {
		$this[] = $obj;
	}
	
	/**
	 * Retrieve the number of objects in the collection
	 * @return int number of objects in the collection
	 */
	function count () {
		$this->load();
		return parent::count();
	}
}
