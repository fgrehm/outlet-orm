<?php
/**
 * File level comment
 * 
 * @package org.outlet-orm
 * @subpackage database
 * @author Alvaro Carrasco
 */

/**
 * Outlet wrapper for a database connection.
 * 
 * @package org.outlet-orm
 * @subpackage database
 * @author Alvaro Carrasco
 */
class OutletConnection
{
	/**
	 * @var string
	 */
	private $driver;
	
	/**
	 * @var string
	 */
	private $dialect;
	
	/**
	 * @var PDO
	 */
	private $pdo;
	
	/**
	 * @var int
	 */
	protected $nestedTransactionLevel;
	
	/**
	 * Assume that the driver supports transactions until proven otherwise
	 * 
	 * @see OutletConnection::beginTransaction()
	 * @var bool
	 */
	protected $driverSupportsTransactions;

	/**
	 * Constructs a new instance of OutletConnection
	 * 
	 * @param PDO $pdo
	 * @param string $dialect It can be 'sqlite', 'mysql', 'mssql', or 'pgsql'
	 * @return OutletConnection instance
	 */
	public function __construct(PDO $pdo, $dialect)
	{
		$this->nestedTransactionLevel = 0;
		$this->driverSupportsTransactions = true;
		$this->pdo = $pdo;
		$this->dialect = $dialect;
	}

	/**
	 * The dialect, can be 'sqlite', 'mysql', 'mssql', or 'pgsql'
	 * @return string
	 */
	public function getDialect()
	{
		return $this->dialect;
	}

	/**
	 * The PHP Database Object
	 * 
	 * @link http://us2.php.net/pdo
	 * @return PDO
	 */
	public function getPDO()
	{
		return $this->pdo;
	}

	/**
	 * Just for testing
	 *  
	 * @link http://us2.php.net/pdo
	 * @param PDO $pdo
	 */
	public function setPDO(PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	/**
	 * Begins a database transaction
	 * 
	 * @return bool true if successful, false otherwise 
	 */
	public function beginTransaction()
	{
		if (!$this->nestedTransactionLevel++) {
			// attempt standard pdo beginTransaction
			try {
				return $this->pdo->beginTransaction();
			} catch (PDOException $e) {
				// save the fact that this driver (probably dblib) doesn't support transactions
				if ($this->driverSupportsTransactions) {
					$this->driverSupportsTransactions = false;
				}
				
				return $this->exec('BEGIN TRANSACTION');
			}
		}
		
		return true;
	}

	/**
	 * Commit a database transaction
	 * 
	 * @see OutletConnection::beginTransaction
	 * @return bool true if successful, false otherwise
	 */
	public function commit()
	{
		if (!--$this->nestedTransactionLevel) {
			// commit using best method as determined in OutletConnection::beginTransaction
			if ($this->driverSupportsTransactions) {
				return $this->pdo->commit();
			} else {
				return $this->exec('COMMIT TRANSACTION');
			}
		}
		
		return true;
	}

	/**
	 * Rollback the current database transaction
	 * 
	 * @see OutletConnection::beginTransaction()
	 * @return bool true if successful, false otherwise
	 */
	public function rollBack()
	{
		if (!--$this->nestedTransactionLevel) {
			// rollback using best method as determined in OutletConnection::beginTransaction()
			if ($this->driverSupportsTransactions) {
				return $this->pdo->rollBack();
			} else {
				$this->exec('ROLLBACK TRANSACTION');
			}
		}
		
		return true;
	}

	/**
	 * Quotes a value to escape special characters, protects against sql injection attacks
	 * 
	 * @param mixed $v value to escape
	 * @return string the escaped value 
	 */
	public function quote($v)
	{
		$quoted = $this->pdo->quote($v);
		
		// odbc doesn't support quote and returns false
		// quote it manually if that's the case	
		if ($v !== false && $quoted === false) {
			if (is_int($v)) {
				$quoted = $v;
			} else {
				$quoted = "'" . str_replace("'", "''", $v) . "'";
			}
		}
		
		return $quoted;
	}

	/**
	 * Automagical __call method, overloaded to allow transparent callthrough to the pdo object
	 * 
	 * @param object $method method to call 
	 * @param object $args arguments to pass to method
	 * @return mixed result from the pdo function call
	 */
	public function __call($method, $args)
	{
		return call_user_func_array(array($this->pdo, $method), $args);
	}

	/**
	 * Returns last generated ID
	 * If using PostgreSQL the $sequenceName needs to be specified
	 * 
	 * @param string $sequenceName [optional] The sequence name, defaults to ''
	 * @return int the last insert id
	 */
	public function lastInsertId($sequenceName = '')
	{
		if ($this->getDialect() == 'mssql') {
			return $this->query('SELECT SCOPE_IDENTITY()')->fetchColumn(0);
		} else {
			return $this->pdo->lastInsertId($sequenceName);
		}
	}
}