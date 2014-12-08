<?php

class TOGoS_TOGES_OverrideSymbolTable extends TOGoS_TOGVM_FakeArray
{
	protected $parent;
	protected $overrides;
	
	public function __construct($parent, $overrides) {
		$this->parent = $parent;
		$this->overrides = $overrides;
	}
	
	public function offsetExists($k) {
		return isset($this->overrides[$k]) or isset($this->parent[$k]);
	}

	public function offsetGet($k) {
		return isset($this->overrides[$k]) ? $this->overrides[$k] : $this->parent[$k];
	}
}
