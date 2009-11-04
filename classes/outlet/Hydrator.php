<?php

use outlet\Query;
use outlet\Proxy;

class OutletHydrator {
	public function  __construct(OutletSession $session) {
		$this->session = $session;
		$this->config = $session->getConfig();
		$this->uow = $session->getUnitOfWork();
	}

	protected function gatherRowData($rowData){
		// TODO: eager fetching
		$data = array();
		foreach ($this->propertiesAliases as $alias => $prop)
			$data[$prop] = $rowData["{$this->fromAliased}_{$alias}"];
		return array(
			$this->fromAliased => array(
				'class' => $this->from,
				'data' => $data
			)
		);
	}

	protected function getEntity($classAlias, $gatheredRowData) {
		return $this->uow->createEntity($gatheredRowData[$classAlias]['class'], $gatheredRowData[$classAlias]['data']);
	}

	protected function hydrateRow($rowData) {
		// TODO: add eager fetching logic
		return $this->getEntity($this->fromAliased, $this->gatherRowData($rowData));
	}

	public function hydrateResult($result, Query $query) {
		$this->query = $query;

		// get the 'from'
		$this->from = $query->from;
		$tmp = explode(' ', $this->from);
		$from = $tmp[0];
		$this->fromAliased = strtolower(count($tmp)>1 ? $tmp[1] : $tmp[0]);

		// get properties aliases
		$this->propertiesAliases = array();
		foreach (array_keys($this->config->getEntity($this->from)->getAllProperties()) as $prop) {
			// by default all properties should be converted to lower case when querying on repository
			// since postgresql does it as well
			$this->propertiesAliases[strtolower($prop)] = $prop;
		}

		$return = array();
		if (is_array($result)) {
			foreach ($result as $rowData) {
				$return[] = $this->hydrateRow($rowData);
			}
		} else { // PDO statement
			while ($rowData = $result->fetch(PDO::FETCH_ASSOC)) {
				$return[] = $this->hydrateRow($rowData);
			}
		}

		// Cleanup
		$this->from =
		$this->fromAliased =
		$this->propertiesAliases = 
		$this->query = null;
		return $return;
	}
}