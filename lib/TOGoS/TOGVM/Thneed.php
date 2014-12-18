<?php

/**
 * It's an array!
 * It's a callback!
 * It's a blob!
 */
class TOGoS_TOGVM_Thneed extends Nife_AbstractBlob implements ArrayAccess
{
	protected $elements;
	
	public function __construct( array $elements=array() ) {
		$this->elements = $elements;
	}

	public function getElements() {
		return $this->elements;
	}
		
	//// For use as an array
	
	public function append( $element ) {
		$this->elements[] = $element;
	}
	public function offsetExists($k) {
		return isset($this->elements[$k]);
	}
	public function offsetGet($k) {
		return $this->elements[$k];
	}
	public function offsetSet($k, $element) {
		$this->elements[$k] = $element;
	}
	public function offsetUnset($k) {
		unset($this->elements[$k]);
	}
	
	//// For use as a callback

	public function __invoke( $element ) {
		$this->append($element);
	}
	
	protected static function describe($thing) {
		if( gettype($thing) == 'object' ) {
			return get_class($thing);
		} else {
			return gettype($thing);
		}
	}
	
	protected static function strlen($thing) {
		if( is_string($thing) ) return strlen($thing);
		if( $thing instanceof Nife_Blob ) return $thing->getLength();
		throw new Exception("Don't know how to get length of ".self::describe($thing));
	}
	
	protected static function write($thing, $dest) {
		if( is_string($thing) ) call_user_func($dest, $thing);
		if( $thing instanceof Nife_Blob ) $thing->writeTo($dest);
		throw new Exception("Don't know how to write ".self::describe($thing));
	}

	//// For use as a Blob
	
	public function __toString() {
		$str = '';
		foreach( $this->elements as $e ) $str .= (string)$e;
		return $str;
	}
	
	public function getLength() {
		$len = 0;
		foreach( $this->elements as $e ) $len += self::strlen($e);
		return $len;
	}
	
	public function writeTo($dest) {
		foreach( $this->elements as $e ) self::write($e, $dest);
	}
}
