<?php

class OutletProxyGenerator {
	private $config;

	function __construct (OutletConfig $config) {
		$this->config = $config;
	}

	function getPkProp($conf, $clazz) {
		foreach ($conf['classes'][$clazz]['props'] as $key=>$f) {
			if (@$f[2]['pk'] == true) {
				$pks[] = $key;
			}

			if (!count($pks)) throw new Exception('You must specified at least one primary key');

			if (count($pks) == 1) {
				return $pks[0];
			} else {
				return $pks;
			}
		}
	}

	function generate () {	
		$c = '';
		foreach ($this->config->getEntities() as $entity) {
			$clazz = $entity->getClass();

			$c .= "class {$clazz}_OutletProxy extends $clazz implements OutletProxy { \n";

			foreach ($entity->getAssociations() as $assoc) {
				switch ($assoc->getType()) {
					case 'one-to-many': $c .= $this->createOneToManyFunctions($assoc); break;
					case 'many-to-one': $c .= $this->createManyToOneFunctions($assoc); break;
					default: throw new Exception("invalid association type: {$assoc->getType()}");
				}
			}
			$c .= "} \n";
		}

		return $c;

		/*
		foreach ($conf['classes'] as $clazz => $settings) {
			$c = "";
			$c .= "class {$clazz}_OutletProxy extends $clazz implements OutletProxy { \n";
			if (isset($settings['associations'])) {
				foreach ($settings['associations'] as $assoc) {	
					$type 	= $assoc[0];
					$entity = $assoc[1];

					//$fk_local 	= $assoc[3];

					switch ($type) {
						case 'many-to-one': 
							$key = $assoc[2]['key'];
							$name = (@$assoc[2]['name'] ? $assoc[2]['name'] : $entity);
							$optional = (@$assoc[2]['optional'] ? $assoc[2]['optional'] : false);

							$c .= "  function get$name() { \n";
							$c .= "    if (is_null(\$this->$key)) return parent::get$name(); \n";
							$c .= "    if (is_null(parent::get$name()) && \$this->$key) { \n";
							$c .= "      parent::set$name( Outlet::getInstance()->load('$entity', \$this->$key) ); \n";
							$c .= "    } \n";
							$c .= "    return parent::get$name(); \n";
							$c .= "  } \n";
							if ($optional) {
								$c .= "  function set$name($entity \$ref=null) { \n";
								$c .= "    if (is_null(\$ref)) { \n";
								$c .= "      \$this->$key = null; \n";
								$c .= "      return parent::set$name(null); \n";
								$c .= "    } \n";
							} else {
								$c .= "  function set$name($entity \$ref) { \n";
							}
							$c .= "    \$mapped = new OutletMapper(\$ref); \n";
							$c .= "    \$this->$key = \$mapped->getPK(); \n";
							$c .= "    return parent::set$name(\$ref); \n";
							$c .= "  } \n";
							break;
						case 'one-to-many':
							$c .= $this->createOneToManyFunctions($clazz, $assoc[1], $assoc[2]);
							break;
						case 'many-to-many':
							$key_column = $assoc[2]['key_column'];
							$ref_column = $assoc[2]['ref_column'];
							$table = $assoc[2]['table'];
							$name = (@$assoc[2]['name'] ? $assoc[2]['name'] : $entity);
							$pkprop = OutletMapper::getPkProp($clazz);
							$refpkprop = OutletMapper::getPkProp($entity);

							// use the plural setting or add an 's' if plural is not defined
							$plural = (isset($conf['classes'][$entity]['plural'])) ? $conf['classes'][$entity]['plural'] : "{$entity}s";

							$c .= "  function get{$plural}() { \n";
							$c .= "    \$q = \" \n";
							$c .= "      INNER JOIN $table ON $table.$ref_column = {"."$entity.$refpkprop} \n";
							$c .= "      INNER JOIN {"."$clazz e} ON $table.$key_column = {e.$pkprop} \n";
							$c .= "    \"; \n";

							$c .= "    parent::set{$plural}( Outlet::getInstance()->select('$entity', \$q) ); \n";

							$c .= "    return parent::get{$plural}(); \n";
							$c .= "  } \n";	
							break;	
					}
				}
			}
			$c .= "} \n";

			$s .= $c;
		}
		return $s;
		*/
	}

	function createOneToManyFunctions (OutletAssociationConfig $config) {
		$foreign	= $config->getForeign();
		$key 		= $config->getKey();
		$pk_prop 	= $config->getRefKey();
		$getter		= $config->getGetter();
		$setter		= $config->getSetter();
	
		$c = '';	
		$c .= "  function {$getter}() { \n";
		$c .= "    \$args = func_get_args(); \n";
		$c .= "    if (count(\$args)) { \n";
		$c .= "      if (is_null(\$args[0])) return parent::{$getter}(); \n";
		$c .= "      \$q = \$args[0]; \n";
		$c .= "    } else { \n";
		$c .= "      \$q = ''; \n";
		$c .= "    } \n";

		//$c .= "      if (\$q===false) return parent::get$prop(); \n";
		
		// if there's a where clause
		$c .= "    if (stripos(\$q, 'where') !== false) { \n";
		$c .= "      \$q = 'where {"."$foreign.$key} = '.\$this->$pk_prop.' and ' . substr(\$q, 5); \n";
		$c .= "    } else { \n";
		$c .= "      \$q = 'where {"."$foreign.$key} = '.\$this->$pk_prop. ' ' . \$q; \n";
		$c .= "    }\n";
		$c .= "    parent::{$setter}( Outlet::getInstance()->select('$foreign', \$q) ); \n";
		/** not sure if i need this
		$c .= "    if (!count(parent::get{$entity}s())) { \n";
		$c .= "      \$this->$prop = Outlet::getInstance()->select('$entity', 'where $entity.$fk_foreign = '.\$this->$fk_local); \n";
		$c .= "    } \n";
		*/
		$c .= "    return parent::{$getter}(); \n";
		$c .= "  } \n";

		return $c;
	}

	function createManyToOneFunctions (OutletAssociationConfig $config) {
		$local		= $config->getLocal();
		$foreign	= $config->getForeign();
		$key 		= $config->getKey();
		$refKey		= $config->getRefKey();
		$getter 	= $config->getGetter();
		$setter		= $config->getSetter();

		$c = '';
		$c .= "  function $getter() { \n";
		$c .= "    if (is_null(\$this->$key)) return parent::$getter(); \n";
		$c .= "    if (is_null(parent::$getter()) && \$this->$key) { \n";
		$c .= "      parent::$setter( Outlet::getInstance()->load('$foreign', \$this->$key) ); \n";
		$c .= "    } \n";
		$c .= "    return parent::$getter(); \n";
		$c .= "  } \n";

		$c .= "  function $setter($foreign \$ref=null) { \n";
		$c .= "    if (is_null(\$ref)) { \n";

		if ($config->isOptional()) {
			$c .= "      \$this->$key = null; \n";
		} else {
			$c .= "      throw new OutletException(\"You can not set this to NULL since this relationship has not been marked as optional\"); \n";
		}

		$c .= "      return parent::$setter(null); \n";
		$c .= "    } \n";

		//$c .= "    \$mapped = new OutletMapper(\$ref); \n";
		//$c .= "    \$this->$key = \$mapped->getPK(); \n";
		$c .= "    \$this->key = \$ref->{$refKey}; \n";
		$c .= "    return parent::$setter(\$ref); \n";
		$c .= "  } \n";

		return $c;
	}

}

