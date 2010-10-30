<?php
require_once 'application/org.outlet-orm/core/OutletException.php';
require_once 'application/org.outlet-orm/config/OutletConfigException.php';
require_once 'application/org.outlet-orm/config/OutletXmlException.php';
require_once 'application/org.outlet-orm/config/OutletXmlConfig.php';

require_once 'PHPUnit/Framework/TestCase.php';

/**
 * OutletXmlConfig test case.
 */
class OutletXmlConfigTest extends PHPUnit_Framework_TestCase
{
	/**
	 * Tests OutletXmlConfig::getInstance()
	 */
	public function testGetInstance()
	{
		$instance = OutletXmlConfig::getInstance();
		$instance->setXmlObj(new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><root></root>'));
		
		$this->assertEquals($instance, OutletXmlConfig::getInstance());
	}
	
	/**
	 * Tests OutletXmlConfig->parseFromFile()
	 * 
	 * @dataProvider parseProvider
	 */
	public function testParseFromFile($xmlString)
	{
		$file = dirname(__FILE__) . '/tmp.xml';
		file_put_contents($file, $xmlString);
		
		$obj = $this->getMock('OutletXmlConfig', array('parse'));
		$obj->setValidate(false);

		$obj->expects($this->once())
			->method('parse');
			
		$obj->parseFromFile($file);
		
		unlink($file);
	}

	/**
	 * Tests OutletXmlConfig->parseFromString()
	 * 
	 * @dataProvider parseProvider
	 */
	public function testParseFromString($xmlString)
	{
		$obj = $this->getMock('OutletXmlConfig', array('parse'));
		$obj->setValidate(false);

		$obj->expects($this->once())
			->method('parse');
			
		$obj->parseFromString($xmlString);
	}
	
	/**
	 * Tests OutletXmlConfig->parseFromFile()
	 * 
	 * @expectedException OutletXmlException
	 */
	public function testParseFromFileException()
	{
		$xmlString = 'aa';
		
		$file = dirname(__FILE__) . '/tmp.xml';
		
		file_put_contents($file, $xmlString);
			
		$obj = $this->getMock('OutletXmlConfig', array('parse'));
		$obj->setValidate(false);

		$obj->expects($this->never())
			->method('parse');

		try {
			$obj->parseFromFile($file);
			unlink($file);
		} catch (OutletXmlException $e) {
			unlink($file);
			throw $e;
		}
	}
	
	/**
	 * Tests OutletXmlConfig->parseFromFile()
	 * 
	 * @expectedException OutletXmlException
	 */
	public function testParseFromStringException()
	{
		$xmlString = 'aa';
		
		$obj = $this->getMock('OutletXmlConfig', array('parse'));
		$obj->setValidate(false);

		$obj->expects($this->never())
			->method('parse');
			
		$obj->parseFromString($xmlString);
	}
	
	/**
	 *  Tests OutletXmlConfig->validate()
	 *  
	 *  @dataProvider validateProvider
	 */
	public function testValidate($xmlString, $hasErrors)
	{
		$obj = new OutletXmlConfig();
		
		try {
			$obj->setXmlObj(new SimpleXMLElement($xmlString));
			$obj->validate();
			$this->assertFalse($hasErrors);
		} catch (Exception $e) {
			$this->assertTrue($hasErrors, $e->getMessage());
		}
	}
	
	public function validateProvider()
	{
		return array(
			array('', true),
			array(
				'<?xml version="1.0" encoding="UTF-8"?>
				<root></root>',
				true
			),
			array(
				'<?xml version="1.0" encoding="UTF-8"?>
				<outlet-config xmlns="http://www.outlet-orm.org" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.outlet-orm.org outlet-config.xsd ">
					<connection>
						<dialect>sqlite</dialect>
						<dsn>sqlite::memory:</dsn>
					</connection>
					<classes>
						<class name="Machine" table="machine">
							<property name="Name" column="name" type="varchar" />
							<property name="Description" column="description" type="varchar" />
						</class>
					</classes>
				</outlet-config>',
				false
			),
			array(
				'<?xml version="1.0" encoding="UTF-8"?>
				<outlet-config xmlns="http://www.outlet-orm.org" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.outlet-orm.org outlet-config.xsd ">
					<connection>
						<dialect>oracle</dialect>
						<dsn>sqlite::memory:</dsn>
					</connection>
					<classes>
						<class name="Machine" table="machine">
							<property name="Name" column="name" type="varchar" />
							<property name="Description" column="description" type="varchar" />
						</class>
					</classes>
				</outlet-config>',
				true
			)
		);
	}
	
	/**
	 * Tests OutletXmlConfig->parse()
	 * 
	 * @dataProvider parseProvider
	 */
	public function testParse($xmlString, $expectedArray, $hasErrors = false)
	{
		$obj = new OutletXmlConfig();
		$obj->setValidate(false);
		$obj->setXmlObj(new SimpleXMLElement($xmlString));
		
		try {
			$this->assertEquals($expectedArray, $obj->parse());
			$this->assertFalse($hasErrors);
		} catch (OutletXmlException $e) {
			$this->assertTrue($hasErrors, $e->getMessage());
		}
	}
	
	/**
	 * Data provider for OutletXmlConfigTest->testParse()
	 * 
	 * @return array()        
	 */
	public function parseProvider()
	{
		return array(
			array(
				'<?xml version="1.0" encoding="UTF-8"?>
				<outlet-config xmlns="http://www.outlet-orm.org" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.outlet-orm.org outlet-config.xsd ">
					<connection>
						<dialect>sqlite</dialect>
						<dsn>sqlite::memory:</dsn>
					</connection>
					<classes>
						<class name="Machine" table="machine">
							<property name="Name" column="name" type="varchar" />
							<property name="Description" column="description" type="varchar" />
						</class>
					</classes>
				</outlet-config>',
				array(
					'connection' => array('dsn' => 'sqlite::memory:', 'dialect' => 'sqlite'),
					'classes' => array(
						'Machine' => array(
							'table' => 'machine',
							'props' => array(
								'Name' => array('name', 'varchar'),
								'Description' => array('description', 'varchar'),
							)
						)
					)
				)
			),
			array(
				'<?xml version="1.0" encoding="UTF-8"?>
				<outlet-config xmlns="http://www.outlet-orm.org" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.outlet-orm.org outlet-config.xsd ">
					<connection>
						<dialect>sqlite</dialect>
						<dsn>sqlite::memory:</dsn>
					</connection>
					<classes>
						<class name="Machine" table="machine">
							<property name="Id" column="id" type="int" pk="true" autoIncrement="false" />
							<property name="Name" column="name" type="varchar" />
							<property name="Description" column="description" type="varchar" />
						</class>
					</classes>
				</outlet-config>',
				array(
					'connection' => array('dsn' => 'sqlite::memory:', 'dialect' => 'sqlite'),
					'classes' => array(
						'Machine' => array(
							'table' => 'machine',
							'props' => array(
								'Id' => array('id', 'int', array('pk' => true, 'autoIncrement' => false)),
								'Name' => array('name', 'varchar'),
								'Description' => array('description', 'varchar'),
							)
						)
					)
				)
			),
			array(
				'<?xml version="1.0" encoding="UTF-8"?>
				<outlet-config xmlns="http://www.outlet-orm.org" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.outlet-orm.org outlet-config.xsd ">
					<connection>
						<dialect>sqlite</dialect>
						<dsn>sqlite::memory:</dsn>
					</connection>
					<classes>
						<class name="Machine" table="machine" useGettersAndSetters="true" plural="Machiness" sequenceName="machine_id_seq">
							<property name="Id" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="Name" column="name" type="varchar" />
							<property name="Description" column="description" type="varchar" default="Fill this" />
						</class>
					</classes>
				</outlet-config>',
				array(
					'connection' => array('dsn' => 'sqlite::memory:', 'dialect' => 'sqlite'),
					'classes' => array(
						'Machine' => array(
							'table' => 'machine',
							'plural' => 'Machiness',
							'sequenceName' => 'machine_id_seq',
							'useGettersAndSetters' => true,
							'props' => array(
								'Id' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'Name' => array('name', 'varchar'),
								'Description' => array('description', 'varchar', array('default' => 'Fill this')),
							)
						)
					)
				)
			),
			array(
				'<?xml version="1.0" encoding="UTF-8"?>
				<outlet-config xmlns="http://www.outlet-orm.org" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.outlet-orm.org outlet-config.xsd ">
					<connection>
						<dialect>mysql</dialect>
						<dsn>mysql:host=myhost.com;dbname=testdb</dsn>
						<username>root</username>
						<password>admin</password>
					</connection>
					<classes>
						<class name="Machine" table="machine" useGettersAndSetters="true" plural="Machiness" sequenceName="machine_id_seq">
							<property name="Id" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="Name" column="name" type="varchar" />
							<property name="Description" column="description" type="varchar" />
						</class>
					</classes>
				</outlet-config>',
				array(
					'connection' => array('dsn' => 'mysql:host=myhost.com;dbname=testdb', 'dialect' => 'mysql', 'username' => 'root', 'password' => 'admin'),
					'classes' => array(
						'Machine' => array(
							'table' => 'machine',
							'plural' => 'Machiness',
							'sequenceName' => 'machine_id_seq',
							'useGettersAndSetters' => true,
							'props' => array(
								'Id' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'Name' => array('name', 'varchar'),
								'Description' => array('description', 'varchar'),
							)
						)
					)
				)
			),
			array(
				'<?xml version="1.0" encoding="UTF-8"?>
				<outlet-config xmlns="http://www.outlet-orm.org" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.outlet-orm.org outlet-config.xsd ">
					<connection>
						<dialect>mysql</dialect>
						<dsn>mysql:host=myhost.com;dbname=testdb</dsn>
					</connection>
					<classes>
						<class name="Machine" table="machine" useGettersAndSetters="true" plural="Machiness" sequenceName="machine_id_seq">
							<property name="Id" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="Name" column="name" type="varchar" />
							<property name="Description" column="description" type="varchar" />
						</class>
					</classes>
				</outlet-config>',
				array(
					'connection' => array('dsn' => 'mysql:host=myhost.com;dbname=testdb', 'dialect' => 'mysql', 'username' => 'root'),
					'classes' => array(
						'Machine' => array(
							'table' => 'machine',
							'plural' => 'Machiness',
							'sequenceName' => 'machine_id_seq',
							'useGettersAndSetters' => true,
							'props' => array(
								'Id' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'Name' => array('name', 'varchar'),
								'Description' => array('description', 'varchar'),
							)
						)
					)
				),
				true
			),
			array(
				'<?xml version="1.0" encoding="UTF-8"?>
				<outlet-config xmlns="http://www.outlet-orm.org" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.outlet-orm.org outlet-config.xsd ">
					<connection>
						<dialect>mysql</dialect>
						<dsn>mysql:host=myhost.com;dbname=testdb</dsn>
						<username>root</username>
						<password>admin</password>
					</connection>
					<classes>
						<class name="Address" table="address">
							<property name="AddressId" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="Street" column="street" type="varchar" />
						</class>
						<class name="User" table="user">
							<property name="UserId" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="FirstName" column="first_name" type="varchar" />
							<property name="LastName" column="last_name" type="varchar" />
							<property name="AddressId" column="address_id" type="int" />
							<association type="many-to-one" classReference="Address" key="AddressId"/>
						</class>
					</classes>
				</outlet-config>',
				array(
					'connection' => array('dsn' => 'mysql:host=myhost.com;dbname=testdb', 'dialect' => 'mysql', 'username' => 'root', 'password' => 'admin'),
					'classes' => array(
						'Address' => array(
							'table' => 'address',
							'props' => array(
								'AddressId' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'Street' => array('street', 'varchar')
							)
						),
						'User' => array(
							'table' => 'user',
							'props' => array(
								'UserId' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'FirstName' => array('first_name', 'varchar'),
								'LastName' => array('last_name', 'varchar'),
								'AddressId' => array('address_id', 'int')
							),
							'associations' => array(
								array('many-to-one', 'Address', array('key'=>'AddressId'))
							)
						)
					)
				)
			),
			array(
				'<?xml version="1.0" encoding="UTF-8"?>
				<outlet-config xmlns="http://www.outlet-orm.org" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.outlet-orm.org outlet-config.xsd ">
					<connection>
						<dialect>mysql</dialect>
						<dsn>mysql:host=myhost.com;dbname=testdb</dsn>
						<username>root</username>
						<password>admin</password>
					</connection>
					<classes>
						<class name="Address" table="address">
							<property name="AddressId" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="Street" column="street" type="varchar" />
						</class>
						<class name="User" table="user">
							<property name="UserId" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="FirstName" column="first_name" type="varchar" />
							<property name="LastName" column="last_name" type="varchar" />
							<property name="AddressId" column="address_id" type="int" />
							<association type="many-to-one" classReference="Address" key="AddressId" plural="Addresses" optional="true"/>
						</class>
					</classes>
				</outlet-config>',
				array(
					'connection' => array('dsn' => 'mysql:host=myhost.com;dbname=testdb', 'dialect' => 'mysql', 'username' => 'root', 'password' => 'admin'),
					'classes' => array(
						'Address' => array(
							'table' => 'address',
							'props' => array(
								'AddressId' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'Street' => array('street', 'varchar')
							)
						),
						'User' => array(
							'table' => 'user',
							'props' => array(
								'UserId' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'FirstName' => array('first_name', 'varchar'),
								'LastName' => array('last_name', 'varchar'),
								'AddressId' => array('address_id', 'int')
							),
							'associations' => array(
								array('many-to-one', 'Address', array('key'=>'AddressId', 'optional'=>true))
							)
						)
					)
				),
				true
			),
			array(
				'<?xml version="1.0" encoding="UTF-8"?>
				<outlet-config xmlns="http://www.outlet-orm.org" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.outlet-orm.org outlet-config.xsd ">
					<connection>
						<dialect>mysql</dialect>
						<dsn>mysql:host=myhost.com;dbname=testdb</dsn>
						<username>root</username>
						<password>admin</password>
					</connection>
					<classes>
						<class name="Address" table="address">
							<property name="AddressId" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="Street" column="street" type="varchar" />
						</class>
						<class name="User" table="user">
							<property name="UserId" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="FirstName" column="first_name" type="varchar" />
							<property name="LastName" column="last_name" type="varchar" />
							<property name="AddressId" column="address_id" type="int" />
							<association type="many-to-many" classReference="Address" key="AddressId" plural="Addresses" optional="true"/>
						</class>
					</classes>
				</outlet-config>',
				array(
					'connection' => array('dsn' => 'mysql:host=myhost.com;dbname=testdb', 'dialect' => 'mysql', 'username' => 'root', 'password' => 'admin'),
					'classes' => array(
						'Address' => array(
							'table' => 'address',
							'props' => array(
								'AddressId' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'Street' => array('street', 'varchar')
							)
						),
						'User' => array(
							'table' => 'user',
							'props' => array(
								'UserId' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'FirstName' => array('first_name', 'varchar'),
								'LastName' => array('last_name', 'varchar'),
								'AddressId' => array('address_id', 'int')
							),
							'associations' => array(
								array('many-to-many', 'Address', array('key'=>'AddressId', 'optional'=>true))
							)
						)
					)
				),
				true
			),
			array(
				'<?xml version="1.0" encoding="UTF-8"?>
				<outlet-config xmlns="http://www.outlet-orm.org" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.outlet-orm.org outlet-config.xsd ">
					<connection>
						<dialect>mysql</dialect>
						<dsn>mysql:host=myhost.com;dbname=testdb</dsn>
						<username>root</username>
						<password>admin</password>
					</connection>
					<classes>
						<class name="Address" table="address">
							<property name="AddressId" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="Street" column="street" type="varchar" />
						</class>
						<class name="User" table="user">
							<property name="UserId" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="FirstName" column="first_name" type="varchar" />
							<property name="LastName" column="last_name" type="varchar" />
							<property name="AddressId" column="address_id" type="int" />
							<association type="one-to-many" classReference="Address"/>
						</class>
					</classes>
				</outlet-config>',
				array(
					'connection' => array('dsn' => 'mysql:host=myhost.com;dbname=testdb', 'dialect' => 'mysql', 'username' => 'root', 'password' => 'admin'),
					'classes' => array(
						'Address' => array(
							'table' => 'address',
							'props' => array(
								'AddressId' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'Street' => array('street', 'varchar')
							)
						),
						'User' => array(
							'table' => 'user',
							'props' => array(
								'UserId' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'FirstName' => array('first_name', 'varchar'),
								'LastName' => array('last_name', 'varchar'),
								'AddressId' => array('address_id', 'int')
							),
							'associations' => array(
								array('one-to-many', 'Address', array())
							)
						)
					)
				),
				true
			),
			array(
				'<?xml version="1.0" encoding="UTF-8"?>
				<outlet-config xmlns="http://www.outlet-orm.org" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.outlet-orm.org outlet-config.xsd ">
					<connection>
						<dialect>mysql</dialect>
						<dsn>mysql:host=myhost.com;dbname=testdb</dsn>
						<username>root</username>
						<password>admin</password>
					</connection>
					<classes>
						<class name="Address" table="address">
							<property name="AddressId" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="Street" column="street" type="varchar" />
						</class>
						<class name="User" table="user">
							<property name="UserId" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="FirstName" column="first_name" type="varchar" />
							<property name="LastName" column="last_name" type="varchar" />
							<property name="AddressId" column="address_id" type="int" />
							<association type="one-to-many" classReference="Address" key="AddressId" optional="true"/>
						</class>
					</classes>
				</outlet-config>',
				array(
					'connection' => array('dsn' => 'mysql:host=myhost.com;dbname=testdb', 'dialect' => 'mysql', 'username' => 'root', 'password' => 'admin'),
					'classes' => array(
						'Address' => array(
							'table' => 'address',
							'props' => array(
								'AddressId' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'Street' => array('street', 'varchar')
							)
						),
						'User' => array(
							'table' => 'user',
							'props' => array(
								'UserId' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'FirstName' => array('first_name', 'varchar'),
								'LastName' => array('last_name', 'varchar'),
								'AddressId' => array('address_id', 'int')
							),
							'associations' => array(
								array('one-to-many', 'Address', array('key'=>'AddressId', 'optional' => true))
							)
						)
					)
				),
				true
			),
			array(
				'<?xml version="1.0" encoding="UTF-8"?>
				<outlet-config xmlns="http://www.outlet-orm.org" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.outlet-orm.org outlet-config.xsd ">
					<connection>
						<dialect>mysql</dialect>
						<dsn>mysql:host=myhost.com;dbname=testdb</dsn>
						<username>root</username>
						<password>admin</password>
					</connection>
					<classes>
						<class name="Address" table="address">
							<property name="AddressId" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="Street" column="street" type="varchar" />
						</class>
						<class name="User" table="user">
							<property name="UserId" column="id" type="int" pk="true" autoIncrement="true" />
							<property name="FirstName" column="first_name" type="varchar" />
							<property name="LastName" column="last_name" type="varchar" />
							<property name="AddressId" column="address_id" type="int" />
							<association type="one-to-many" classReference="Address" key="AddressId" table="test"/>
						</class>
					</classes>
				</outlet-config>',
				array(
					'connection' => array('dsn' => 'mysql:host=myhost.com;dbname=testdb', 'dialect' => 'mysql', 'username' => 'root', 'password' => 'admin'),
					'classes' => array(
						'Address' => array(
							'table' => 'address',
							'props' => array(
								'AddressId' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'Street' => array('street', 'varchar')
							)
						),
						'User' => array(
							'table' => 'user',
							'props' => array(
								'UserId' => array('id', 'int', array('pk' => true, 'autoIncrement' => true)),
								'FirstName' => array('first_name', 'varchar'),
								'LastName' => array('last_name', 'varchar'),
								'AddressId' => array('address_id', 'int')
							),
							'associations' => array(
								array('one-to-many', 'Address', array('key'=>'AddressId', 'table' => 'test'))
							)
						)
					)
				),
				true
			)
		);
	}
	
	/**
	 * Tests OutletXmlConfig->parse() when an object is not configured
	 * 
	 * @expectedException OutletXmlException
	 */
	public function testParseException()
	{
		$obj = new OutletXmlConfig();
		$obj->parse();
	}
}