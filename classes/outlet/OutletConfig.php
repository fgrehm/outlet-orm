<?php
/**
 * Configuration used to perform data mapping
 * 
 * @package outlet
 */
class OutletConfig
{
	/**
	 * @var array
	 */
	public $conf;
	
	/**
	 * @var OutletConnection
	 */
	private $con;
	
	/**
	 * @var array
	 */
	private $entities;
	
	/**
	 * @var array
	 */
	private $classes;
	
	/**
	 * @var bool
	 */
	public $useGettersAndSetters;

	/**
	 * Constructs a new instance of OutletConfig
	 * 
	 * @param object $conf configuration
	 * @return OutletConfig instance
	 */
	public function __construct(array $conf)
	{
		$this->entities = array();
		$this->useGettersAndSetters = false;
		
		// validate config
		if (!isset($conf['connection'])) {
			throw new OutletConfigException('Element [connection] not found in configuration');
		}
		
		if (!isset($conf['connection']['dsn']) && !isset($conf['connection']['pdo'])) {
			throw new OutletConfigException('You must set either [connection][pdo] or [connection][dsn] in configuration');
		}
		
		if (!isset($conf['connection']['dialect'])) {
			throw new OutletConfigException('Element [connection][dialect] not found in configuration');
		}
		
		if (!isset($conf['classes'])) {
			throw new OutletConfigException('Element [classes] missing in configuration');
		}
		
		$this->classes = array_keys($conf['classes']);
		
		$conn = $conf['connection'];
		
		if (isset($conn['pdo'])) {
			$pdo = $conn['pdo'];
		} else {
			$pdo = new PDO($conn['dsn'], @$conn['username'], @$conn['password']);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		
		$this->con = new OutletConnection($pdo, $conn['dialect']);
		
		if (isset($conf['useGettersAndSetters'])) {
			$this->useGettersAndSetters = $this->conf['useGettersAndSetters'];
		}
			
		// create the entity configs
		foreach ($conf['classes'] as $key => $cls) {
			$this->entities[$key] = new OutletEntityConfig($this, $key, $cls);
		}
	}

	/**
	 * Retrieve the singleton connection as configured in the configuration
	 * 
	 * @return OutletConnection
	 */
	public function getConnection()
	{
		return $this->con;
	}

	/**
	 * Retrieves the singleton entities array as configured in the configuration
	 * 
	 * @return array entities array
	 */
	public function getEntities()
	{
		return $this->entities;
	}

	/**
	 * @param object $obj
	 * @return string
	 */
	public function getEntityClass($obj)
	{
		foreach ($this->classes as $cls) {
			if ($obj instanceof $cls) {
				return $cls;
			}
		}
		
		throw new OutletException('Object [' . get_class($obj) . '] not configured');
	}

	/**
	 * Retrieve the specified entity
	 * 
	 * @param string $cls entity class to retrieve
	 * @return OutletEntityConfig
	 */
	public function getEntity($cls)
	{
		if (!isset($this->entities[$cls])) {
			throw new OutletException('Entity [' . $cls . '] has not been defined in the configuration');
		}
		
		return $this->entities[$cls];
	}

	public function getEntityForObject($obj)
	{
		return $this->getEntity($this->getEntityClass($obj));
	}

	/**
	 * Retrieves the getters and setters setting from the configuration
	 * 
	 * @return bool true if outlet should use getters and setters, false otherwise
	 */
	public function useGettersAndSetters()
	{
		return isset($this->conf['useGettersAndSetters']) ? $this->conf['useGettersAndSetters'] : false;
	}
}