<?php

class OutletPropMap {
	private $prop;
	private $column;
	private $type;

	private $options;
	
	public function __construct ($prop, $column, $type, array $options) {
		$this->prop = $prop;
		$this->column = $column;
		$this->type = $type;

		$this->options = $options;
	}
	
	public function getColumn () {
		return $this->column;
	}
	
	public function getType () {
		return $this->type;
	}
	
	public function getSQL () {
		return isset($this->options['sql']) ? $this->options['sql'] : null;
	}

	public function isAutoIncrement () {
		return isset($this->options['autoIncrement']) ? $this->options['autoIncrement'] : false;
	}

	public function getDefault () {
		return isset($this->options['default']) ? $this->options['default'] : null;
	}

	public function getDefaultExpr() {
		return isset($this->options['defaultExpr']) ? $this->options['defaultExpr'] : null;
	}
	
	public function __toString () {
		return "  - {$this->prop}\n";
	}
}

