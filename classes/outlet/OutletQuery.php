<?php

class OutletQuery {
	private $from;
	private $with = array();
	private $query;
	private $params = array();
	
	/**
	 * @param string $from
	 * @return OutletQuery
	 */
	function from ($from) {
		$this->from = $from;
		
		return $this;
	}
	
	/**
	 * @param string $q
	 * @param array $params
	 * @return OutletQuery
	 */
	function where ($q, array $params=array()) {
		$this->query = $q;
		$this->params = $params;
		
		return $this;
	}
	
	/**
	 * @return OutletQuery
	 */
	function with () {
		$this->with = func_get_args();
		
		return $this;
	}
	
	/**
	 * @return array
	 */
	function find () {
		$outlet = Outlet::getInstance();
		
		// get the 'from'
		$tmp = explode(' ', $this->from);

		$from = $tmp[0];
		$from_aliased = (count($tmp)>1 ? $tmp[1] : $tmp[0]);

		$config = Outlet::getInstance()->getConfig();
		$entity_config = $config->getEntity($from);
		$props = $entity_config->getProperties();
		
		$from_props = $props;
		
		$select_cols = array();
		foreach ($props as $key=>$p) {
			$select_cols[] = "\n{".$from_aliased.'.'.$key.'} as '.$from_aliased.'_'.$key;
		}
		
		// get the include entities
		$with = array();
		$with_aliased = array();
		
		$join_q = '';
		foreach ($this->with as $with_key=>$j) {
			$tmp = explode(' ', $j);
			
			$with[$with_key] = $tmp[0];
			$with_aliased[$with_key] = (count($tmp)>1 ? $tmp[1] : $tmp[0]);
			
			$assoc = $entity_config->getAssociation($with[$with_key]);
			
			$props = $config->getEntity($assoc->getForeign())->getProperties();
			foreach ($props as $key=>$p) {
				$select_cols[] = "\n{".$with_aliased[$with_key].'.'.$key.'} as '.$with_aliased[$with_key].'_'.$key;
			}
		
			$aliased_join = $with_aliased[$with_key];
			$join_q .= "LEFT JOIN {".$assoc->getForeign()." ".$aliased_join."} ON {".$from_aliased.'.'.$assoc->getKey()."} = {".$with_aliased[$with_key].'.'.$assoc->getRefKey()."} \n";
		}
		
		$q = "SELECT ".implode(', ', $select_cols)." \n";
		$q .= " FROM {".$this->from."} \n";
		$q .= $join_q;
		
		echo $q;
		
		$stmt = $outlet->query($q);
		
		$res = array();
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$data = array();
			foreach ($from_props as $key=>$p) {
				$data[$p[0]] = $row[$from_aliased.'_'.$key];
			}

			$obj = $outlet->populateObject($from, new $from, $data);
			
		
			foreach ($with as $with_key=>$w) {
				$a = $entity_config->getAssociation($w);
				
				if ($a) {
					$data = array();
					$with_entity = $config->getEntity($a->getForeign());
					foreach ($with_entity->getProperties() as $key=>$p) {
						$data[$p[0]] = $row[$with_aliased[$with_key].'_'.$key];
					}
					
					$setter = $a->getSetter();
					
					$foreign = $a->getForeign();

					$obj->$setter($outlet->populateObject($foreign, new $foreign, $data));
				}
			}
			
			$res[] = $obj;
		}
		
		return $res;
	}
	
	public function findOne () {
		$res = $this->find();
		
		if (count($res)) return $res[0];
	}
}
