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
class OutletPaginatorResultSet
{
	/**
	 * @var int
	 */
	public $totalResults;
	
	/**
	 * @var int
	 */
	public $totalPages;
	
	/**
	 * @var int
	 */
	public $currentPage;

	/**
	 * @var int
	 */
	public $firstPage;
	
	/**
	 * @var int
	 */
	public $lastPage;
	
	/**
	 * @var int
	 */
	public $previousPage;
	
	/**
	 * @var int
	 */
	public $nextPage;
	
	/**
	 * @var int
	 */
	public $start;
	
	/**
	 * @var int
	 */
	public $end;
	
	/**
	 * @var array
	 */
	public $results;
	
	/**
	 * @var boolean
	 */
	public $isFirst;
	
	/**
	 * @var boolean
	 */
	public $isLast;

	/**
	 * @param array $results
	 * @param int $totalResults
	 * @param int $resultsPerPage
	 * @param int $currentPage
	 */
	public function __construct($results, $totalResults, $resultsPerPage, $currentPage)
	{
		$this->results = $results;
		$this->totalResults = $totalResults;
		$this->totalPages = floor($this->totalResults / $resultsPerPage) + 1;
		$this->currentPage = $currentPage;
		$this->firstPage = 1;
		$this->lastPage = $this->totalPages;
		$this->previousPage = $currentPage - 1;
		$this->nextPage = $currentPage + 1;
		$this->start = $currentPage * $resultsPerPage - $resultsPerPage + 1;
		$this->end = ($this->totalResults > ($this->start + $resultsPerPage)) ? $this->start + $resultsPerPage - 1 : $this->totalResults;
		
		$this->isFirst = $currentPage == 1;
		$this->isLast = $currentPage == $this->lastPage;
	}
}