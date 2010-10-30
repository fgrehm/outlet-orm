<?php
define('DATABASE_DRIVER', 'sqlite');

date_default_timezone_set('America/Sao_Paulo');

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/../../../');
require 'application/org.outlet-orm/autoloader/OutletAutoloader.php';

spl_autoload_register(array(new OutletAutoloader(), 'autoLoad'));

require_once 'test/integration-tests/resources/entities.php';
require_once 'test/integration-tests/resources/OutletTestSetup.php';

switch (DATABASE_DRIVER) {
	case 'sqlite':
		$conf = include 'outlet-config-sqlite.php';
		break;
	case 'mysql':
		$conf = include 'outlet-config-mysql.php';
		break;
	case 'pgsql':
		$conf = include 'outlet-config-pgsql.php';
		break;
	default:
		throw new Exception('Unsupported database driver: ' . DATABASE_DRIVER);
}

Outlet::init($conf);
Outlet::getInstance()->createProxies();
Outlet::getInstance()->getConnection()->getPDO()->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);