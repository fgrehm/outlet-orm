<?php
/**
 * Concrete Many to Many Association
 * 
 * @see OutletAssociationConfig
 */
class OutletManyToManyConfig extends OutletAssociationConfig
{
	protected $type = 'many-to-many';
	protected $table;
	protected $tableKeyLocal;
	protected $tableKeyForeign;

	/**
	 * Constructs a new instance of OutletManyToManyConfig
	 * 
	 * @param OutletConfig $config configuration
	 * @param object       $local local entity
	 * @param object       $foreign foreign entity
	 * @param array        $options options
	 * @return OutletManyToManyConfig instance
	 */
	public function __construct(OutletConfig $config, $local, $foreign, array $options)
	{
		if (!isset($options['table']))
			throw new OutletConfigException("Entity $local, association with $foreign: You must specify a table when defining a many-to-many relationship");
		
		$this->table = $options['table'];
		$this->tableKeyLocal = $options['tableKeyLocal'];
		$this->tableKeyForeign = $options['tableKeyForeign'];
		
		parent::__construct($config, $local, $foreign, $options);
	}

	/**
	 * Retrieves the local table key
	 * 
	 * @return mixed local table key
	 */
	public function getTableKeyLocal()
	{
		return $this->tableKeyLocal;
	}

	/**
	 * Retrieves the foreign table key
	 * 
	 * @return mixed foreign table key
	 */
	public function getTableKeyForeign()
	{
		return $this->tableKeyForeign;
	}

	/**
	 * Retrieves the linking table
	 * 
	 * @return string linking table
	 */
	public function getLinkingTable()
	{
		return $this->table;
	}

	/**
	 * Retrieves the local key
	 * 
	 * @return mixed local key
	 */
	public function getKey()
	{
		if (isset($this->options['key'])) {
			return $this->options['key'];
		} else {
			return current($this->config->getEntity($this->foreign)->getPkFields());
		}
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