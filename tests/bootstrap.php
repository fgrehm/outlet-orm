<?php
error_reporting(E_STRICT | E_ALL);

require_once realpath(dirname(__FILE__).'/../classes/outlet/Outlet.php');
require_once 'OutletTestCase.php';
require_once 'entities/entities.php';

//define('REPOSITORIES_TO_TEST', 'sqlite, mysql');
define('REPOSITORIES_TO_TEST', 'sqlite');

define('MYSQL_DSN', 'mysql:host=localhost;dbname=test_outlet;');
define('MYSQL_USER', 'root');
define('MYSQL_PASSWORD', '');