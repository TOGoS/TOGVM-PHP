<?php

class TOGoS_TOGVM_TestUtil
{
	protected static function stripSourceLocations(array $arr) {
		$rez = array();
		foreach($arr as $k=>$v) {
			// Triple-equal because 0 == "sourceLocation".  Oy.
			if( $k === 'sourceLocation' ) continue;
			if( is_array($v) ) {
				$rez[$k] = self::stripSourceLocations($v);
			} else {
				$rez[$k] = $v;
			}
		}
		return $rez;
	}
	
	protected static function containsSourceLocations(array $arr) {
		foreach($arr as $k=>$v) {
			// Triple-equal because 0 == "sourceLocation".  Oy.
			if( $k === 'sourceLocation' ) return true;
			if( is_array($v) && self::containsSourceLocations($v) ) return true;
		}
		return false;
	}
	
	// Strip out all 'sourceLocation' array entries unless any are present in both $a and $b
	public static function matchSourceLocateyness(array &$a, array &$b) {
		if( self::containsSourceLocations($a) and self::containsSourceLocations($b) ) return;
		
		$a = self::stripSourceLocations($a);
		$b = self::stripSourceLocations($b);
	}
}
