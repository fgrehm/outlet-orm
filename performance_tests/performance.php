<?php

class BaseTestRunner {
	private $profiler;

	protected $supportedOperations = array();

	private function  __call($name,  $arguments) {
		$name = substr($name, 1);
		if (count($this->supportedOperations) == 0 || in_array($name, $this->supportedOperations)) {
			$this->profiler->startTimer($name, "$name x {$arguments[0]}");
			$this->$name($arguments[0]);
			$this->profiler->stopTimer($name);
			$this->cleanUp();
		}
		return $this;
	}

	public function setProfiler($profiler) {
		$this->profiler = $profiler;
	}

	protected final function _initialize(){
		$this->profiler->startTimer('init_runner', 'Initializing runner');
		$this->initialize();
		$this->profiler->stopTimer('init_runner');
		return $this;
	}
	protected function initialize(){}
	protected function cleanUp() { }
	protected function insertRecords($times){}
	protected function bulkInsertRecords($records){}
	protected function selectRecords($times){}
	protected function selectWithCriteria($times){}
	protected function updateRecords($times){}
	protected function bulkUpdateRecords($times){}
	protected function autoUpdateRecords($times){}
	protected function deleteRecords($times){}

	public final function run($times = 10) {
		$this->_initialize()
			->_insertRecords($times)
			->_bulkInsertRecords($times)
			->_selectRecords(10)
			->_selectWithCriteria($times)
			->_updateRecords($times)
			->_bulkUpdateRecords($times)
			->_autoUpdateRecords($times)
			->_deleteRecords($times);
	}
}

class PDOTest extends BaseTestRunner {
	/**
	 *
	 * @var PDO
	 */
	protected $pdo;

	protected $supportedOperations = array(
		'insertRecords',
		'selectRecords'
	);

	protected function initialize(){
		$this->pdo = new PDO('sqlite::memory:');
		$this->pdo->exec('CREATE TABLE users (id INTEGER, name TEXT)');
	}
	protected function insertRecords($times){
		for ($i = 0; $i < $times; $i++)
			$this->pdo->exec("INSERT INTO users (id, name) VALUES ($i, 'Name $i')");
	}
	protected function selectRecords($times){
		for ($i = 0; $i < $times; $i++)
			$this->pdo->query("SELECT * FROM users");
	}
}

class OutletTest extends BaseTestRunner {
	/**
	 *
	 * @var OutletSession
	 */
	protected $outletSession;

	protected $supportedOperations = array(
		'insertRecords',
		'bulkInsertRecords',
		'selectRecords'
	);

	protected function initialize(){
		require_once(dirname(__FILE__).'/../classes/outlet/Outlet.php');
		$this->pdo = new PDO('sqlite::memory:');
		$config = array(
			'connection' => array(
				'pdo' => $this->pdo,
				'dialect' => 'sqlite'
			),
			'classes' => array(
				'User' => array(
						'table' => 'users',
						'props' => array(
							'id' => array('id', 'int', array('pk' => true)),
							'name' => array('name', 'varchar')),
						'useGettersAndSetters' => false
				)
			)
		);
		$config = new OutletConfig($config);

		$proxyGenerator = new OutletProxyGenerator($config);
		eval($proxyGenerator->generate());
		
		$this->outletSession = Outlet::openSession($config);

		$this->pdo->exec('CREATE TABLE users (id INTEGER, name TEXT)');
	}
	protected function cleanUp() {
		$this->outletSession->clear();
	}
	protected function insertRecords($times){
		for ($i = 0; $i < $times; $i++){
			$user = new User($i, "Name $i");
			$this->outletSession->save($user);
		}
		// HACK:
		$this->pdo->exec('DELETE FROM users');
	}
	protected function bulkInsertRecords($times){
		$this->outletSession->setAutoFlush(false);
		for ($i = 0; $i < $times; $i++){
			$user = new User($i, "Name $i");
			$this->outletSession->save($user);
		}
		$this->outletSession->flush(false);
		$this->outletSession->setAutoFlush(true);
	}
	protected function selectRecords($times){
		// TODO: should we clear identity map after querying?
		for ($i = 0; $i < $times; $i++)
			$this->outletSession->from('User')->find();
	}
}

class User {
	public $id;
	public $name;

	public function __construct($id = 0, $name = '') {
		$this->id = $id;
		$this->name = $name;
	}
}




include_once('profiler.inc');

$prof = new Profiler(true, true, 'text');
//$prof = new Profiler(true, true, 'html', dirname(__FILE__).'/');

$runner = new PDOTest();
//$runner = new OutletTest();

$runner->setProfiler($prof);
$runner->run(1000);

$prof->printTimers( true );