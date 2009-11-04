<?php

namespace outlet;

class UnitOfWork {
	private $insertOrders = array();
	private $updateOrders = array();
	private $deleteOrders = array();
	private $values = array();
	/**
	 *
	 * @var OutletSession
	 */
	private $session;

	public function getOriginalValues($obj) {
		return $this->values[spl_object_hash($obj)]['original'];
	}
	public function getOriginalValue($obj, $prop) {
		return $this->values[spl_object_hash($obj)]['original'][$prop];
	}

	public function getModifiedValues($obj) {
		$original = $this->getOriginalValues($obj);
		$new = $this->session->getMapperFor($obj)->getValues($obj);
		return array_diff_assoc($new, $original);
	}

	public function  __construct(Session $session) {
		$this->session = $session;
		$this->config = $session->getConfig();
		$this->repository = $session->getRepository();
		$this->identityMap = $session->getIdentityMap();
	}

	public function clear() {
		$this->values = array();
		return $this;
	}

	public function setRepository(Repository $repository) {
		$this->repository = $repository;
	}

	public function save(&$obj){
		$orderType = ($obj instanceof Proxy) ? 'update' : 'insert';
		$orders =& $this->{$orderType.'Orders'};
		if (!in_array($obj, $orders))
			$orders[] = $obj;
	}

	public function delete($obj){
		if ($obj instanceof Proxy && !in_array($obj, $this->deleteOrders)) {
			if (($key = array_search($obj, $this->updateOrders)) !== false)
				unset($this->updateOrders[$key]);
			$this->deleteOrders[] = $obj;
		} elseif (($key = array_search($obj, $this->insertOrders)) !== false){
			unset($this->insertOrders[$key]);
		}
	}

	public function detectUpdates() {
		foreach ($this->values as $values) {
			$obj = $values['object'];
			$modified = $this->getModifiedValues($obj);
			if (count($modified) > 0)
				$this->save($obj);
		}
	}

	public function commit() {
		// FIXME: Database transaction
		foreach ($this->insertOrders as &$obj) {			
			$this->repository->add($obj);
		}
		
		foreach ($this->updateOrders as $obj) {
			$this->repository->update($obj);
		}

		foreach ($this->deleteOrders as $obj){
			unset($this->values[spl_object_hash($obj)]);
			$this->repository->remove($obj);
		}
		// TODO: add a test for this
		$this->insertOrders =
		$this->updateOrders =
		$this->deleteOrders = array();
	}

	public function createEntity($class, $data) {		
		$config = $this->config->getEntity($class);
		if ($config->getDiscriminator() !== null) {
			$discriminatorName = $config->getDiscriminator()->getName();
			$discriminatorValue = isset($data[$discriminatorName]) ? $data[$discriminatorName] : null;
			if ($discriminatorValue !== null) {
				$class = $config->getSubclassConfByDiscriminator($discriminatorValue)->getClass();				
			}
		}
		$mapper = $this->session->getMapperFor($class);
		$pkProperties = $config->getPkProperties();
		$pks = array();
		foreach ($pkProperties as $prop) {
			$pks[] = $data[$prop->getName()];
		}
		if (!($entity = $this->identityMap->get($class, $pks))) {
			$class .= '_OutletProxy';
			$entity = new $class();
			$mapper->set($entity, $data);
			$this->session->attach($entity);
		}
	
		return $entity;
	}

	public function attach($obj) {
		$this->values[spl_object_hash($obj)] = array(
			'object' => $obj,
			'original' => $this->session->getMapperFor($obj)->getValues($obj)
		);
	}

	public function isAttached($obj) {
		return isset($this->values[spl_object_hash($obj)]);
	}
}
