<?php
/**
 * Outlet Collection used in the place of array objects
 * @package outlet
 */
class Collection extends ArrayObject {
	/**
	 * Add an object to the collection
	 * @param object $obj object to add
	 * @todo ArrayObject::append returns void, the return here has no effect, check for removal
	 */
	public function add ($obj) {
		return $this->append($obj);	
	}
	
	/**
	 * Override the remove function to do nothing
	 * @param object $obj object to remove
	 */
	public function remove ($obj) {}
	
	/**
	 * Removes all entries from the collection by exchanging the array with a new empty array
	 */
	public function removeAll () {
		$this->exchangeArray(array());
	}
}
