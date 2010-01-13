<?php
/**
 * Concrete One to Many Association
 * 
 * @see OutletAssociationConfig
 */
class OutletOneToManyConfig extends OutletAssociationConfig
{
	protected $type = 'one-to-many';

	/**
	 * Constructs a new instance of OutletOneToManyConfig
	 * 
	 * @see OutletAssociationConfig::__construct($config, $local, $foreign, $options)
	 * @param OutletConfig $config configuration
	 * @param object       $local local entity
	 * @param object       $foreign foreign entity
	 * @param array        $options options 
	 * @return OutletOneToManyConfig instance
	 */
	public function __construct(OutletConfig $config, $local, $foreign, array $options)
	{
		// one-to-many requires a key
		if (!isset($options['key'])) {
			throw new OutletConfigException("Entity $local, association with $foreign: You must specify a 'key' when defining a one-to-many relationship");
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
			return current($this->config->getEntity($this->local)->getPkFields());
		}
	}
}