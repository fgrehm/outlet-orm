<?php

use outlet\QueryParser;

class Unit_QueryParserTest extends OutletTestCase {
	public function testParsingDoesNotAffectPlainSql() {
		$sql = 'SELECT testing.name FROM testing WHERE id = 1';

		$this->assertEquals($sql, $this->parser->parse($sql));
	}

	public function testParsingEntityClass() {
		$outletQuery = 'SELECT entity.colname1, entity.colname2, entity.colname3 FROM {QueryParserEntity} WHERE {QueryParserEntity.id} = ?';
		$expectedSql = 'SELECT entity.colname1, entity.colname2, entity.colname3 FROM entity WHERE entity.colname1 = ?';

		$this->assertEquals($expectedSql, $this->parser->parse($outletQuery));
	}

	public function testParsingProperties() {
		$outletQuery = 'SELECT {QueryParserEntity.id}, {QueryParserEntity.name}, entity.colname3 FROM entity WHERE {QueryParserEntity.id} = ?';
		$expectedSql = 'SELECT entity.colname1, entity.colname2, entity.colname3 FROM entity WHERE entity.colname1 = ?';

		$this->assertEquals($expectedSql, $this->parser->parse($outletQuery));
	}

	public function testParsingPropertiesAndEntityClassAliased() {
		$outletQuery = 'SELECT {E.id}, {E.name}, entity.colname3 FROM {QueryParserEntity E} WHERE {E.id} = ?';
		$expectedSql = 'SELECT E.colname1, E.colname2, entity.colname3 FROM entity E WHERE E.colname1 = ?';

		$this->assertEquals($expectedSql, $this->parser->parse($outletQuery));
	}

	public function testParsingUpdate() {
		$outletQuery = 'UPDATE {QueryParserEntity} SET {QueryParserEntity.name} = ?, entity.colname3 = ? WHERE {QueryParserEntity.id} = ?';
		$expectedSql = 'UPDATE entity SET colname2 = ?, entity.colname3 = ? WHERE colname1 = ?';

		$this->assertEquals($expectedSql, $this->parser->parse($outletQuery));
	}

	public function testParsingUpdateAliased() {
		$outletQuery = 'UPDATE {QueryParserEntity E} SET {E.name} = ?, E.colname3 = ? WHERE {E.id} = ?';
		$expectedSql = 'UPDATE entity E SET E.colname2 = ?, E.colname3 = ? WHERE E.colname1 = ?';

		$this->assertEquals($expectedSql, $this->parser->parse($outletQuery));
	}

	public function testParsingEntityAlias() {
		$parser = new QueryParser($this->_createConfig('application\model', 'EntityAlias'));

		$outletQuery = 'SELECT {EntityAlias.id}, {EntityAlias.name}, entity.colname3 FROM {EntityAlias} WHERE {EntityAlias.id} = ?';
		$expectedSql = 'SELECT entity.colname1, entity.colname2, entity.colname3 FROM entity WHERE entity.colname1 = ?';

		$this->assertEquals($expectedSql, $parser->parse($outletQuery));
	}

	public function testParsingEntityAliasAliased() {
		$parser = new QueryParser($this->_createConfig('application\model', 'EntityAlias'));

		$outletQuery = 'SELECT {A.id}, {A.name}, entity.colname3 FROM {EntityAlias A} WHERE {A.id} = ?';
		$expectedSql = 'SELECT A.colname1, A.colname2, entity.colname3 FROM entity A WHERE A.colname1 = ?';

		$this->assertEquals($expectedSql, $parser->parse($outletQuery));
	}

// TODO: do we need to support this?
//
//	public function testParsingQualifiedEntity() {
//		$parser = new QueryParser($this->_createConfig('application\model'));
//
//		$outletQuery = 'SELECT {application\model\QueryParserEntity.id}, {application\model\QueryParserEntity.name}, entity.colname3 FROM {application\model\QueryParserEntity} WHERE {application\model\QueryParserEntity.id} = ?';
//		$expectedSql = 'SELECT entity.colname1, entity.colname2, entity.colname3 FROM entity WHERE entity.colname1 = ?';
//
//		$this->assertEquals($expectedSql, $parser->parse($outletQuery));
//	}

	protected function _createConfig($namespace = '', $alias = null){
		if ($namespace != '')
			$namespace .= '\\';

		$classes = array(
			$namespace.'QueryParserEntity' => array(
				'alias' => $alias,
				'table' => 'entity',
				'props' => array(
					'id' => array('colname1', 'int', array('pk' => true)),
					'name' => array('colname2', 'varchar', array('pk' => true)),
					'address' => array('colname3', 'varchar', array('pk' => true))
				)
			)
		);
		return $this->createConfig($classes);
	}

	public function setUp() {
		$this->parser = new QueryParser($this->_createConfig());
	}
}