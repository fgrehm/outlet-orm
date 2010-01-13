<?php
class OutletXmlConfig
{
	/**
	 * @var OutletXmlConfig
	 */
	private static $instance;
	
	/**
	 * @var SimpleXmlElement
	 */
	private $xmlObj;
	
	/**
	 * @var array
	 */
	private $configArr;
	
	/**
	 * @var bool
	 */
	private $validate;
	
	/**
	 * @return OutletXmlConfig
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance)) {
			self::setInstance(new OutletXmlConfig());
		}
		
		return self::$instance;
	}
	
	/**
	 * @param OutletXmlConfig $instance
	 */
	public static function setInstance(OutletXmlConfig $instance = null)
	{
		self::$instance = $instance;
	}
	
	/**
	 * Constructor 
	 */
	public function __construct()
	{
		libxml_use_internal_errors(true);
		$this->validate = true;
	}
	
	/**
	 * @param bool $validate
	 */
	public function setValidate($validate)
	{
		$this->validate = $validate;
	}
	
	/**
	 * @param SimpleXMLElement $xmlObj
	 */
	public function setXmlObj(SimpleXMLElement $xmlObj)
	{
		$this->xmlObj = $xmlObj;
	}
	
	/**
	 * @return array
	 */
	public function parseFromFile($file)
	{
		try {
			$this->xmlObj = new SimpleXMLElement($file, null, true);
			$this->getXmlErrors();
		} catch (Exception $e) {
			throw new OutletXmlException('Xml parse error.', $e);
		}
		
		return $this->parse();
	}
	
	/**
	 * @return array
	 */
	public function parseFromString($xmlString)
	{
		try {
			$this->xmlObj = new SimpleXMLElement($xmlString);
			$this->getXmlErrors();
		} catch (Exception $e) {
			throw new OutletXmlException('Xml parse error.', $e);
		}
		
		return $this->parse();
	}
	
	/**
	 * @return array
	 */
	public function parse()
	{
		if (is_null($this->configArr)) {
			$this->validate();
			
			$data = array();
			
			$data['connection'] = $this->parseConnection();
			$data['classes'] = $this->parseClasses();
			
			$this->configArr = $data;
		}
		
		return $this->configArr;
	}
	
	/**
	 * @return string
	 */
	protected function getXsdPath()
	{
		return dirname(__FILE__) . '/outlet-config.xsd';
	}
	
	/**
	 * Validate the XML source 
	 */
	public function validate()
	{
		$this->verifyInstance();
		
		if ($this->validate) {
			$dom = new DOMDocument();
			$dom->loadXML($this->xmlObj->saveXML());
			
			if (!$dom->schemaValidate($this->getXsdPath())) {
				throw new OutletXmlException('This XML is not compatible with the Outlet\'s standard config', $this->getXmlErrors());
			}
		}
	}
	
	/**
	 * Verify if the instance is right
	 */
	protected function verifyInstance()
	{
		if (!$this->xmlObj instanceof SimpleXMLElement) {
			throw new OutletXmlException('The $this->xmlObj attribute must be an instance of SimpleXMLElement');
		}
	}
	
	/**
	 * Get Xml errors
	 * 
	 *  @throws Exception
	 */
	protected function getXmlErrors()
	{
		$errors = '';
		
		foreach(libxml_get_errors() as $error) {
	        $errors .= $error->message . '; ';
	    }
	    
	    libxml_clear_errors();
	    
	    if (strlen($errors) > 0) {
	    	throw new Exception($errors);
	    }
	}
	
	/**
	 * @return array
	 */
	protected function parseConnection()
	{
		$data = array();
		
		$data['dsn'] = strval($this->xmlObj->connection->dsn);
		$data['dialect'] = strval($this->xmlObj->connection->dialect);
		
		if ($this->xmlObj->connection->username) {
			$data['username'] = strval($this->xmlObj->connection->username);
		}
		
		if ($this->xmlObj->connection->password) {
			$data['password'] = strval($this->xmlObj->connection->password);
		}
		
		if ($data['dialect'] != 'sqlite' && !array_key_exists('username', $data)) {
			throw new OutletXmlException('You must provide at least the connection\'s username');
		}
		
		return $data;
	}
	
	/**
	 * @return array
	 */
	protected function parseClasses()
	{
		$data = array();
		
		foreach ($this->xmlObj->classes->class as $class) {
			$data[strval($class->attributes()->name)] = $this->parseClass($class);
		}
		
		return $data;
	}
	
	/**
	 * @param SimpleXMLElement $class
	 * @return array
	 */
	protected function parseClass(SimpleXMLElement $class)
	{
		$data = array();
		$data['table'] = strval($class->attributes()->table);
		$data['props'] = array();
		
		if ($class->attributes()->useGettersAndSetters) {
			$data['useGettersAndSetters'] = $class->attributes()->useGettersAndSetters == 'true';
		}
		
		if ($class->attributes()->plural) {
			$data['plural'] = strval($class->attributes()->plural);
		}
		
		if ($class->attributes()->sequenceName) {
			$data['sequenceName'] = strval($class->attributes()->sequenceName);
		}
		
		foreach ($class->property as $property) {
			$data['props'][strval($property->attributes()->name)] = $this->parseProperty($property);
		}
		
		if ($class->association) {
			$data['associations'] = array();
			
			foreach ($class->association as $association) {
				$data['associations'][] = $this->parseAssociation($association);
			}
		}
		
		return $data;
	}
	
	/**
	 * @param SimpleXMLElement $property
	 * @return array
	 */
	protected function parseProperty(SimpleXMLElement $property)
	{
		$data = array();
		$extraData = array();
		
		$data[] = strval($property->attributes()->column);
		$data[] = strval($property->attributes()->type);
		
		foreach ($property->attributes() as $attributeName => $value) {
			if (!in_array($attributeName, array('column', 'type', 'name'))) {
				if (strval($value) == 'false' || strval($value) == 'true') {
					$extraData[$attributeName] = strval($value) == 'true';
				} else {
					$extraData[$attributeName] = strval($value);
				}
			}
		}
		
		if (count($extraData) > 0) {
			$data[] = $extraData;
		}
		
		return $data;
	}
	
	/**
	 * @param SimpleXMLElement $association
	 * @return array
	 */
	protected function parseAssociation(SimpleXMLElement $association)
	{
		$data = array();
		$extraData = array();
		$type = strval($association->attributes()->type);
		
		$data[] = $type;
		$data[] = strval($association->attributes()->classReference);
		
		foreach ($association->attributes() as $attributeName => $value) {
			if (!in_array($attributeName, array('classReference', 'type'))) {
				if (strval($value) == 'false' || strval($value) == 'true') {
					$extraData[$attributeName] = strval($value) == 'true';
				} else {
					$extraData[$attributeName] = strval($value);
				}
			}
		}
		
		if ($type == 'many-to-many') {
			if (array_key_exists('key', $extraData) || array_key_exists('name', $extraData) || !array_key_exists('table', $extraData) || !array_key_exists('tableKeyLocal', $extraData) || !array_key_exists('tableKeyForeign', $extraData)) {
				throw new OutletXmlException('The ' . $type . ' association must have only "table", "tableKeyLocal", "tableKeyForeign" or "plural" attributes');
			}
		} else {
			if (array_key_exists('table', $extraData) || array_key_exists('tableKeyLocal', $extraData) || array_key_exists('tableKeyForeign', $extraData)) {
				throw new OutletXmlException('The "table", "tableKeyLocal" or "tableKeyForeign" attributes are prohibited in the ' . $type . ' association');
			}
			
			if (!array_key_exists('key', $extraData)) {
				throw new OutletXmlException('You must define the association\'s key');
			}
			
			if ($type != 'one-to-many' && array_key_exists('plural', $extraData)) {
				throw new OutletXmlException('The "plural" attribute is prohibited in the ' . $type . ' association');
			} elseif ($type == 'one-to-many' && array_key_exists('optional', $extraData)) {
				throw new OutletXmlException('The "optional" attribute is prohibited in the ' . $type . ' association');
			}
		}
		
		if (count($extraData) > 0) {
			$data[] = $extraData;
		}
		
		return $data;
	}
}