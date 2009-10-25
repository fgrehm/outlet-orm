<?php
/**
 * @package outlet
 */
class NestedSetBrowser {
	/**
	 * @var Outlet
	 */
	protected $outlet;
	
	protected $cls;
	protected $left;
	protected $right;
	protected $qualifiers;
	
	/**
	 * @param Outlet $outlet Instance of outlet
	 * @param string $cls Entity class in which the hierarchy is stored
	 * @param array $qualifiers Properties of the entity that determine which tree to look for children
	 * @param string $left Name of class property that represents the left value
	 * @param string $right Name of class property that represents the right value
	 */
	public function __construct (Outlet $outlet, $cls, $qualifiers = array(), $left='Left', $right='Right') {
		$this->outlet = $outlet;
		$this->cls = $cls;
		$this->qualifiers = $qualifiers;
		$this->left = $left;
		$this->right = $right;
	}
	
	public function appendChild ($parent, $node) {
		// begin transaction
		$outlet = $this->outlet;
		$con = $outlet->getConnection();
		$con->beginTransaction();
		
		$c = '{'.$this->cls.'}';
		$l = '{'.$this->cls.'.'.$this->left.'}';
		$r = '{'.$this->cls.'.'.$this->right.'}';
		
		// qualifiers
		$w = '';
		foreach ($this->qualifiers as $field) {
			$w .= "{$field} = " . $outlet->quote($node->$field) . " AND ";
		}
		
		// make space
		$stmt = $outlet->prepare("
			UPDATE $c
			SET $l = $l+2
			WHERE $w $l > ?
		");
		
		$stmt->execute(array(
			$parent->{$this->right}
		));
		
		$stmt = $outlet->prepare("
			UPDATE $c
			SET $r = $r+2
			WHERE $w $r >= ?
		");
		$stmt->execute(array(
			$parent->{$this->right}
		));
		
		// insert node
		$parent->{$this->right} += 2;
		$node->{$this->left} = $parent->{$this->right}-2;
		$node->{$this->right} = $parent->{$this->right}-1;
		
		$outlet->save($node);
		
		$con->commit();
		
		return $node;
	}
	
	public function getChildren ($obj) {
		$outlet = $this->outlet;
		
		$cls = $this->cls;
		$left = $this->left;
		$right = $this->right;
		
		// qualifiers
		$w = '';
		foreach ($this->qualifiers as $field) {
			$w .= "{n.$field} = " . $outlet->quote($obj->$field) . " AND ";
		}
		
		$children = $outlet->from("$cls n")
			->leftJoin("{"."$cls parent} ON ({parent.$left} < {n.$left} AND {parent.$right} > {n.$right})")
			->where(
				"
					$w
					{n.$left} > ?
					AND {n.$right} < ?
					AND {parent.$left} >= ?
					
				",
				array($obj->Left, $obj->Right, $obj->Left)
			)
			->select("COUNT({parent.$left})")
			->groupBy("{n.$left}, {n.$right}")
			->having("COUNT({parent.$left}) = 1")
			->find();
		
		return $children;
	}
}
