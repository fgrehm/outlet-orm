<?php
/**
 * Contains the base exception for outlet errors
 * 
 * @package org.outlet-orm
 * @subpackage core
 * @author Luís Otávio Cobucci Oblonczyk <luis@softnex.com.br>
 */

/**
 * Exception to be used on problems related to outlet, add the nested exception funcionality (like in PHP 5.3)
 * 
 * @package org.outlet-orm
 * @subpackage core
 * @author Luís Otávio Cobucci Oblonczyk <luis@softnex.com.br>
 */
class OutletException extends Exception
{
	/**
	 * Previous exception
	 * 
	 * @var Exception
	 */
	protected $previous;
	
	/**
	 * @param string $message
	 * @param int $code
	 * @param Exception $cause
	 */
	public function __construct($message = '', $code = 0, Exception $previous = null)
	{
		$this->previous = $previous;
		
		parent::__construct($message, $code);
	}
	
	/**
	 * Returns previous Exception (the third parameter of OutletException::__construct()).
	 *  
	 * @return Exception
	 */
	public final function getPrevious()
	{
		return $this->previous;
	}
	
	/**
	 * Override of __toString to work like PHP 5.3 (with nested Exception)
	 */
	public function __toString()
	{
		$string = 'exception \'' . get_class($this) . '\' with message \'' . $this->getMessage() . '\' in ' . $this->getFile() . ':' . $this->getLine() . "\n";
		$string .= 'Stack trace:' . "\n";
		$string .= $this->getTraceAsString();
		
		if ($this->getPrevious()) {
			$string .= "\n\n";
			$string .= 'Next ' . $this->getPrevious();
		}
		
		return $string;
	}
}