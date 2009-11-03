<?php

abstract class OutletRepository {
	protected $uow;

	protected function toSqlValue($value, $type) {
		if (is_null($value)) return NULL;

		switch ($type) {
			case 'date': return $value->format('Y-m-d');
			case 'datetime': return $value->format('Y-m-d H:i:s');

			case 'int': return (int) $value;

			case 'float': return (float) $value;

			// Strings
			default: return $value;
		}
	}

	protected function prepare($sql) {
		return $this->connection->prepare($sql);
	}

	protected function execute($sql, $params) {
//		echo $sql, "\n";
//		var_dump($params);
		return $this->prepare($sql)->execute($params);
	}

	protected function getHydrator() {
		return $this->session->getHydrator();
	}

	protected function getUnitOfWork() {
		if ($this->uow == null)
			$this->uow = $this->session->getUnitOfWork();
		return $this->uow;
	}

	public function  __construct(OutletSession $session) {
		$this->session = $session;
		$this->connection = $session->getConnection();
		$this->config = $session->getConfig();
		$this->queryParser = $session->getQueryParser();
	}

	public function get($class, $pk) {
		$pkProps = array_keys($this->config->getEntity($class)->getPkProperties());
		$query = new OutletQuery($class, $this->session);

		return $query->where("{{$class}.".join("} = ? AND {{$class}.", $pkProps).'} = ?', $pk)
			->findOne();
	}

	public function add(&$obj) {	
		$config = $this->config->getEntity($obj);
		$class = $config->getClass();
		$table = $config->getTable();
		$properties = $config->getProperties();
		$mapper = $this->session->getMapperFor($class);
		$fields = array();
		$values = array();
		foreach ($properties as $name => $prop) {
			$fields[] = $prop->getField();
			$values[] = $this->toSqlValue($mapper->get($obj, $name), $prop->getType());
		}
		if($config->getDiscriminator()!==null) {
			$fields[] = $config->getDiscriminator()->getField();
			$values[] = $config->getDiscriminatorValue();
		}
		$q = "INSERT INTO $table ";
		$q .= "(" . implode(', ', $fields) . ")";
		$q .= " VALUES (?" . str_repeat(', ?', count($fields) - 1) . ")";
		if ($this->execute($q, $values)) {			
			$values = array();
			foreach ($properties as $name => $prop) {
				$values[$name] = $mapper->get($obj, $name);
			}
			$proxy = $this->getUnitOfWork()->createEntity($class, $values);
			$obj = $proxy;
		}
	}

	public function update($obj) {		
		$config = $this->config->getEntity($obj);
		$class = $config->getClass();
		$table = $config->getTable();
		$properties = $config->getProperties();
		$mapper = $this->session->getMapperFor($class);
		$fields = array();
		$values = array();
		$where = array();

		foreach ($mapper->getDirtyValues($obj, $this->getUnitOfWork()->getOriginalValues($obj)) as $propName => $value) {
			$fields[] = $properties[$propName]->getField().' = ?';
			$values[] = $this->toSqlValue($value, $properties[$propName]->getType());
		}
		$q = "UPDATE $table SET ";
		$q .= implode(', ', $fields).' WHERE ';
		foreach ($config->getPkProperties() as $prop) {
			$where[] = $prop->getField()." = ?";
			$values[] = $this->toSqlValue($this->getUnitOfWork()->getOriginalValue($obj, $prop->getName()), $prop->getType());
		}
		$q .= join(' AND ', $where);

		return $this->execute($q, $values);
	}

	public function remove($obj) {
		$config = $this->config->getEntity($obj);
		$class = $config->getClass();
		$table = $config->getTable();
		$mapper = $this->session->getMapperFor($class);
		$where = array();
		$values = array();

		$q = "DELETE FROM $table WHERE ";
		foreach ($config->getPkProperties() as $prop) {
			$where[] = $prop->getField()." = ?";
			$values[] = $this->toSqlValue($mapper->get($obj, $prop->getName()), $prop->getType());
		}
		$q .= join(' AND ', $where);
		return $this->execute($q, $values);
	}

	public function query(OutletQuery $query) {
		// get the 'from'
		$tmp = explode(' ', $query->from);
		$from = $tmp[0];
		$from_aliased = (count($tmp)>1 ? $tmp[1] : $tmp[0]);

		$props = $this->config->getEntity($from)->getAllProperties();
		$select_cols = array();
		foreach (array_keys($props) as $key) {
			$select_cols[] = "\n{".$from_aliased.'.'.$key.'} as '.strtolower($from_aliased.'_'.$key);
		}

		$q = "SELECT ".implode(', ', $select_cols)." \n";
		$q .= " FROM {".$query->from."} \n";

		if ($query->where)
			$q .= 'WHERE ' . $query->where."\n";
		
		$stmt = $this->prepare($this->queryParser->parse($q));
		$stmt->execute($query->params);
		
		return $this->getHydrator()->hydrateResult($stmt, $query);
	}

	public static function getRepository($session) {
		$dialect = $session->getConfig()->getDialect();
		if ($dialect == 'sqlite') {
			return new OutletRepositorySQLite($session);
		} elseif ($dialect == 'mysql'){
			return new OutletRepositoryMySQL($session);
		} else {
			throw new OutletException("Dialect [$dialect] not supported");
		}
	}
}

require_once dirname(__FILE__).'/SQLite.php';
require_once dirname(__FILE__).'/MySQL.php';