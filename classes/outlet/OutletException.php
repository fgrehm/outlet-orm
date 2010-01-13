<?php
class OutletException extends Exception
{
	/**
	 * @param string $message
	 * @param Exception $cause
	 * @param int $code
	 */
	public function __construct($message = '', Exception $cause = null, $code = null)
	{
		if (!is_null($cause)) {
			$message .= ' Causa: ' . $cause;
		}
		
		parent::__construct($message, $code);
	}
}