<?php

class TOGoS_TOGVM_NormalAction implements TOGoS_TOGVM_Action
{
	protected $actionlet;
	protected $onResult;
	
	public static function emptyActionList() { return array(); }
	
	public function __construct( $actionlet, $onResult=array('TOGoS_TOGVM_NormalAction','emptyActionList') ) {
		$this->actionlet = $actionlet;
		$this->onResult = $onResult;
	}
	
	/** Return the result value of the action */
	public function step( array $c ) {
		$value = call_user_func($this->actionlet, $c);
		return call_user_func($this->onResult, $value);
	}
	
	public function onResult( $onResult ) {
		if( $this->onResult === array('TOGoS_TOGVM_NormalAction','emptyActionList') ) {
			return new TOGoS_TOGVM_NormalAction( $this->actionlet, $onResult );
		}
		
		$myOnResult = $this->onResult;
		$newOnResult = function($r) use ($myOnResult, $onResult) {
			$myNexts = call_user_func($myOnResult, $r);
			$nexts = array();
			foreach( $nexts as $n ) {
				$nexts[] = $n->onResult($onResult);
			}
			return $nexts;
		};
		return new TOGoS_TOGVM_NormalAction( $this->actionlet, $newOnResult );
	}
	
	public function andThen( array $thens ) {
		return $this->onResult( function($_) { return $thens; } );
	}
}
