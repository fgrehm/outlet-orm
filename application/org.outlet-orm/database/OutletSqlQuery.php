<?php
/**
 * File level comment
 * 
 * @package org.outlet-orm
 * @subpackage database
 * @author Alvaro Carrasco
 */

/**
 * Query representation to aid in the building of sql. 
 * Meant for internal use only. Lower-level than OutletQuery.
 * 
 * @package org.outlet-orm
 * @subpackage database
 * @author Alvaro Carrasco
 */
class OutletSqlQuery
{
	private $from;
	private $select_fields = array();
	private $joins = array();
	private $where = '';
	private $groupBy = '';
	private $orderBy = '';
	private $having = '';
	private $limit = '';
	private $offset = '';

	public function setFrom($from)
	{
		$this->from = $from;
	}

	public function addSelectField($field)
	{
		$this->select_fields[] = $field;
	}

	public function addJoin($join)
	{
		$this->joins[] = $join;
	}

	public function setWhere($where)
	{
		$this->where = $where;
	}

	public function setGroupBy($groupBy)
	{
		$this->groupBy = $groupBy;
	}

	public function setOrderBy($orderBy)
	{
		$this->orderBy = $orderBy;
	}

	public function setHaving($having)
	{
		$this->having = $having;
	}

	public function setLimit($limit)
	{
		$this->limit = $limit;
	}

	public function setOffset($offset)
	{
		$this->offset = $offset;
	}

	public function toSql()
	{
		return "
			SELECT " . implode(",\n", $this->select_fields) . "
			FROM " . $this->from . " 
			" . implode("\n", $this->joins) . "
			" . $this->where . "
			" . $this->groupBy . "
			" . $this->orderBy . "
			" . $this->having . "
			" . $this->limit . "
			" . $this->offset . "
		";
	}
}