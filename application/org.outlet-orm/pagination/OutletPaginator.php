<?php
/**
 * File level comment
 * 
 * @package org.outlet-orm
 * @subpackage pagination
 * @author Alvaro Carrasco
 */

/**
 * OutletPaginator....
 * 
 * @package org.outlet-orm
 * @subpackage pagination
 * @author Alvaro Carrasco
 */
class OutletPaginator
{
	/**
	 * @var OutletQuery
	 */
	private $query;
	
	/**
	 * @var int
	 */
	private $resultsPerPage;
	
	/**
	 * @var int
	 */
	private $total;

	/**
	 * @param OutletQuery $query
	 * @param int $resultsPerPage Results per page
	 */
	public function __construct(OutletQuery $query, $resultsPerPage = 10)
	{
		$this->query = $query;
		$this->resultsPerPage = $resultsPerPage;
	}

	/**
	 * @param int $page
	 * @return OutletPaginatorResultSet
	 */
	public function getPage($page)
	{
		if (!$this->total) {
			$this->total = $this->query->count();
		}
		
		$offset = $page * $this->resultsPerPage - $this->resultsPerPage;
		
		return new OutletPaginatorResultSet(
			$this->query->offset($offset)->limit($this->resultsPerPage)->find(),
			$this->total,
			$this->resultsPerPage,
			$page
		);
	}
}