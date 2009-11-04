<?php
namespace outlet;

$root = __DIR__.'/';
require_once $root.'Connection.php';
require_once $root.'Mapper.php';
require_once $root.'ProxyAutoloader.php';
require_once $root.'ProxyGenerator.php';
require_once $root.'Config.php';
require_once $root.'IdentityMap.php';
require_once $root.'QueryParser.php';
require_once $root.'Session.php';
require_once $root.'UnitOfWork.php';
require_once $root.'Query.php';
require_once $root.'Hydrator.php';
require_once $root.'repositories/Repository.php';

class Outlet {
	public static $configs = array();

	public static function createProxies(OutletConfig $config) {
		$gen = new outlet\ProxyGenerator($config);
		eval($gen->generate());
	}

	public static function addConfig($config, $name = 'default') {
		if (is_array($config))
			$config = new \OutletConfig($config);
		self::$configs[$name] = $config;
	}

	/**
	 *
	 * @param mixed $config
	 * @return OutletSession
	 */
	public static function openSession($config = 'default') {
		if ($config instanceof \OutletConfig)
			return new \OutletSession($config);
		else
			return new \OutletSession(self::$configs[$config]);
	}
}

class OutletException extends \Exception {}

interface Proxy {}