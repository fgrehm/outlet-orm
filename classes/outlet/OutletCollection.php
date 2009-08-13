<?php
/**
 * @package outlet
 */
class OutletCollection extends Collection {
	/**
	 * @var OutletQuery
	 */
	private $q;
	
	private $loaded = false;
	private $removeAll = false;
	
	function __construct (OutletQuery $q) {
		$this->q = $q;
	}
	
	/**
	 * @return OutletQuery
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
		if (!$this->loaded) $this->load();
		
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
	
	private function load () {
		if (!$this->loaded) {
			$this->exchangeArray( $this->q->find() );
			$this->loaded = true;
		}
	} 
	
	function toArray () {
		if (!$this->loaded) $this->load();
		
		return $this->getArrayCopy();
	}
	
	function removeAll () {
		$this->removeAll = true;
		$this->exchangeArray( array() );
		$this->loaded = true;
	}
	
	function isRemoveAll () {
		return $this->removeAll;
	}
	
	function add ($obj) {
		$this[] = $obj;
	}
	
	/**
	 * @return int
	 */
	function count () {
		$this->load();
		return parent::count();
	}
}
