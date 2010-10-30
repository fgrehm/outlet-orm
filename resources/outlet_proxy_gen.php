<?php
/**
 * Command line tool to build proxies, allows byte-code caches to cache the proxies code.
 * This is an alternative to using Outlet::createProxies()
 * @see Outlet::createProxies()
 * @see OutletProxyGenerator::generate() 
 * @package org.outlet-orm
 * @subpackage resources
 */
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/../');
require 'application/org.outlet-orm/autoloader/OutletAutoloader.php';

spl_autoload_register(array(new OutletAutoloader(), 'autoLoad'));

Outlet::init(require $_SERVER['argv'][1]);

$gen = Outlet::getInstance()->getProxyGenerator();

$s = "<?php\n";
$s .= $gen->generate();

file_put_contents('outlet-proxies.php', $s);