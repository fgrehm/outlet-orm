<?php

class OutletProxyGenerator {

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

	function generate ($conf) {
		self::validate($conf);
		
		$s = '';
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
							$key = $assoc[2]['key'];
							$pk_prop = OutletMapper::getPkProp($clazz);

							$c .= "  function get{$entity}s() { \n";
							$c .= "    \$args = func_get_args(); \n";
							$c .= "    if (count(\$args)) { \n";
							$c .= "      if (is_null(\$args[0])) return parent::get{$entity}s(); \n";
							$c .= "      \$q = \$args[0]; \n";
							$c .= "    } else { \n";
							$c .= "      \$q = ''; \n";
							$c .= "    } \n";
					
							//$c .= "      if (\$q===false) return parent::get$prop(); \n";
							
							// if there's a where clause
							$c .= "    if (stripos(\$q, 'where') !== false) { \n";
							$c .= "      \$q = 'where {"."$entity.$key} = '.\$this->$pk_prop.' and ' . substr(\$q, 5); \n";
							$c .= "    } else { \n";
							$c .= "      \$q = 'where {"."$entity.$key} = '.\$this->$pk_prop. ' ' . \$q; \n";
							$c .= "    }\n";
							$c .= "    parent::set{$entity}s( Outlet::getInstance()->select('$entity', \$q) ); \n";
							/** not sure if i need this
							$c .= "    if (!count(parent::get{$entity}s())) { \n";
							$c .= "      \$this->$prop = Outlet::getInstance()->select('$entity', 'where $entity.$fk_foreign = '.\$this->$fk_local); \n";
							$c .= "    } \n";
							*/
							$c .= "    return parent::get{$entity}s(); \n";
							$c .= "  } \n";
							break;
						case 'many-to-many':
							$key_column = $assoc[2]['key_column'];
							$ref_column = $assoc[2]['ref_column'];
							$table = $assoc[2]['table'];
							$name = (@$assoc[2]['name'] ? $assoc[2]['name'] : $entity);
							$pkprop = OutletMapper::getPkProp($clazz);
							$refpkprop = OutletMapper::getPkProp($entity);

							$c .= "  function get{$name}s() { \n";
							$c .= "    \$q = \" \n";
							$c .= "      INNER JOIN $table ON $table.$ref_column = {"."$entity.$refpkprop} \n";
							$c .= "      INNER JOIN {"."$clazz e} ON $table.$key_column = {e.$pkprop} \n";
							$c .= "    \"; \n";

							$c .= "    parent::set{$name}s( Outlet::getInstance()->select('$entity', \$q) ); \n";

							$c .= "    return parent::get{$name}s(); \n";
							$c .= "  } \n";	
							break;	
					}
				}
			}
			$c .= "} \n";

			$s .= $c;
		}
		return $s;
	}
	
	static function validate (array $conf) {
		foreach ($conf['classes'] as $cls=>$cls_conf) {
			if (isset($cls_conf['associations'])) {
				foreach ($cls_conf['associations'] as $assoc) {
					if (!isset($assoc[2]['key'])) {
						throw new Exception("Class $cls, association with $assoc[1]: You must specify a key when defining a $assoc[0] relationship");
					}
				}
			}
		}
	}
}

