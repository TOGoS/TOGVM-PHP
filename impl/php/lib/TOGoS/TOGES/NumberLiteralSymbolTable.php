<?php

class TOGoS_TOGES_NumberLiteralSymbolTable extends TOGoS_TOGVM_FakeArray
{
	const BOOLEAN_REGEX = 'true|false';
	const INTEGER_REGEX = '[+-]?\d+';
	const NUMBER_REGEX = '[+-]?\d+(?:\.\d+)?';
	
	public function offsetExists($k) {
		return preg_match('/^(?:'.self::INTEGER_REGEX.'|'.self::NUMBER_REGEX.'|'.self::BOOLEAN_REGEX.')$/', $k);
	}
	public function offsetGet($k) {
		if( preg_match('/^'.self::BOOLEAN_REGEX.'$/', $k) ) {
			return ['classUri'=>'http://ns.nuke24.net/TOGVM/Expressions/LiteralBoolean', 'literalValue'=>$k == 'true'];
		} else if( preg_match('/^'.self::INTEGER_REGEX.'$/', $k) ) {
			return ['classUri'=>'http://ns.nuke24.net/TOGVM/Expressions/LiteralInteger', 'literalValue'=>(int)$k];
		} else if( preg_match('/^'.self::NUMBER_REGEX.'$/', $k) ) {
			return ['classUri'=>'http://ns.nuke24.net/TOGVM/Expressions/LiteralNumber', 'literalValue'=>(float)$k];
		} else {
			throw new Exception("'$k' doesn't parse as a number or boolean");
		}
	}
}
