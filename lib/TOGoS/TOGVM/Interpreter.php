<?php

class TOGoS_TOGVM_Interpreter
{
	protected $config;
	
	public function __construct( $config ) {
		$this->config = $config;
	}
	
	public function evaluate( array $ast, array $ctx ) {
		switch( $ast['classUri'] ) {
		case 'http://ns.nuke24.net/TOGVM/Expressions/LiteralString':
			return (string)$ast['literalValue'];
		case 'http://ns.nuke24.net/TOGVM/Expressions/LiteralInteger':
			return (int)$ast['literalValue'];
		case 'http://ns.nuke24.net/TOGVM/Expressions/LiteralNumber':
			return (float)$ast['literalValue'];
		case 'http://ns.nuke24.net/TOGVM/Expressions/LiteralBoolean':
			if( $ast['literalValue'] === 'true' ) return true;
			return (bool)$ast['literalValue'];
		case 'http://ns.nuke24.net/TOGVM/Expressions/Variable':
			return $ctx['variableResolver'][$ast['variableName']];
		case 'http://ns.nuke24.net/TOGVM/Expressions/FunctionApplication':
			if( isset($ast['function']) ) {
				$function = $this->evaluate($ast['function'], $ctx);
			} else if( isset($ast['functionUri']) ) {
				if( isset($this->config['functions'][$ast['functionUri']]) ) {
					$function = $this->config['functions'][$ast['functionUri']];
				} else {
					throw new Exception("No function defined for '{$ast['functionUri']}'");
				}
			} else {
				throw new Exception("ApplyFunction expression does not define 'function' or 'functionUri'");
			}
			$argumentValues = array();
			foreach( $ast['arguments'] as $k=>$argument ) {
				$argumentValues[$k] = $this->evaluate($argument, $ctx);
			}
			return call_user_func($function, $argumentValues);
		default:
			throw new Exception("Unrecognized expression class URI: '{$ast['classUri']}'");
		}
	}
}
