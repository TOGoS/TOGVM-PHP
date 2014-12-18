<?php

interface TOGoS_TOGVM_Compiler
{
	/**
	 * Given an expression AST,
	 * Return a function that, when called using call_user_func($ast, $context)
	 * returns the value of the expression represented by the AST.
	 * 
	 * (Meaning of $context not yet figured)
	 */
	public function compileExpression( array $ast );
	
	// Return a function representing the action returned by $ast?
	// public function compileActionExpression( array $ast, $context );
}
