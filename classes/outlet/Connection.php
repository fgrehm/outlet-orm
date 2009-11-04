<?php

namespace outlet;

/**
 * @method PDOStatement prepare (string $statement)
 */
class Connection {
	private $dialect;
	private $pdo;

	protected $nestedTransactionLevel = 0;

	/**
	 * Assume that the driver supports transactions until proven otherwise
	 * @var bool
	 */
	protected $driverSupportsTransactions = true;

	function __construct (\PDO $pdo, $dialect) {
		$this->pdo = $pdo;
		$this->dialect = $dialect;
	}

	public function execute($sql) {
		$this->pdo->exec($sql);
	}

	function __call ($method, $args) {
		return call_user_func_array(array($this->pdo, $method), $args);
	}
}

