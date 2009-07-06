<?php

class NestedSetBrowser {
	protected $cls;
	protected $left;
	protected $right;
	protected $qualifiers;
	
	public function __construct ($cls, $qualifiers = array(), $left='Left', $right='Right') {
		$this->cls = $cls;
		$this->qualifiers = $qualifiers;
		$this->left = $left;
		$this->right = $right;
	}
	
	public function getChildren ($obj) {
		$outlet = Outlet::getInstance();
		
		$cls = $this->cls;
		$left = $this->left;
		$right = $this->right;
		
		// qualifiers
		$w = '';
		foreach ($this->qualifiers as $field) {
			$w .= "{n.$field} = " . $outlet->quote($obj->$field) . " AND ";
		}
		
		$children = $outlet->from("$cls n")
			->where(
				"
					$w
					{n.$left} > ? 
					AND {n.$right} < ?
					AND (
						SELECT p.id FROM {"."$cls p} 
						WHERE {p.$left} < {n.$left} 
							AND {p.$right} > {n.$right} 
							ORDER BY {p.$left} DESC 
							LIMIT 1
					) = ? 
				",
				array($obj->Left, $obj->Right, $obj->ID)
			)
			->find();
		
		return $children;
	}
}
