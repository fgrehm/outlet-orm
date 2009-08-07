<?php

/**
 * @method PDOStatement prepare (string $statement)
 */
class OutletConnection {
	private $driver;
	private $dialect;
	private $pdo;

	protected $nestedTransactionLevel = 0;

	/**
	 * Assume that the driver supports transactions until proven otherwise
	 * @var bool
	 */
	protected $driverSupportsTransactions = true;

	/**
	 * @param PDO $pdo
	 * @param string $dialect It can be 'sqlite', 'mysql', 'mssql', or 'pgsql'
	 */
	function __construct (PDO $pdo, $dialect) {
		$this->pdo = $pdo;
		$this->dialect = $dialect;
	}	

	function getDialect () {
		return $this->dialect;
	}

	/**
	 * @return PDO
	 */
	function getPDO () {
		return $this->pdo;
	}

	function beginTransaction () {
		if (!$this->nestedTransactionLevel++) {
			
			// since dblib driver doesn't support transactions
			try {
				return $this->pdo->beginTransaction();
			} catch (PDOException $e) {
				// save the fact that this driver (probably dblib) doesn't support transactions
				if ($this->driverSupportsTransactions) $this->driverSupportsTransactions = false;	
				return $this->exec('BEGIN TRANSACTION');
			}
		}
		return true;
	}

	function commit () {
		if (!--$this->nestedTransactionLevel) {
			
			// since dblib driver doesn't support transactions
			if ($this->driverSupportsTransactions) {
				return $this->pdo->commit();
			} else {
				return $this->exec('COMMIT TRANSACTION');
			}
		}
		return true;
	}
	
	function rollBack () {
		if (!--$this->nestedTransactionLevel) {
			
			// since dblib driver doesn't support transactions
			if ($this->driverSupportsTransactions) {
				return $this->pdo->rollBack();
			} else {
				$this->exec('ROLLBACK TRANSACTION');
			}
		}
		return true;
	}
	
	function quote ($v) {
		$quoted = $this->pdo->quote($v);
		
		// odbc doesn't support quote and returns false
		// quote it manually if that's the case	
		if ($v !== false && $quoted===false) {
			if (is_int($v)) $quoted = $v;
			else $quoted = "'".str_replace("'", "''", $v)."'";
		}	
		return $quoted;
	}

	function __call ($method, $args) {
		return call_user_func_array(array($this->pdo, $method), $args);
	}
	
	/**
	 * Returns last generated ID
	 *
	 * If using PostgreSQL the $sequenceName needs to be specified
	 */
	function lastInsertId ($sequenceName = '') {
		if ($this->getDialect() == 'mssql') {
			return $this->query('SELECT SCOPE_IDENTITY()')->fetchColumn(0);
		} else{
			return $this->pdo->lastInsertId($sequenceName);
		}
	}
}

