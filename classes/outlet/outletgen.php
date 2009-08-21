<?php
/**
 * Command line tool to build proxies, allows byte-code caches to cache the proxies code.
 * This is an alternative to using Outlet::createProxies()
 * @see Outlet::createProxies()
 * @see OutletProxyGenerator::generate() 
 * @package outlet
 */
set_include_path(dirname(__FILE__).'/..'.PATH_SEPARATOR.get_include_path());
require 'outlet/Outlet.php';
require 'outlet/OutletProxyGenerator.php';

Outlet::init(require $_SERVER['argv'][1]);

$conf = require $_SERVER['argv'][1];

$gen = new OutletProxyGenerator(Outlet::getInstance()->getConfig());

$s = "<?php\n";
$s .= $gen->generate();

file_put_contents('outlet-proxies.php', $s);

