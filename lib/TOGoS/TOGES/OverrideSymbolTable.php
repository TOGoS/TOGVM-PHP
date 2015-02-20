<?php

class TOGoS_TOGES_OverrideSymbolTable extends TOGoS_TOGVM_FakeArray
{
	protected $tables;
	
	public function __construct( $tables ) {
		$this->tables = $tables;
	}
	
	public function offsetExists($k) {
		foreach( $this->tables as $t ) {
			if( isset($t[$k]) ) return true;
		}
		return false;
	}

	public function offsetGet($k) {
		foreach( $this->tables as $t ) {
			if( isset($t[$k]) ) return $t[$k];
		}
		return null;
	}
}
