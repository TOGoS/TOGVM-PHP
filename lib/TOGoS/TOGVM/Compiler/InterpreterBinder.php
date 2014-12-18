<?php

class TOGoS_TOGVM_Compiler_InterpreterBinder implements TOGoS_TOGVM_Compiler
{
	protected $interp;
	
	public function __construct( TOGoS_TOGVM_Interpreter $interp ) {
		$this->interp = $interp;
	}
	
	public function compileExpression( array $ast ) {
		$interp = $this->interp;
		return function($ctx) use ($ast,$interp) { return $interp->evaluate($ast,$ctx); };
	}
}
