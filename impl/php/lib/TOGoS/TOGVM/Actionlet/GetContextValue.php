<?php

class TOGoS_TOGVM_Actionlet_GetContextValue implements TOGoS_TOGVM_Actionlet
{
	public function __construct( $name ) {
		$this->name = $name;
	}
	
	public function __invoke( array $c ) {
		return $c[$this->name];
	}
}
