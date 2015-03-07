<?php

class TOGoS_TOGVM_Interpreter
{
	protected $config;
	
	public function __construct( $config ) {
		$this->config = $config;
	}
	
	public function evaluate( array $ast, array $ctx ) {
		switch( $ast['classRef'] ) {
		case 'http://ns.nuke24.net/TOGVM/Expressions/LiteralString':
			return (string)$ast['literalValue'];
		case 'http://ns.nuke24.net/TOGVM/Expressions/LiteralInteger':
			return (int)$ast['literalValue'];
		case 'http://ns.nuke24.net/TOGVM/Expressions/LiteralNumber':
			return (float)$ast['literalValue'];
		case 'http://ns.nuke24.net/TOGVM/Expressions/LiteralBoolean':
			$v = $ast['literalValue'];
			if( is_bool($v) ) return $v;
			if( $v === 'true' ) return true;
			if( $v === 'false' ) return false;
			if( $v === 0 or $v === '0' ) return false;
			if( $v === 1 or $v === '1' ) return true;
			throw new Exception("Unrecognized representation of literal boolean: {$v}");
		case 'http://ns.nuke24.net/TOGVM/Expressions/Variable':
			return $ctx['variableResolver'][$ast['variableName']];
		case 'http://ns.nuke24.net/TOGVM/Expressions/FunctionApplication':
			if( isset($ast['function']) ) {
				$function = $this->evaluate($ast['function'], $ctx);
			} else if( isset($ast['functionRef']) ) {
				if( isset($this->config['functions'][$ast['functionRef']]) ) {
					$function = $this->config['functions'][$ast['functionRef']];
				} else {
					throw new Exception("No function defined for '{$ast['functionRef']}'");
				}
			} else {
				throw new Exception("ApplyFunction expression does not define 'function' or 'functionRef'");
			}
			$argumentValues = array();
			foreach( $ast['arguments'] as $k=>$argument ) {
				$argumentValues[$k] = $this->evaluate($argument, $ctx);
			}
			return call_user_func($function, $argumentValues);
		default:
			throw new Exception("Unrecognized expression class URI: '{$ast['classRef']}'");
		}
	}
}
