<?php

class TOGoS_TOGES_HashURNSymbolTable extends TOGoS_TOGVM_FakeArray
{
	public function offsetExists($k) {
		return preg_match('/^urn:(?:sha1|bitprint):([A-Z2-7]{32})/',$k);
	}
	public function offsetGet($k) {
		if( preg_match('/^urn:(?:sha1|bitprint):([A-Z2-7]{32})/',$k) ) {
			return [
				'classRef' => 'http://ns.nuke24.net/TOGVM/Expressions/FunctionApplication',
				'functionRef' => 'http://ns.nuke24.net/TOGVM/Functions/ResolveHashURN',
				'arguments' => [[
					'classRef' => 'http://ns.nuke24.net/TOGVM/Expressions/LiteralString',
					'literalValue' => $k
				]]
			];
		}
	}
}
