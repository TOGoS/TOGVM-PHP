<?php

class TOGoS_TOGES_NumberLiteralSymbolTable extends TOGoS_TOGVM_FakeArray
{
	const NUMBER_REGEX = '/^\d+$/';

	public function offsetExists($k) {
		return preg_match(self::NUMBER_REGEX, $k);
	}
	public function offsetGet($k) {
		return ['classUri'=>'http://ns.nuke24.net/TOGVM/Expressions/LiteralInteger', 'literalValue'=>(int)$k];
	}
}
