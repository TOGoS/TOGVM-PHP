<?php

/**
 * It's an array!
 * It's a blob!
 * It's a callback!
 */
class TOGoS_TOGVM_Thneed extends Nife_AbstractBlob
{
	protected $elements;
	
	public function __construct( array $elements=array() ) {
		$this->elements = $elements;
	}
	
	public function __invoke( $element ) {
		$this->elements[] = $element;
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
	
	public function __toString() {
		$str = '';
		foreach( $this->elements as $e ) $str .= (string)$e;
		return $str;
	}
	
	public function getElements() {
		return $this->elements;
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
