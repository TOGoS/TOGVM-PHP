<?php

class TOGoS_TOGVM_FakeArray implements ArrayAccess
{
	public function offsetExists($k) { return false; }
	public function offsetGet($k) { throw new Exception(); }
	public function offsetSet($k,$v) { throw new Exception(); }
	public function offsetUnset($k) { throw new Exception(); }
}
