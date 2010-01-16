<?php

abstract class OutletAssociation {
	protected $entity;
	protected $name;
	protected $type;
	protected $key;
	protected $refKey;
	
	public function getName () {
		return $this->name;
	}
	
	public function getType () {
		return $this->type;
	}

	public function getKey () {
		return $this->key;
	}
	
	public function getRefKey () {
		return $this->refKey;
	}
	
	/**
	 * 
	 * @return OutletEntityMap
	 */
	public function getEntityMap () {
		return $this->entity;
	}
	
	public function __toString () {
		$s = "  > {$this->name} ({$this->type}, class={$this->entity->getClass()})\n";
		return $s;
	}
}

class OutletManyToOneAssociation extends OutletAssociation {
	protected $type = 'many-to-one';
	protected $optional;
	
	public function __construct (OutletEntityMap $entity, $name, $key, $refKey, $optional) {
		$this->entity = $entity;
		$this->name = $name;
		$this->key = $key;
		$this->refKey = $refKey;
		$this->optional = $optional;
	}
	
	public function isOptional () {
		return $this->optional;
	}
}

class OutletOneToOneAssociation extends OutletManyToOneAssociation {
	protected $type = 'one-to-one';
}

class OutletOneToManyAssociation extends OutletAssociation {
	protected $type = 'one-to-many';
	
	public function __construct (OutletEntityMap $entity, $name, $key, $refKey) {
		$this->entity = $entity;
		$this->name = $name;
		$this->key = $key;
		$this->refKey = $refKey;
	}
}

class OutletManyToManyAssociation extends OutletAssociation {
	protected $type = 'many-to-many';
	protected $linkingTable;
	protected $tableKeyLocal;
	protected $tableKeyForeign;
	
	public function __construct (OutletEntityMap $entity, $name, $linkingTable, $key, $refKey, $tableKeyLocal, $tableKeyForeign) {
		$this->entity = $entity;
		$this->name = $name;
		$this->linkingTable = $linkingTable;
		$this->key = $key;
		$this->refKey = $refKey;
		$this->tableKeyLocal = $tableKeyLocal;
		$this->tableKeyForeign = $tableKeyForeign;
	}
	
	public function getLinkingTable () {
		return $this->linkingTable;
	}
	
	public function getTableKeyLocal () {
		return $this->tableKeyLocal;
	}
	
	public function getTableKeyForeign () {
		return $this->tableKeyForeign;
	}
}

