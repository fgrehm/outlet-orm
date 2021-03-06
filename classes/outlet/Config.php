<?php

namespace outlet;

class Config {
	public $conf;

	private $con;

	private $entities = null;
	private $aliases = null;

	public $autoloadProxies = false;
	public $proxiesCache = false;

	function __construct (array $conf) {
		// validate config
		if (!isset($conf['connection'])) throw new ConfigException('Element [connection] not found in configuration');

		if (!isset($conf['connection']['dsn']) && !isset($conf['connection']['pdo'])) {
			throw new ConfigException('You must set either [connection][pdo] or [connection][dsn] in configuration');
		}

		if (!isset($conf['connection']['dialect'])) throw new ConfigException('Element [connection][dialect] not found in configuration');
		if (!isset($conf['classes'])) throw new ConfigException('Element [classes] missing in configuration');

		// proxies section is not required
		if (isset($conf['proxies'])) {
			if (isset($conf['proxies']['autoload']))
				$this->autoloadProxies = $conf['proxies']['autoload'];
			if ($this->autoloadProxies
					&& isset($conf['proxies']['cache'])
					&& $conf['proxies']['cache'] !== false){
				$this->proxiesCache = $conf['proxies']['cache'];
				$this->proxiesCache = rtrim($this->proxiesCache, '/');
			}
		}

		$this->conf = $conf;
	}

	function getDialect() {
		return $this->conf['connection']['dialect'];
	}

	/**
	 * @return OutletConnection
	 */
	function getConnection () {
		if (!$this->con) {
			$conn = $this->conf['connection'];

			if (isset($conn['pdo'])) {
				$pdo = $conn['pdo'];
			} else {
				$pdo = new \PDO($conn['dsn'], @$conn['username'], @$conn['password']);
				$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			}

			$this->con = new Connection($pdo, $conn['dialect']);
		}
		return $this->con;
	}

	/**
	 * @return array
	 */
	function getEntities () {
		if (is_null($this->entities)) {
			$this->entities = array();
			$this->aliases = array();
			foreach ($this->conf['classes'] as $key=>$cls) 
				$this->addEntity(new EntityConfig($this, $key, $cls));
		}
		return $this->entities;
	}
	
	/**
	 * Adds an entity config
	 * @param outlet\EntityConfig $entityConfig
	 */
	function addEntity(EntityConfig $entityConfig) {
		$alias = $entityConfig->getAlias();
		if (isset($this->aliases[$alias]))
			throw new ConfigException();

		$this->aliases[$alias] = $entityConfig;
		$this->entities[$entityConfig->getQualifiedEntityClass()] = $entityConfig;
	}

	/**
	 * @param string $cls
	 * @return outlet\EntityConfig
	 */
	function getEntity ($cls, $throwsException = true) {
		if (is_null($this->entities)) $this->getEntities();

		if (is_object($cls)) {
			if ($cls instanceof Proxy)
				$cls = substr(get_class($cls), 0, -(strlen('_OutletProxy')));
			else
				$cls = get_class($cls);
		}

		if (isset($this->aliases[$cls]))
			return $this->aliases[$cls];

		if (!isset($this->entities[$cls])) {
			if ($throwsException)
				throw new OutletException('Entity ['.$cls.'] has not been defined in the configuration');
			else
				return null;		
		}

		return $this->entities[$cls];
	}

	function useGettersAndSetters () {
		return isset($this->conf['useGettersAndSetters']) ? $this->conf['useGettersAndSetters'] : false;
	}
}

class EntityConfig {
	private $namespace;
	private $entityClass;
	private $proxyClass;
	private $alias;
	private $props;

	private $sequenceName = '';

	private $useGettersAndSetters;
	private $discriminator=null;
	private $discriminatorValue='';
	/**
	 * @var outlet\EntityConfig
	 */
	private $superConfig = null;
	private $isSubclass=false;
	private $allprops;
	private $subclasses;

	function __construct (Config $config, $entity, array $conf, EntityConfig $superConfig = null) {
		if($superConfig !== null) {
			$this->superConfig = $superConfig;
			$this->isSubclass = true;
		}
		if (!$this->isSubclass && !isset($conf['table']))
			throw new ConfigException('Mapping for entity ['.$entity.'] is missing element [table]');
		if (!isset($conf['props']))
			throw new ConfigException('Mapping for entity ['.$entity.'] is missing element [props]');

		$this->sequenceName = isset($conf['sequenceName']) ? $conf['sequenceName'] : '';
		$this->useGettersAndSetters = isset($conf['useGettersAndSetters']) ? $conf['useGettersAndSetters'] : $config->useGettersAndSetters();

		// TODO: Create a regex to simplify this
		$entity = \trim($entity, '\\');
		if (\strstr($entity, '\\') !== false) {
			$this->namespace = \substr($entity, 0, \strrpos($entity, '\\')).'\\';
			$this->entityClass = \substr($entity,\strlen($this->namespace));
		} else {
			$this->namespace = '';
			$this->entityClass = $entity;
		}

		$this->proxyClass = $this->entityClass.'_OutletProxy';
		if (!isset($conf['alias']) || ($conf['alias'] === null))
			$conf['alias'] = $this->entityClass;
		$this->alias = $conf['alias'];

		// validate that there's a pk
		$this->props = array();
		$this->allprops = array();
		$this->subclasses = array();
		$this->pks = array();
		foreach ($conf['props'] as $propName => $propConf) {
			$propConf = new PropertyConfig($propName, $propConf);
			$this->props[$propName] = $propConf;
			$this->allprops[$propName] = $propConf;
			if($superConfig!==null) {
				$superConfig->allprops[$propName] = $propConf;
			}
			if (!$this->isSubclass && $propConf->isPK()) $this->pks[$propName] = $propConf;
		}

		if (!$this->isSubclass && count($this->pks) == 0) 
			throw new ConfigException("Entity [$entity] must have at least one column defined as a primary key in the configuration");

		if ($this->isSubclass) {
			foreach ($this->superConfig->getProperties() as $prop) {
				$this->props[$prop->getName()] = $prop;
				$this->allprops[$prop->getName()] = $prop;
			}
		}
		
		if ($this->isSubclass) {
			$this->pks =  $this->superConfig->getPkProperties();
			$this->table = $this->superConfig->getTable();
			$this->discriminator = $this->superConfig->getDiscriminator();
			$this->discriminatorValue = $conf['discriminator-value'];
		} else {
			$this->table = $conf['table'];
		}
		
		if (isset($conf['subclasses'])) {
			if (!isset($conf['discriminator'])) {
				throw new ConfigException('Mapping for entity ['.$entity.'] is specifying subclasses but it is missing element [discriminator]');
			}
			if (!isset($conf['discriminator-value'])) {
				throw new ConfigException('Mapping for entity ['.$entity.'] is specifying subclasses but it is missing element [discriminator-value]');
			}

			$this->discriminator = new PropertyConfig('discriminator',$conf['discriminator']);
			$this->discriminatorValue = $conf['discriminator-value'];
			$this->allprops[$this->discriminator->getName()] = $this->discriminator;
			
			foreach ($conf['subclasses'] as $className => $classConf) {
				if (!isset($classConf['discriminator-value'])) {
					throw new ConfigException('Mapping for entity ['.$className.'] is specifying subclasses but it is missing element [discriminator-value]');
				}
				$subentity = new EntityConfig($config, $className, $classConf, $this);
				$this->subclasses[$subentity->getDiscriminatorValue()] = $subentity;
				$config->addEntity($subentity);
			}
		}
	}

	/**
	 * Gets the upper most class name
	 * @return string
	 */
	function getSuperClass() {
		if($this->isSubclass) {
			return $this->superConfig->getEntityClass();
		}
		return $this->getEntityClass();
	}

	function getAllProperties() {
		return $this->allprops;
	}
	
	function getSubclassConfByDiscriminator($discriminator) {
		if($discriminator==$this->discriminatorValue) {
			return $this;
		}
		return $this->subclasses[$discriminator];
	}
	
	function getDiscriminatorValue() {
		return $this->discriminatorValue;
	}
	
	function getDiscriminator() {
		return $this->discriminator;
	}

	function getEntityClass () {
		return $this->entityClass;
	}

	function getQualifiedEntityClass() {
		return $this->getNamespace().$this->getEntityClass();
	}

	function getNamespace () {
		return $this->namespace;
	}

	function getTable () {
		return $this->table;
	}

	function getProperties () {
		return $this->props;
	}

	function getProperty($prop, $throwsException = true) {
		if (!isset($this->allprops[$prop])) {
			if ($throwsException)
				throw new ConfigException("Property [{$this->entityClass}.{$prop}] not found in configuration");
			else
				return null;
		}
		return $this->allprops[$prop];
	}

	function getPkProperties () {
		return $this->pks;
	}

	function useGettersAndSetters () {
		return $this->useGettersAndSetters;
	}

	function getAlias() {
		return $this->alias;
	}

	function getProxyClass() {
		return $this->proxyClass;
	}

	function getQualifiedProxyClass() {
		return $this->getNamespace().$this->getProxyClass();
	}
}

class PropertyConfig {
	private $name;
	private $config;
	public function __construct($name, $config) {
		$this->name = $name;
		$this->config = $config;
	}

	public function getName() {
		return $this->name;
	}

	public function getField() {
		return $this->config[0];
	}

	public function getType() {
		return $this->config[1];
	}

	public function isPK() {
		return isset($this->config[2]['pk']) && $this->config[2]['pk'];
	}
}

class ConfigException extends OutletException {}
