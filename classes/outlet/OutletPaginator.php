<?php

class OutletPaginator {
	/**
	 * @var OutletQuery
	 */
	private $q;
	private $results_per_page;
	private $total;
	
	/**
	 * @param OutletQuery $q
	 * @param int Results per page
	 */
	public function __construct (OutletQuery $q, $results_per_page=10) {
		$this->q = $q;
		$this->results_per_page = $results_per_page;
	}
	
	/**
	 * @param int $page
	 * @return OutletPaginatorResultSet
	 */
	public function getPage ($page) {
		if (!$this->total) $this->total = $this->q->count();
		
		$offset = $page * $this->results_per_page - $this->results_per_page;
		
		$outlet = $this->q->getOutlet();
		
		$r = new OutletPaginatorResultSet(
			$this->q->offset($offset)->limit($this->results_per_page)->find(),
			$this->total,
			$this->results_per_page,
			$page
		);
		
		return $r;
	}
}

class OutletPaginatorResultSet {
	public $totalResults;
	public $totalPages;
	public $currentPage;
	public $firstPage;
	public $lastPage;
	public $previousPage;
	public $nextPage;
	public $start;
	public $end;
	public $results;
	
	public $isFirst;
	public $isLast;
	
	public function __construct ($results, $totalResults, $resultsPerPage, $currentPage) {
		$this->results = $results;
		$this->totalResults = $totalResults;
		$this->totalPages = floor($this->totalResults/$resultsPerPage)+1;
		$this->currentPage = $currentPage;
		$this->firstPage = 1;
		$this->lastPage = $this->totalPages;
		$this->previousPage = $currentPage - 1;
		$this->nextPage = $currentPage + 1;
		$this->start = $currentPage * $resultsPerPage - $resultsPerPage + 1;
		$this->end = ($this->totalResults > ($this->start+$resultsPerPage)) ? $this->start + $resultsPerPage-1 : $this->totalResults;
		
		$this->isFirst = $currentPage == 1;
		$this->isLast = $currentPage == $this->lastPage;
	}
}

