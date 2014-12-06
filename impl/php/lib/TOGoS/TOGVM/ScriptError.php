<?php

class TOGoS_TOGVM_ScriptError extends Exception
{
	protected $details;
	protected $scriptTrace;
	public function __construct( $details, array $trace, $cause=null ) {
		if( is_scalar($details) ) $details = array('message'=>$details);
		parent::__construct($details['message'], 0, $cause);
		$this->details = $details;
		$this->scriptTrace = $trace;
	}
}
