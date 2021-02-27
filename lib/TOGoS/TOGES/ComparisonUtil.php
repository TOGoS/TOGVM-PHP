<?php

/**
 * @api
 */
class TOGoS_TOGES_ComparisonUtil
{
	public static function isComparisonOp( array $expr ) {
		if( $expr['classRef'] != 'http://ns.nuke24.net/TOGVM/Expression/FunctionApplication' ) return false;
		
		switch( $expr['functionRef'] ) {
		case 'http://ns.nuke24.net/TOGVM/Functions/IsGreaterThanOrEqualTo':
		case 'http://ns.nuke24.net/TOGVM/Functions/IsGreaterThan':
		case 'http://ns.nuke24.net/TOGVM/Functions/AreEqual':
		case 'http://ns.nuke24.net/TOGVM/Functions/AreNotEqual':
		case 'http://ns.nuke24.net/TOGVM/Functions/IsLesserThan':
		case 'http://ns.nuke24.net/TOGVM/Functions/IsLesserThanOrEqualTo':
			return true;
		}
	}
	
	protected static function collectComparisonOps( array $expr, array &$toBeAnded, &$firstValueExpr=null, &$lastValueExpr=null ) {
		if( !self::isComparisonOp($expr) ) throw new Exception("Not a comparison op!");
		
		$firstValueExpr = $expr['arguments'][0];
		$lastValueExpr = $expr['arguments'][1];

		if( !self::isComparisonOp($firstValueExpr) and !self::isComparisonOp($lastValueExpr) ) {
			// No transform to be done!
			$toBeAnded[] = $expr;
			return;
		}
		
		$leftValueExpr = $firstValueExpr;
		$rightValueExpr = $lastValueExpr;
		
		if( self::isComparisonOp($firstValueExpr) ) {
			self::collectComparisonOps($firstValueExpr, $toBeAnded, $firstValueExpr, $leftValueExpr );
		}
		$toBeAndedRight = array();
		if( self::isComparisonOp($lastValueExpr) ) {
			self::collectComparisonOps($lastValueExpr, $toBeAndedRight, $rightValueExpr, $lastValueExpr );
		}
		
		// Rewrite the middle expression
		$expr['arguments'][0] = $leftValueExpr;
		$expr['arguments'][1] = $rightValueExpr;
		$toBeAnded[] = $expr;
		$toBeAnded = array_merge($toBeAnded, $toBeAndedRight);
	}
	
	/**
	 * In some languages you may want to allow expressions like
	 * 
	 *   a < b = c <= d
	 * 
	 * and have them be equivalent to
	 * 
	 *   a < b and b = c and c <= d
	 *
	 * This function will do that transformation for you.
	 * 
	 * It must be called on the OUTERMOST comparison expression or it won't work!
	 */
	public static function fixMultiComparisonOp( array $expr ) {
		if( !self::isComparisonOp($expr) ) return $expr;
		
		$toBeAnded = array();
		self::collectComparisonOps($expr, $toBeAnded);
		if( count($toBeAnded) == 1 ) return $toBeAnded[0];
		
		return array(
			'classRef' => 'http://ns.nuke24.net/TOGVM/Expression/FunctionApplication',
			'functionRef' => 'http://ns.nuke24.net/TOGVM/Functions/And',
			'arguments' => $toBeAnded,
			'sourceLocation' => isset($expr['sourceLocation']) ? $expr['sourceLocation'] : null,
		);
	}
	
	/**
	 * Call this on your top-level expression
	 */
	public static function fixProgramWithMultiComparisonOps( array $expr, TOGoS_TOGES_ExpressionTransformer $transformer ) {
		return $transformer->transformRecursively( $expr, array(__CLASS__, 'fixMultiComparisonOp'), null );
	}
}
