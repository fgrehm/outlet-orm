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
		
		$r = new OutletPaginatorResultSet();
		$r->results = $this->q->offset($offset)->limit($this->results_per_page)->find();
		$r->totalResults = $this->total;
		$r->totalPages = floor($this->total/$this->results_per_page)+1;
		$r->currentPage = $page;
		$r->firstPage = 1;
		$r->lastPage = $r->totalPages;
		$r->previousPage = $page - 1;
		$r->nextPage = $page + 1;
		$r->start = $page * $this->results_per_page - $this->results_per_page + 1;
		$r->end = ($r->totalResults > ($r->start+$this->results_per_page)) ? $r->start+$this->results_per_page-1 : $r->totalResults;
		
		$r->isFirst = $page == 1;
		$r->isLast = $page == $r->lastPage;
		
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
	
	public $hasFirst;
	public $hasPrevious;
	public $hasNext;
	public $hasLast;
}

