<?php

class TOGoS_TOGVM_Actionlet_InvokeProcedure implements TOGoS_TOGVM_Actionlet
{
	public function __construct( $proc, array $args ) {
		$this->proc = $proc;
		$this->args = $args;
	}
	
	public function __invoke( array $c ) {
		return call_user_func_array( $this->proc, $this->args );
	}
}
