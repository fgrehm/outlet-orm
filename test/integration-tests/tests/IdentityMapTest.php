<?php
require_once 'test/integration-tests/resources/OutletTestCase.php';

class IdentityMapTest extends OutletTestCase
{
	/**
	 * Try indirectly saving the same project twice
	 * and make sure it only gets inserted once
	 */
	public function testIndirectSave()
	{
		$outlet = Outlet::getInstance();
		
		$p = new OutletTest_Project();
		$p->setName('Project 1');
		
		$b1 = new OutletTest_Bug();
		$b1->Title = 'Bug 1';
		$b1->setProject($p);
		
		$outlet->save($p);
		
		$b2 = new OutletTest_Bug();
		$b2->Title = 'Bug 2';
		$b2->setProject($p);
		
		$outlet->save($b2);
		
		$this->assertEquals(1, count($outlet->select('OutletTest_Project', 'where {OutletTest_Project.Name} = ?', array('Project 1'))), 'Only one project inserted');
	}
	
	public function testMap()
	{
		$outlet = Outlet::getInstance();
		
		$p = new OutletTest_Project();
		$p->setName('Project 1');
		
		$outlet->save($p);
		
		$p_map = $outlet->select('OutletTest_Project', 'where {OutletTest_Project.ProjectID} = ?', array($p->getProjectID()));
		$p_map = $p_map[0];
		$this->assertTrue($p === $p_map, 'Diferent object on identity map');
		
		$outlet->clearCache();
		
		$p_map = $outlet->select('OutletTest_Project', 'where {OutletTest_Project.ProjectID} = ?', array($p->getProjectID()));
		$p_map = $p_map[0];
		$this->assertTrue($p !== $p_map, 'Equal object on identity map');
	}
}