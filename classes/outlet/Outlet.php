<?php
require 'Connection.php';
require 'Mapper.php';
require 'Proxy.php';
require 'ProxyGenerator.php';
require 'Config.php';
require 'IdentityMap.php';
require 'QueryParser.php';
require 'Session.php';
require 'UnitOfWork.php';
require 'Query.php';
require 'Hydrator.php';
require 'repositories/SQLite.php';

class Outlet {
	public static function createProxies(OutletConfig $config) {
		$gen = new OutletProxyGenerator($config);
		eval($gen->generate());
	}

	public static function openSession(OutletConfig $config) {
		return new OutletSession($config);
	}
}

class OutletException extends Exception {}

