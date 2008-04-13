<?php
require 'Outlet.php';
require 'OutletProxyGenerator.php';

Outlet::init($_SERVER['argv'][1]);

$conf = require $_SERVER['argv'][1];

$s = "<?php\n";
$s .= OutletProxyGenerator::generate($conf);

file_put_contents('outlet-proxies.php', $s);

