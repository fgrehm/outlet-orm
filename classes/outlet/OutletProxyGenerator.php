<?php
/**
 * Generator for all proxy classes
 * @package outlet
 */
class OutletProxyGenerator {
	private $config;

	/**
	 * Constructs a new instance of OutletProxyGenerator
	 * @param OutletConfig $config configuration
	 * @return OutletProxyGenerator instance
	 */
	function __construct (OutletConfig $config) {
		$this->config = $config;
	}


	/**
	 * Extracts primary key information from a configuration for a given class
	 * @param array $conf configuration array
	 * @param string $clazz entity class 
	 * @return array primary key properties
	 */
	function getPkProp($conf, $clazz) {
		foreach ($conf['classes'][$clazz]['props'] as $key=>$f) {
			if (isset($f[2]['pk']) && $f[2]['pk'] == true) {
				$pks[] = $key;
			}

			if (!count($pks)) {
				throw new Exception('You must specified at least one primary key');
			}

			if (count($pks) == 1) {
				return $pks[0];
			} else {
				return $pks;
			}
		}
	}

	/**
	 * Generates the source code for the proxy classes
	 * @return string class source
	 */
	function generate () {
		$c = '';
		foreach ($this->config->getEntities() as $entity) {
			$clazz = $entity->clazz;

			$c .= "class {$clazz}_OutletProxy extends $clazz implements OutletProxy { \n";
			$c .= "  static \$_outlet; \n";

			foreach ($entity->getAssociations() as $assoc) {
				switch ($assoc->getType()) {
					case 'one-to-many': $c .= $this->createOneToManyFunctions($assoc); break;
					case 'many-to-one': $c .= $this->createManyToOneFunctions($assoc); break;
					case 'one-to-one':	$c .= $this->createOneToOneFunctions($assoc); break;
					case 'many-to-many': $c .= $this->createManyToManyFunctions($assoc); break;
					default: throw new Exception("invalid association type: {$assoc->getType()}");
				}
			}
			$c .= "} \n";
		}

		return $c;
	}

	/**
	 * Generates the code to support one to one associations
	 * @param OutletAssociationConfig $config configuration
	 * @return string one to one function code
	 */
	function createOneToOneFunctions (OutletAssociationConfig $config) {
		$foreign	= $config->getForeign();
		$key 		= $config->getKey();
		$getter 	= $config->getGetter();
		$setter		= $config->getSetter();
		if ($config->getLocalUseGettersAndSetters())
			$key = 'get'.$key.'()';

		$c = '';
		$c .= "  function $getter() { \n";
		$c .= "	if (is_null(\$this->$key)) return parent::$getter(); \n";
		$c .= "	if (is_null(parent::$getter()) && \$this->$key) { \n";
		$c .= "	  parent::$setter( self::\$_outlet->load('$foreign', \$this->$key) ); \n";
		$c .= "	} \n";
		$c .= "	return parent::$getter(); \n";
		$c .= "  } \n";

		return $c;
	}

	/**
	 * Generates the code to support one to many associations
	 * @param OutletAssociationConfig $config configuration
	 * @return string one to many functions code
	 */
	function createOneToManyFunctions (OutletAssociationConfig $config) {
		$foreign	= $config->getForeign();
		$key 		= $config->getKey();
		$pk_prop 	= $config->getRefKey();
		$getter		= $config->getGetter();
		$setter		= $config->getSetter();
		if ($config->getLocalUseGettersAndSetters())
			$pk_prop = 'get'.$pk_prop.'()';

		$c = '';
		$c .= "  function {$getter}() { \n";
		$c .= "	\$args = func_get_args(); \n";
		$c .= "	if (count(\$args)) { \n";
		$c .= "	  if (is_null(\$args[0])) return parent::{$getter}(); \n";
		$c .= "	  \$q = \$args[0]; \n";
		$c .= "	} else { \n";
		$c .= "	  \$q = ''; \n";
		$c .= "	} \n";
		$c .= "	if (isset(\$args[1])) \$params = \$args[1]; \n";
		$c .= "	else \$params = array(); \n";

		// if there's a where clause
		//$c .= "	echo \$q; \n";
		$c .= "	\$q = trim(\$q); \n";
		$c .= "	if (stripos(\$q, 'where') !== false) { \n";
		$c .= "	  \$q = '{"."$foreign.$key} = '.\$this->$pk_prop.' and ' . substr(\$q, 5); \n";
		$c .= "	} else { \n";
		$c .= "	  \$q = '{"."$foreign.$key} = '.\$this->$pk_prop. ' ' . \$q; \n";
		$c .= "	}\n";
		//$c .= "	echo \"<h2>\$q</h2>\"; \n";

		$c .= "	\$query = self::\$_outlet->from('$foreign')->where(\$q, \$params); \n";
		$c .= "	\$cur_coll = parent::{$getter}(); \n";
		
		// only set the collection if the parent is not already an OutletCollection
		// or if the query is different from the previous query
		$c .= "	if (!\$cur_coll instanceof OutletCollection || \$cur_coll->getQuery() != \$query) { \n";
		$c .= "	  parent::{$setter}( new OutletCollection( \$query ) ); \n";
		$c .= "	} \n";
		$c .= "	return parent::{$getter}(); \n";
		$c .= "  } \n";

		return $c;
	}

	/**
	 * Generates the code to support many to many associations
	 * @param OutletManyToManyConfig $config configuration
	 * @return string many to many function code
	 */
	function createManyToManyFunctions (OutletManyToManyConfig $config) {
		$foreign	= $config->getForeign();

		$tableKeyLocal 		= $config->getTableKeyLocal();
		$tableKeyForeign 	= $config->getTableKeyForeign();

		$pk_prop 	= $config->getKey();
		$ref_pk		= $config->getRefKey();
		$getter		= $config->getGetter();
		$setter		= $config->getSetter();
		$table		= $config->getLinkingTable();
		if ($config->getForeignUseGettersAndSetters())
			$ref_pk = 'get'.$ref_pk.'()';

		$c = '';
		$c .= "  function {$getter}() { \n";
		$c .= "	if (parent::$getter() instanceof OutletCollection) return parent::$getter(); \n";
		//$c .= "	if (stripos(\$q, 'where') !== false) { \n";
		$c .= "	\$q = self::\$_outlet->from('$foreign') \n";
		$c .= "		->innerJoin('$table ON {$table}.{$tableKeyForeign} = {"."$foreign.$pk_prop}') \n";
		$c .= "		->where('{$table}.{$tableKeyLocal} = ?', array(\$this->$ref_pk)); \n";
		$c .= "	parent::{$setter}( new OutletCollection( \$q ) ); \n";
		$c .= "	return parent::{$getter}(); \n";
		$c .= "  } \n";

		return $c;
	}

	/**
	 * Generates the code to support many to one associations
	 * @param OutletAssociationConfig $config configuration
	 * @return string many to one function code
	 */
	function createManyToOneFunctions (OutletAssociationConfig $config) {
		$local		= $config->getLocal();
		$foreign	= $config->getForeign();
		$key		= $config->getKey();
		$refKey		= $config->getRefKey();
		$getter 	= $config->getGetter();
		$setter		= $config->getSetter();

		if ($config->getLocalUseGettersAndSetters()) {
			$keyGetter = 'get'.$key.'()';
		} else {
			$keyGetter = $key;
		}
		
		if ($config->getForeignUseGettersAndSetters()) {
			$refKey = 'get'.$refKey.'()';
		}

		$c = '';
		$c .= "  function $getter() { \n";
		$c .= "	if (is_null(\$this->$keyGetter)) return parent::$getter(); \n";
		$c .= "	if (is_null(parent::$getter())) { \n";//&& isset(\$this->$keyGetter)) { \n";
		$c .= "	  parent::$setter( self::\$_outlet->load('$foreign', \$this->$keyGetter) ); \n";
		$c .= "	} \n";
		$c .= "	return parent::$getter(); \n";
		$c .= "  } \n";

		$c .= "  function $setter($foreign \$ref".($config->isOptional() ? '=null' : '').") { \n";
		$c .= "	if (is_null(\$ref)) { \n";

		if ($config->isOptional()) {
			$c .= "	  \$this->$keyGetter = null; \n";
		} else {
			$c .= "	  throw new OutletException(\"You can not set this to NULL since this relationship has not been marked as optional\"); \n";
		}

		$c .= "	  return parent::$setter(null); \n";
		$c .= "	} \n";

		//$c .= "	\$mapped = new OutletMapper(\$ref); \n";
		//$c .= "	\$this->$key = \$mapped->getPK(); \n";
		if ($config->getLocalUseGettersAndSetters())
			$c .= "	\$this->set$key(\$ref->{$refKey}); \n";
		else
			$c .= "	\$this->$key = \$ref->{$refKey}; \n";
		$c .= "	return parent::$setter(\$ref); \n";
		$c .= "  } \n";

		return $c;
	}

}

