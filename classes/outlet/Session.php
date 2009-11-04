<?php

namespace outlet;

class Session {
	private $identityMap;
	private $config;
	private $mappersCache = array();
	private $uow = null;
	private $autoFlush = true;
        private $repository = null;
        private $queryParser = null;

	public function __construct(Config $config) {
		$this->config = $config;
		if ($config->autoloadProxies)
			$this->autoloader = new ProxyAutoloader($config);
	}

	public function setAutoFlush($autoFlush) {
		$this->autoFlush = $autoFlush;
		return $this;
	}

	public function getIdentityMap() {
		if ($this->identityMap == null) {
			$this->identityMap = new IdentityMap($this);
		}
		return $this->identityMap;
	}

	public function clear() {
		$this->identityMap->clear();
		$this->getUnitOfWork()->clear();
		return $this;
	}

	public function getMapperFor($class) {
		$config = $this->config->getEntity($class);
		if (is_object($class)) $class = $config->getClass();
		if (isset($this->mappersCache[$class])) return $this->mappersCache[$class];

		if ($config->useGettersAndSetters())
			$mapper = new GettersAndSettersMapper($config);
		else
			$mapper = new PropertiesMapper($config);
		$this->mappersCache[$class] = $mapper;
		return $mapper;
	}

	public function getUnitOfWork() {
		if ($this->uow == null) {
			$this->uow = new UnitOfWork($this);
		}
		return $this->uow;
	}

	public function getConfig() {
		return $this->config;
	}

	public function getConnection() {
		return $this->getConfig()->getConnection();
	}

	public function getHydrator() {
		return new Hydrator($this);
	}

	public function getRepository() {
		if ($this->repository == null) 
			$this->repository = Repository::getRepository($this);
		return $this->repository;
	}

	public function getQueryParser() {
		if ($this->queryParser == null)
			$this->queryParser = new QueryParser($this->config);
		return $this->queryParser;
	}

	public function from($class) {
		return new Query($class, $this);
	}

	public function attach($obj) {
		$this->getUnitOfWork()->attach($obj);
		$this->identityMap->register($obj);
		return $this;
	}

	public function isAttached($obj) {
		return $this->getUnitOfWork()->isAttached($obj);
	}

	public function load($class, $pk) {
		// Check identity map first
		if ($entity = $this->getIdentityMap()->get($class, $pk))
			return $entity;
		else
			return $this->getRepository()->get($class, $pk);
	}

	public function delete($obj){
		$this->identityMap->remove($obj);
		$this->getUnitOfWork()->delete($obj);
		// TODO: need to test this
		if ($this->autoFlush) $this->flush(false);
		return $this;
	}

	public function save(&$obj) {
		$this->getUnitOfWork()->save($obj);
		// TODO: need to test this
		if ($this->autoFlush) $this->flush(false);
		return $this;
	}

	public function flush($detectUpdates = true) {
		if ($detectUpdates)
			$this->getUnitOfWork()->detectUpdates();
		$this->getUnitOfWork()->commit();
		return $this;
	}
}