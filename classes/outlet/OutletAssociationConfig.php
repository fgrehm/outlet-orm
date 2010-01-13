<?php
/**
 * Abstract base class for all outlet associations.
 * 
 * @see OutletOneToManyConfig
 * @see OutletManyToOneConfig
 * @see OutletOneToOneConfig
 * @see OutletManyToManyConfig
 */
abstract class OutletAssociationConfig
{
	protected $config;
	protected $local;
	protected $pk;
	protected $foreign;
	protected $type;
	protected $key;
	protected $localUseGettersAndSetters;
	protected $foreignUseGettersAndSetters;

	/**
	 * Abstract constructor
	 * 
	 * @param OutletConfig $config configuration
	 * @param string $type Type of association: one-to-many, many-to-one, etc
	 * @param string $local Name of the entity where the association is defined
	 * @param string $foreign Name of the entity that is referenced by the association
	 * @param array $options
	 */
	public function __construct(OutletConfig $config, $local, $foreign, array $options)
	{
		$this->config = $config;
		
		$this->local = $local;
		$this->foreign = $foreign;
		$this->options = $options;
	}

	/**
	 * Retrieve the foreign getters and setters setting
	 * 
	 * @return bool whether or not to use getters and setters for the foreign table instead of properties
	 */
	public function getForeignUseGettersAndSetters()
	{
		return $this->config->getEntity($this->foreign)->useGettersAndSetters;
	}

	/**
	 * Retrieve the local getters and setters setting
	 * 
	 * @return bool whether or not to use getters and setters for the local table instead of properties
	 */
	public function getLocalUseGettersAndSetters()
	{
		return $this->config->getEntity($this->local)->useGettersAndSetters;
	}

	/**
	 * Retrieve the local entity name
	 * 
	 * @return string local entity name
	 */
	public function getLocal()
	{
		return $this->local;
	}

	/**
	 * Retrieve the type
	 * 
	 * @return string type 
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * Retrieve the optional flag
	 * 
	 * @return bool true if optional, false otherwise
	 */
	public function isOptional()
	{
		return (isset($this->options['optional']) && $this->options['optional']);
	}

	/**
	 * Retrieve the foreign entity name
	 * 
	 * @return string foreign entity name
	 */
	public function getForeign()
	{
		return $this->foreign;
	}

	/**
	 * Retrieve the getter for the foreign entity
	 * 
	 * @return string getter function name
	 */
	public function getGetter()
	{
		switch ($this->type) {
			case 'many-to-one':
			case 'one-to-one':
				return "get" . $this->getForeignName();
			default:
				return "get" . $this->getForeignPlural();
		}
	}

	/**
	 * Retrieve the setter for the foreign entity
	 * 
	 * @return string setter function name
	 */
	public function getSetter()
	{
		switch ($this->type) {
			case 'many-to-one':
			case 'one-to-one':
				return "set" . $this->getForeignName();
			default:
				return "set" . $this->getForeignPlural();
		}
	}

	/**
	 * Retrieves the name of the association
	 * 
	 * @return string name of the association
	 */
	public function getForeignName()
	{
		if (isset($this->options['name'])) {
			$name = $this->options['name'];
		} else {
			$name = $this->foreign;
		}
		return $name;
	}

	/**
	 * Retrieves the pluralized foreign entity name
	 * 
	 * if plural is defined in the configuration it will return that value
	 * otherwise it will take the entity name and append an 's'
	 * 
	 * if the foreign entity cannot be found the function throws an OutletConfigException
	 * 
	 * @return string pluralized foreign entity
	 */
	public function getForeignPlural()
	{
		// if this association has a name
		if (isset($this->options['name'])) {
			// if this association has a plural, use that
			// else use the name plus an 's' 
			if (isset($this->options['plural'])) {
				$plural = $this->options['plural'];
			} else {
				$plural = $this->options['name'] . 's';
			}
			// else check the entity definition
		} else {
			$foreignCfg = $this->config->getEntity($this->foreign);
			
			$plural = $foreignCfg->plural;
		}
		return $plural;
	}
}