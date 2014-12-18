<?php

class TOGoS_TOGVM_Actionlet_Echo implements TOGoS_TOGVM_Actionlet
{
	public function __construct( $output ) {
		$this->output = $output;
	}
	
	public function __invoke( array $c ) {
		call_user_func( $c['standardOutputFunction'], $this->output );
	}
}
