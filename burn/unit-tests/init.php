<?php
// set this to mysql, mssql, or sqlite
define('DATABASE_DRIVER', 'sqlite');
set_include_path(dirname(__FILE__).'/../classes'.PATH_SEPARATOR.get_include_path());

// outlet
require_once 'outlet/Outlet.php';
require_once 'entities.php';

// basic setup
switch (DATABASE_DRIVER) {
	case 'sqlite':
            $conf = include('outlet-config-sqlite.php');
            break;
        case 'mysql':
            $conf = include('outlet-config-mysql.php');
            break;
        case 'pgsql':
            $conf = include('outlet-config-pgsql.php');
            break;
	default: throw new Exception('Unsupported database driver: '.DATABASE_DRIVER);
}
Outlet::init($conf);
Outlet::getInstance()->createProxies();
Outlet::getInstance()->getConnection()->getPDO()->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

