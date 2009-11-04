<?php

namespace outlet;

class Query {
	public $from;
	private $with = array();
	private $joins = array();
	public $where;
	public $params = array();
	private $orderby;
	private $limit;
	private $offset;
	private $select;
	private $groupBy;
	private $having;
	function  __construct($from = null, $session = null) {
		$this->session = $session;
		$this->from = $from;
	}


	/**
	 * @param string $from
	 * @return OutletQuery
	 */
	function from ($from) {
		$this->from = $from;

		return $this;
	}

	/**
	 * @param string $q
	 * @param array $params
	 * @return OutletQuery
	 */
	function where ($q, $params = array()) {
		$this->where = $q;
		$params = is_array($params) ? $params : array($params);
		$this->params = $params;

		return $this;
	}

	/**
	 * @param string $join
	 * @return OutletQuery
	 */
	function innerJoin ($join) {
		$this->joins[] = 'INNER JOIN ' . $join . "\n";

		return $this;
	}

	/**
	 * @param string $join
	 * @return OutletQuery
	 */
	function leftJoin ($join) {
		$this->joins[] = 'LEFT JOIN ' . $join . "\n";

		return $this;
	}

	/**
	 * @return OutletQuery
	 */
	function with () {
		$this->with = func_get_args();

		return $this;
	}

	/**
	 * @param string $v Order clause
	 * @return OutletQuery
	 */
	function orderBy ($v) {
		$this->orderby = $v;

		return $this;
	}

	/**
	 * @param $num
	 * @return OutletQuery
	 */
	function limit ($num) {
		$this->limit = $num;

		return $this;
	}

	/**
	 * @param string $s
	 * @return OutletQuery
	 */
	function select ($s) {
		$this->select = $s;

		return $this;
	}

	/**
	 * @param $num
	 * @return OutletQuery
	 */
	function offset ($num) {
		$this->offset = $num;

		return $this;
	}

	/**
	 * @param $s
	 * @return OutletQuery
	 */
	function groupBy($s) {
		$this->groupBy = $s;

		return $this;
	}

	function having($s) {
		$this->having = $s;

		return $this;
	}

	/**
	 * @return array
	 */
	function find () {
		return $this->session->getRepository()->query($this);
	}

	public function findOne () {
		$res = $this->find();

		if (count($res)) return $res[0];
	}
}
