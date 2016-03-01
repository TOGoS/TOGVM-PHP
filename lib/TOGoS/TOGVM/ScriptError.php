<?php

class TOGoS_TOGVM_ScriptError extends Exception
{
	protected $details;
	protected $scriptTrace;
	public function __construct( $details, array $trace, $cause=null ) {
		if( is_scalar($details) ) $details = array('message'=>$details);
		$m = $details['message'];
		if( $trace ) $m .= " at ".TOGoS_TOGVM_Util::sourceLocationToString($trace[0]);
		parent::__construct($m, 0, $cause);
		$this->details = $details;
		$this->scriptTrace = $trace;
	}
	
	public function getDetails() { return $this->details; }
	public function getScriptTrace() { return $this->scriptTrace; }
}
