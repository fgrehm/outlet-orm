<?php
require_once 'application/org.outlet-orm/autoloader/OutletAutoloader.php';
require_once 'PHPUnit/Framework/TestCase.php';

/**
 * OutletAutoloader test case.
 */
class OutletAutoloaderTest extends PHPUnit_Framework_TestCase
{
	/**
	 * Tests OutletAutoLoader->autoLoad()
	 */
	public function testAutoLoad()
	{
		$load = $this->getMock('OutletAutoLoader', array('includeClass', 'getFileName', 'fileExists', 'getDirectories'));
		
		$load->expects($this->once())
			 ->method('getDirectories')
			 ->will($this->returnValue(array('')));
			 
		$load->expects($this->once())
			 ->method('includeClass')
			 ->with('Cpf.php');
			 
		$load->expects($this->once())
			 ->method('getFileName')
			 ->with('', 'Cpf')
			 ->will($this->returnValue('Cpf.php'));
			 
		$load->expects($this->once())
			 ->method('fileExists')
			 ->with('Cpf.php')
			 ->will($this->returnValue(true));
			 
		$load->autoLoad('Cpf');
	}
	
	/**
	 * Tests OutletAutoLoader->autoLoad()
	 */
	public function testAutoLoadNotFound()
	{
		$load = $this->getMock('OutletAutoLoader', array('includeClass', 'getFileName', 'fileExists', 'getDirectories'));
		
		$load->expects($this->once())
			 ->method('getDirectories')
			 ->will($this->returnValue(array('', '', '')));
			 
		$load->expects($this->never())
			 ->method('includeClass');
			 
		$load->expects($this->exactly(3))
			 ->method('getFileName')
			 ->with('', 'Cpf')
			 ->will($this->returnValue('Cpf.php'));
			 
		$load->expects($this->exactly(3))
			 ->method('fileExists')
			 ->with('Cpf.php')
			 ->will($this->returnValue(false));
			 
		$load->autoLoad('Cpf');
	}
}