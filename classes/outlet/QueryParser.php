<?php

namespace outlet;

class QueryParser {
	/**
	 *
	 * @var OutletConfig
	 */
	private $conf;

	public function  __construct(Config $config) {
		$this->conf = $config;
	}

	public function parse($query) {
		preg_match_all('/\{[\\a-zA-Z0-9_]+(( |\.)[a-zA-Z0-9_]+)*\}/', $query, $matches, \PREG_SET_ORDER);

		// check if it's an update statement
		$update = (stripos(trim($query), 'UPDATE')===0);

		// get the table names
		$aliased = array();
		foreach ($matches as $key=>$m) {
			// clear braces
			$str = substr($m[0], 1, -1);

			// if it's an aliased class
			if (strpos($str, ' ')!==false) {
				$tmp = explode(' ', $str);
				$aliased[$tmp[1]] = $tmp[0];

				$query = str_replace($m[0], $this->conf->getEntity($tmp[0])->getTable().' '.$tmp[1], $query);
			// if it's a non-aliased class
			} elseif (strpos($str, '.')===false) {
				$table = $this->conf->getEntity($str)->getTable();
				$aliased[$table] = $str;
				$query = str_replace($m[0], $table, $query);
			}

		}

		// update references to the properties
		foreach ($matches as $key=>$m) {
			// clear braces
			$str = substr($m[0], 1, -1);

			// if it's a property
			if (strpos($str, '.')!==false) {
			list($en, $prop) = explode('.', $str);

			// if it's an alias
			if (isset($aliased[$en])) {
				$entity = $aliased[$en];

				// check for the existence of the field configuration
				$propertyConfig = $this->conf->getEntity($entity)->getProperty($prop);

				$col = $en.'.'.$propertyConfig->getField();
			} else {
				$entity = $en;

				$entityConfig = $this->conf->getEntity($entity, false);
				if ($entityConfig === null) throw new \OutletException('String ['.$entity.'] is not a valid entity or alias, check your query');

				// if it's an update statement,
				// we must not include the table
				if ($update) {
					$propertyConfig = $this->conf->getEntity($entity)->getProperty($prop);
					$col = $propertyConfig->getField();
				} else {
					$table = $entityConfig->getTable();

					$propconf = $entityConfig->getProperty($prop);

					// if it's an sql field
//					if (isset($propconf[2]) && isset($propconf[2]['sql'])) {
//						$col = $propconf[2]['sql'] .' as ' . $propconf[0];
//					} else {
						$col = $table.'.'.$propconf->getField();
//					}
				}
			}

			$query = str_replace(
				    $m[0],
				    $col,
				    $query
				);
			}
		}

		return $query;
	}
}