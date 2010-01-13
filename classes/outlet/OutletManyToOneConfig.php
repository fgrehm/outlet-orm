<?php
/**
 * Concrete Many to One Association
 * 
 * @see OutletAssociationConfig
 */
class OutletManyToOneConfig extends OutletAssociationConfig
{
	protected $type = 'many-to-one';

	/**
	 * Constructs a new instance of OutletManyToOneConfig
	 * 
	 * @param OutletConfig $config configuration
	 * @param object       $local local entity
	 * @param object       $foreign foreign entity
	 * @param array       $options options
	 * @return OutletManyToOneConfig instance
	 */
	public function __construct(OutletConfig $config, $local, $foreign, array $options)
	{
		// many-to-one requires a key
		if (!isset($options['key'])) {
			throw new OutletConfigException("Entity $local, association with $foreign: You must specify a 'key' when defining a many-to-one relationship");
		}
		
		parent::__construct($config, $local, $foreign, $options);
	}

	/**
	 * Retrieves the local key
	 * 
	 * @return mixed local key
	 */
	public function getKey()
	{
		return $this->options['key'];
	}

	/**
	 * Retrieves the reference key
	 * 
	 * @return mixed reference key
	 */
	public function getRefKey()
	{
		if (isset($this->options['refKey'])) {
			return $this->options['refKey'];
		} else {
			return current($this->config->getEntity($this->foreign)->getPkFields());
		}
	}
}