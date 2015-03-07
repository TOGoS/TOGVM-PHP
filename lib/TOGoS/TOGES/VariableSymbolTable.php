<?php

class TOGoS_TOGES_VariableSymbolTable extends TOGoS_TOGVM_FakeArray
{
	public function offsetExists($k) {
		return true;
	}
	public function offsetGet($k) {
		return ['classRef'=>'http://ns.nuke24.net/TOGVM/Expressions/Variable', 'variableName'=>$k];
	}
}
