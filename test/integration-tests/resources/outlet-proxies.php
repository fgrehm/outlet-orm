<?php
class Address_OutletProxy extends Address implements OutletProxy
{
	static $_outlet;
}

class Bug_OutletProxy extends Bug implements OutletProxy
{
	static $_outlet;
	
	function getProject()
	{
		if (is_null($this->ProjectID))
			return parent::getProject();
			
		if (is_null(parent::getProject())) {
			parent::setProject(
			self::$_outlet->load('Project', $this->ProjectID));
		}
		
		return parent::getProject();
	}
	
	function setProject(Project $ref)
	{
		if (is_null($ref)) {
			throw new OutletException(
			"You can not set this to NULL since this relationship has not been marked as optional");
			return parent::setProject(null);
		}
		
		$this->ProjectID = $ref->getProjectID();
		
		return parent::setProject($ref);
	}
}

class Machine_OutletProxy extends Machine implements OutletProxy
{
	static $_outlet;
}

class Project_OutletProxy extends Project implements OutletProxy
{
	static $_outlet;
	
	function getBugs()
	{
		$args = func_get_args();
		
		if (count($args)) {
			if (is_null($args[0]))
				return parent::getBugs();
			$q = $args[0];
		} else {
			$q = '';
		}
		
		if (isset($args[1]))
			$params = $args[1];
		else
			$params = array();
		$q = trim($q);
		
		if (stripos($q, 'where') !== false) {
			$q = '{Bug.ProjectID} = ' . $this->getProjectID() . ' and ' . substr($q, 5);
		} else {
			$q = '{Bug.ProjectID} = ' . $this->getProjectID() . ' ' . $q;
		}
		
		$query = self::$_outlet->from('Bug')->where($q, $params);
		$cur_coll = parent::getBugs();
		
		if (!$cur_coll instanceof OutletCollection || $cur_coll->getQuery() != $query) {
			parent::setBugs(new OutletCollection($query));
		}
		
		return parent::getBugs();
	}
}

class User_OutletProxy extends User implements OutletProxy
{
	static $_outlet;
	
	function getWorkAddresses()
	{
		$args = func_get_args();
		
		if (count($args)) {
			if (is_null($args[0]))
				return parent::getWorkAddresses();
			$q = $args[0];
		} else {
			$q = '';
		}
		
		if (isset($args[1]))
			$params = $args[1];
		else
			$params = array();
			
		$q = trim($q);
		
		if (stripos($q, 'where') !== false) {
			$q = '{Address.UserID} = ' . $this->UserID . ' and ' . substr($q, 5);
		} else {
			$q = '{Address.UserID} = ' . $this->UserID . ' ' . $q;
		}
		
		$query = self::$_outlet->from('Address')->where($q, $params);
		$cur_coll = parent::getWorkAddresses();
		
		if (!$cur_coll instanceof OutletCollection || $cur_coll->getQuery() != $query) {
			parent::setWorkAddresses(new OutletCollection($query));
		}
		return parent::getWorkAddresses();
	}
	function getBugs()
	{
		if (parent::getBugs() instanceof OutletCollection)
			return parent::getBugs();
			
		$q = self::$_outlet->from('Bug')
						   ->innerJoin('watchers ON watchers.bug_id = {Bug.ID}')
						   ->where('watchers.user_id = ?', array($this->UserID));
		
		parent::setBugs(new OutletCollection($q));
		
		return parent::getBugs();
	}
}
class Profile_OutletProxy extends Profile implements OutletProxy
{
	static $_outlet;
	
	function getUser()
	{
		if (is_null($this->getUserID()))
			return parent::getUser();
			
		if (is_null(parent::getUser()) && $this->getUserID()) {
			parent::setUser(self::$_outlet->load('User', $this->getUserID()));
		}
		
		return parent::getUser();
	}
}