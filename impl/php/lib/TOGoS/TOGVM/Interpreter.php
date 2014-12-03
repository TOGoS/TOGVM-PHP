<?php

class TOGoS_TOGVM_Interpreter
{
	protected $actionQueue = array();
	protected $context = array();
	
	public function __construct( array $context=array() ) {
		$this->context = $context;
	}
	
	public function enqueueAction( TOGoS_TOGVM_Action $action ) {
		$this->actionQueue[] = $action;
	}
	
	public function step() {
		$act = array_shift( $this->actionQueue );
		foreach( $act->step($this->context) as $act ) {
			$this->enqueueAction($act);
		}
	}
	
	public function run() {
		while( $this->actionQueue ) {
			$this->step();
		}
	}
}
