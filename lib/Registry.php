<?php

/*
  08/11/2009
  
  By Steve Fatula of 5 Diamond IT Consulting
  
  This class handles object access and storing
  
*/

abstract class Registry {

protected static $registryCache;

public static function set($key, $value) {
	
	if (!is_object($value)) trigger_error("Only objects are allowed in the registry", E_USER_ERROR);
	if (!is_array(self::$registryCache)) self::$registryCache = array();
	
	self::$registryCache[$key] = $value;
	return TRUE;
}

public static function get($key) {

	if (isset(self::$registryCache[$key])) {
		return self::$registryCache[$key];
	} else {
		trigger_error("Registry object [{$key}] does not exist", E_USER_ERROR);
	}
}

public static function getDB($key) {

	if (isset(self::$registryCache[$key])) {
		return self::$registryCache[$key];
	} else {
		return NULL;
	}
}

}
?>