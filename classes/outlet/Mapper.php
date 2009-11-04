<?php

namespace outlet;

abstract class Mapper {	
	/**
	 *
	 * @var OutletEntityConfig
	 */
	protected $entityConfig = null;

	protected function toPhpValue($value, $type) {
		if (is_null($value)) return NULL;

		switch ($type) {
			case 'date':
			case 'datetime':
				if ($value instanceof \DateTime) return $value;
				return new \DateTime($value);

			case 'int': return (int) $value;

			case 'float': return (float) $value;

			// Strings
			default: return $value;
		}
	}

	protected function getPropertiesNames() {
		return array_keys($this->entityConfig->getProperties());
	}

	public function __construct(EntityConfig $config) {
		$this->entityConfig = $config;
	}

	public function set($entity, $property, $value = null) {
		if (is_array($property)) {
			foreach ($this->getPropertiesNames() as $propName) {
				if (!isset($property[$propName])) continue;
				$this->set($entity, $propName, $property[$propName]);
			}
		} else {
			$value = $this->toPhpValue($value, $this->entityConfig->getProperty($property)->getType());
			$this->_set($entity, $property, $value);
		}
		return $this;
	}
	public function getValues($entity) {
		$props = $this->entityConfig->getProperties();
		$values = array();
		foreach($props as $prop) {
			$values[$prop->getName()] = $this->get($entity, $prop->getName());
		}
		return $values;
	}
	public function getDirtyValues($entity, $originalValues) {
		$values = array();
		foreach ($this->getValues($entity) as $propName => $value) {
			if ($value != $originalValues[$propName])
				$values[$propName] = $value;
		}
		return $values;
	}
	public function get($entity, $property) {
		return $this->_get($entity, $property);
	}

	public function getPKs($entity) {
		$props = $this->entityConfig->getPkProperties();
		$pks = array();
		foreach($props as $prop) {
			$pks[$prop->getName()] = $this->get($entity, $prop->getName());
		}
		return $pks;
	}

	public function setPKs($entity, $pk) {
		$props = $this->entityConfig->getPkProperties();
		foreach ($props as $prop) {
			$this->set($entity, $prop->getName(), $pk[$prop->getName()]);
		}
		return $this;
	}

	protected abstract function _set($entity, $property, $value);
	protected abstract function _get($entity, $property);
}

class PropertiesMapper extends Mapper {
	protected function _set($entity, $property, $value) {
		$entity->$property = $value;
	}
	protected function _get($entity, $property) {
		return $entity->$property;
	}
}

class GettersAndSettersMapper extends Mapper {
	protected function _set($entity, $property, $value) {
		$entity->{"set$property"}($value);
	}
	protected function _get($entity, $property) {
		return $entity->{"get$property"}();
	}
}