<?php

/**
 * @api
 */
class TOGoS_TOGES_ExpressionTransformer
{
	public static function identity($thing) {
		return $thing;
	}

	public static function identityFunction( ) {
		return array(__CLASS__,'identity');
	}
	
	protected function transformSubExpressionsRecursively( $expr, $preTransform, $postTransform ) {
		switch( $expr['classRef'] ) {
		case 'http://ns.nuke24.net/TOGVM/Expression/LiteralString':
		case 'http://ns.nuke24.net/TOGVM/Expression/LiteralInteger':
		case 'http://ns.nuke24.net/TOGVM/Expression/LiteralNumber':
		case 'http://ns.nuke24.net/TOGVM/Expression/LiteralBoolean':
		case 'http://ns.nuke24.net/TOGVM/Expression/Variable':
			// No recursion to be done!
			return $expr;
		case 'http://ns.nuke24.net/TOGVM/Expression/FunctionApplication':
			if( isset($expr['function']) ) {
				$expr['function'] = $this->transformRecursively( $expr['function'], $preTransform, $postTransform );
			}
			foreach( $expr['arguments'] as $k=>$argument ) {
				$expr['arguments'][$k] = $this->transformRecursively( $argument, $preTransform, $postTransform );
			}
			return $expr;
		default:
			$unrecognizedProps = [];
			foreach( $expr as $propK=>$propV ) {
				switch( $propK ) {
				case 'classRef': case 'sourceLocation':
					break;
				case 'arguments':
					if( is_array($propV) ) {
						foreach( $propV as $argK=>$arg ) {
							if( isset($arg['classRef']) ) {
								$expr[$propK][$argK] = $this->transformRecursively( $arg, $preTransform, $postTransform );
							} else {
								$allPropertiresTransformable = false;
							}
						}
						break;
					}
				default:
					$unrecognizedProps[] = $propK;
				}
			}
			
			if( count($unrecognizedProps) == 0 ) return $expr; // Hey, we transformed it automatically!
			
			throw new Exception(
				"Unrecognized expression class URI: '{$expr['classRef']}'; ".
				"can't transform automatically because unrecognized properties: ".
				implode(', ',$unrecognizedProps));
		}
	}
	
	/**
	 * Apply a transformation to an expression and all sub-expressions
	 * 
	 * @param callable $preTransform a transformation to be applied ~before~ recursion
	 *   (outermost expression will be transformed first)
	 * @param callable $postTransform a transformation to be applied ~after~ recursion
	 *   (innermost expressions will be transformed first)
	 */
	public function transformRecursively( array $expr, $preTransform=null, $postTransform=null ) {
		if(  $preTransform === null )  $preTransform = self::identityFunction();
		if( $postTransform === null ) $postTransform = self::identityFunction();

		$expr = call_user_func($preTransform, $expr);
		$expr = $this->transformSubExpressionsRecursively( $expr, $preTransform, $postTransform );
		return call_user_func($postTransform, $expr);
	}
}
