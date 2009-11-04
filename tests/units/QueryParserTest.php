<?php

use outlet\QueryParser;

class Unit_QueryParserTest extends OutletTestCase {
	public function testParsingPlainSql() {
		$sql = 'SELECT testing.name FROM testing WHERE id = 1';

		$this->assertEquals($sql, $this->parser->parse($sql));
	}

	public function testParsingSelectOneTable() {
		$outletQuery = 'SELECT {QueryParserEntity.id}, {QueryParserEntity.name}, entity.colname3 FROM {QueryParserEntity} WHERE {QueryParserEntity.id} = ?';
		$expectedSql = 'SELECT entity.colname1, entity.colname2, entity.colname3 FROM entity WHERE entity.colname1 = ?';

		$this->assertEquals($expectedSql, $this->parser->parse($outletQuery));
	}

	public function testParsingSelectOneTableAliased() {
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

	public function setUp() {
		$classes = array(
			'QueryParserEntity' => array(
				'table' => 'entity',
				'props' => array(
					'id' => array('colname1', 'int', array('pk' => true)),
					'name' => array('colname2', 'varchar', array('pk' => true)),
					'address' => array('colname3', 'varchar', array('pk' => true))
				)
			)
		);
		$this->parser = new QueryParser($this->createConfig($classes));
	}
}