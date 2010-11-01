<?php
/**
 * File level comment
 * 
 * @package org.outlet-orm
 * @subpackage database
 * @author Alvaro Carrasco
 */

/**
 * OutletQuery.....
 * 
 * @package org.outlet-orm
 * @subpackage database
 * @author Alvaro Carrasco
 */
class OutletQuery
{
	/**
	 * @var string
	 */
	private $from;
	
	/**
	 * @var array
	 */
	private $with;
	
	/**
	 * @var array
	 */
	private $joins;
	
	/**
	 * @var string
	 */
	private $query;
	
	/**
	 * @var array
	 */
	private $params;
	
	/**
	 * @var string
	 */
	private $orderBy;
	
	/**
	 * @var int
	 */
	private $limit;
	
	/**
	 * @var int
	 */
	private $offset;
	
	/**
	 * @var string
	 */
	private $select;
	
	/**
	 * @var string
	 */
	private $groupBy;
	
	/**
	 * @var string
	 */
	private $having;

	/**
	 * Constructs a new instance of OutletQuery
	 * 
	 * @return OutletQuery instance
	 */
	public function __construct()
	{
		$this->with = array();
		$this->joins = array();
		$this->params = array();
	}

	/**
	 * Entity table to select from
	 * 
	 * @param string $from
	 * @return OutletQuery for fluid interface
	 */
	public function from($from)
	{
		$this->from = $from;
		
		return $this;
	}

	/**
	 * Where clause to filter results by
	 * 
	 * @param string $q where clause
	 * @param array $params parameters
	 * @return OutletQuery for fluid interface
	 */
	public function where($q, array $params = array())
	{
		$this->query = $q;
		$this->params = $params;
		
		return $this;
	}

	/**
	 * Declare an inner join
	 * 
	 * @param string $join entity table to join on
	 * @return OutletQuery for fluid interface
	 */
	public function innerJoin($join)
	{
		$this->joins[] = 'INNER JOIN ' . $join . "\n";
		
		return $this;
	}

	/**
	 * Declare a left join
	 * 
	 * @param string $join entity table to join on
	 * @return OutletQuery for fluid interface
	 */
	public function leftJoin($join)
	{
		$this->joins[] = 'LEFT JOIN ' . $join . "\n";
		
		return $this;
	}

	/**
	 * Include associated entity tables
	 * 
	 * @param string... variadiac entity tables to include
	 * @return OutletQuery for fluid interface
	 */
	public function with()
	{
		$args = func_get_args();
		$this->with = array_merge($this->with, $args);
		
		return $this;
	}

	/**
	 * Declare an ordering
	 * 
	 * @param string $v Order clause 
	 * @return OutletQuery for fluid interface
	 */
	public function orderBy($v)
	{
		$this->orderBy = $v;
		
		return $this;
	}

	/**
	 * Declare a limit to the result set
	 * 
	 * @param int $num Number of results to return 
	 * @return OutletQuery for fluid interface
	 */
	public function limit($num)
	{
		$this->limit = $num;
		
		return $this;
	}

	/**
	 * Declare an extra column to select
	 * 
	 * @param string $s column
	 * @return OutletQuery for fluid interface
	 */
	public function select($s)
	{
		$this->select = $s;
		
		return $this;
	}

	/**
	 * Declare an offset for the result set
	 * 
	 * @param int $num Offset to begin returning result set at 
	 * @return OutletQuery for fluid interface
	 */
	public function offset($num)
	{
		$this->offset = $num;
		
		return $this;
	}

	/**
	 * Declare a grouping
	 * 
	 * @param string $s column to group by
	 * @return OutletQuery for fluid interface
	 */
	public function groupBy($s)
	{
		$this->groupBy = $s;
		
		return $this;
	}

	/**
	 * Declare a having clause
	 * 
	 * @param string $s having condition
	 * @return OutletQuery for fluid interface
	 */
	public function having($s)
	{
		$this->having = $s;
		
		return $this;
	}

	private function addWiths(OutletSqlQuery $oq, OutletEntityMap $entMap, $from_aliased, &$with_map, $addToSelect = true)
	{
		// get the included entities
		$with = array();
		$with_aliased = array();
		
		foreach ($this->with as $with_key => $j) {
			$tmp = explode(' ', $j);
			
			$with[$with_key] = $tmp[0];
			$with_aliased[$with_key] = (count($tmp) > 1 ? $tmp[1] : $tmp[0]);
			$assoc = $entMap->getAssociation($with[$with_key]);
			
			if (!$assoc) {
				throw new OutletException('No association found with entity or alias [' . $with[$with_key] . ']');
			}
			
			$foreign = $assoc->getEntityMap();
			
			if ($addToSelect) {
				foreach ($foreign->getPropMaps() as $key => $p) {
					//$select_cols[] = "\n{" . $with_aliased[$with_key] . '.' . $key . '} as ' . $with_aliased[$with_key] . '_' . $key;
					$oq->addSelectField('{' . $with_aliased[$with_key] . '.' . $key . '} as ' . $with_aliased[$with_key] . '_' . $key);
				}
			}
			
			$aliased_join = $with_aliased[$with_key];
			//$join_q .= "LEFT JOIN {" . $foreign->getClass() . " " . $aliased_join . "} ON {" . $from_aliased . '.' . $assoc->getKey() . "} = {" . $with_aliased[$with_key] . '.' . $assoc->getRefKey() . "} \n";
			$oq->addJoin('LEFT JOIN {' . $foreign->getClass() . ' ' . $aliased_join . '} ON {' . $from_aliased . '.' . $assoc->getKey() . '} = {' . $with_aliased[$with_key] . '.' . $assoc->getRefKey() . '}');
		}
		
		if (count($with)) {
			$with_map = array_combine($with_aliased, $with);
		} else {
			$with_map = array();
		}
	}

	/**
	 * Execute the query
	 * 
	 * @return array result set
	 */
	public function find()
	{
		$oq = new OutletSqlQuery();
		
		$outlet = Outlet::getInstance();
		
		// get the 'from'
		$tmp = explode(' ', $this->from);
		
		$from = $tmp[0];
		$from_aliased = (count($tmp) > 1 ? $tmp[1] : $tmp[0]);
		
		$entMap = $outlet->getEntityMap($from);
		
		// select columns
		foreach ($entMap->getPropMaps() as $key => $p) {
			$oq->addSelectField('{' . $from_aliased . '.' . $key . '} as ' . $from_aliased . '_' . $key);
		}
		if ($this->select) {
			//$q .= ", " . $this->select;
			$oq->addSelectField($this->select);
		}
		
		// from
		$oq->setFrom('{' . $this->from . '}');
		
		// with
		$this->addWiths($oq, $entMap, $from_aliased, $with_map);
		
		// joins
		foreach ($this->joins as $join) {
			$oq->addJoin($join);
		}
		
		if ($this->query) {
			$oq->setWhere('WHERE ' . $this->query);
		}
		
		if ($this->groupBy) {
			$oq->setGroupBy('GROUP BY ' . $this->groupBy);
		}
		
		if ($this->orderBy) {
			$oq->setOrderBy('ORDER BY ' . $this->orderBy);
		}
		
		if ($this->having) {
			$oq->setHaving('HAVING ' . $this->having);
		}
		
		// TODO: Make it work on MS SQL
		//	   In SQL Server 2005 http://www.singingeels.com/Articles/Pagination_In_SQL_Server_2005.aspx
		if ($this->limit) {
			$oq->setLimit('LIMIT ' . $this->limit);
			
			if ($this->offset) {
				$oq->setOffset(' OFFSET ' . $this->offset);
			}
		}
		
		$q = $oq->toSql();
		
		$stmt = $outlet->query($q, $this->params);
		
		$res = array();
		
		// populate objects		
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$res[] = $this->populateObject($row, $entMap, $from_aliased, $from, $with_map);
		}
		
		return $res;
	}

	/**
	 * Execute a count
	 * 
	 * Count always ignores limit, offset, and order by
	 * 
	 * @return int Count
	 */
	public function count()
	{
		$oq = new OutletSqlQuery();
		
		$outlet = Outlet::getInstance();
		
		// get the 'from'
		$tmp = explode(' ', $this->from);
		
		$from = $tmp[0];
		$from_aliased = (count($tmp) > 1 ? $tmp[1] : $tmp[0]);
		
		$entMap = $outlet->getEntityMap($from);
		
		// select 
		$oq->addSelectField('COUNT(*) as total');
		
		// from
		$oq->setFrom('{' . $this->from . '}');
		
		// with
		$this->addWiths($oq, $entMap, $from_aliased, $with_map, $addToSelect = false);
		
		// joins
		foreach ($this->joins as $join) {
			$oq->addJoin($join);
		}
		
		if ($this->query) {
			$oq->setWhere('WHERE ' . $this->query);
		}
		
		if ($this->groupBy) {
			$oq->setGroupBy('GROUP BY ' . $this->groupBy);
		}
		
		if ($this->having) {
			$oq->setHaving('HAVING ' . $this->having);
		}
		
		$q = $oq->toSql();
		
		$stmt = $outlet->query($q, $this->params);
		$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		return (int) $res[0]['total'];
	}

	private function populateObject(array $row, OutletEntityMap $entMap, $from_aliased, $from, array $with_map)
	{
		$data = array();
		$outlet = Outlet::getInstance();
		// Postgres returns columns as lowercase
		// TODO: Maybe everything should be converted to lower in query creation / processing to avoid this
		$dialect = $outlet->getConnection()->getDialect();
		
		foreach ($entMap->getPropMaps() as $key => $p) {
			if ($dialect == 'pgsql') {
				$data[$p[0]] = $row[strtolower($from_aliased) . '_' . strtolower($key)];
			} else {
				$data[$p->getColumn()] = $row[$from_aliased . '_' . $key];
			}
		}
		
		$obj = $outlet->getEntityForRow($from, $data);
		
		foreach ($with_map as $alias => $with) {
			$a = $entMap->getAssociation($with);
			
			if ($a) {
				$data = array();
				$setter = 'set' . $a->getName();
				$with_entMap = $a->getEntityMap();
				
				if ($a instanceof OutletOneToManyConfig) {
					// TODO: Implement...											 
				} elseif ($a instanceof OutletManyToManyConfig) {
					// TODO: Implement...
				} else { // Many-to-one or one-to-one
					

					foreach ($with_entMap->getPropMaps() as $key => $p) {
						// Postgres returns columns as lowercase
						// TODO: Maybe everything should be converted to lower in query creation / processing to avoid this
						if ($dialect == 'pgsql') {
							$data[$p->getColumn()] = $row[strtolower($alias . '_' . $key)];
						} else {
							$data[$p->getColumn()] = $row[$alias . '_' . $key];
						}
					}
					
					$f = $with_entMap->getPkColumns();
					
					// check to see if we found any data for the related entity
					// using the pk
					$data_returned = false;
					$pk_values = array();
					
					foreach ($f as $k) {
						if (isset($data[$k])) {
							$data_returned = true;
							break;
						}
					}
					
					// only fill object if there was data returned
					if ($data_returned) {
						$obj->$setter($outlet->getEntityForRow($with_entMap->getClass(), $data));
					}
				}
			}
		}
		
		return $obj;
	}

	/**
	 * Executes query returning the first result out of the result set
	 * 
	 * @return object first result out of the result set
	 */
	public function findOne()
	{
		//TODO review code suggestion below
		/*
		 * It would improve performance to replace the code below with
		 * $res = $this->limit(1)->find();
		 * 
		 * There is no need to do an unbound search if we only want one result
		 */
		$res = $this->find();
		
		if (count($res)) {
			return $res[0];
		}
	}

	public function getOutlet()
	{
		return Outlet::getInstance();
	}
}