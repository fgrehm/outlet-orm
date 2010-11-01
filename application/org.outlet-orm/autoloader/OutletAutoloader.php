<?php
/**
 * Contains the necessary class to use spl_autoload_register
 * 
 * @package org.outlet-orm
 * @subpackage autoloader
 * @author Luís Otávio Cobucci Oblonczyk <luis@softnex.com.br>
 */

/**
 * Class for autoloading with SPL
 * 
 * @package org.outlet-orm
 * @subpackage autoloader
 * @author Luís Otávio Cobucci Oblonczyk <luis@softnex.com.br>
 */
class OutletAutoloader
{
	/**
	 * Search and include the class
	 * 
	 * @param string $class
	 * @uses Softnex_AutoLoader::getDirectories()
	 * @uses Softnex_AutoLoader::getFileName()
	 * @uses Softnex_AutoLoader::fileExists()
	 * @uses Softnex_AutoLoader::includeClass()
	 */
	public function autoLoad($class)
	{
		foreach ($this->getDirectories() as $dir) {
			$file = $this->getFileName($dir, $class);
			
			if ($this->fileExists($file)) {
				$this->includeClass($file);
				break;
			}
		}
	}
	
	/**
	 * Include the requested file
	 * 
	 * @param string $file
	 */
	protected function includeClass($file)
	{
		include $file;
	}
	
	/**
	 * Get the file path based on class name
	 * 
	 * @param string $dir
	 * @param string $class
	 * @return string
	 */
	protected function getFileName($dir, $class)
	{
		return $dir . $class . '.php';
	}
	
	/**
	 * Checks if the file exists and if it's readable
	 * 
	 * @param string $file
	 * @return boolean
	 */
	protected function fileExists($file)
	{
		return is_readable($file);
	}
	
	/**
	 * Get the list of directories to search
	 * 
	 * @return ArrayObject
	 */
	protected function getDirectories()
	{
		$rootDir = realpath(dirname(__FILE__) . '/../') . '/';
		$dir = new ArrayObject();
		
		$dir->append($rootDir . 'association/');
		$dir->append($rootDir . 'config/');
		$dir->append($rootDir . 'core/');
		$dir->append($rootDir . 'database/');
		$dir->append($rootDir . 'map/');
		$dir->append($rootDir . 'nestedset/');
		$dir->append($rootDir . 'pagination/');
		$dir->append($rootDir . 'proxy/');
		
		return $dir;
	}
}