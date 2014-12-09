<?php

class TOGoS_TOGES_ExpressionParser
{
	protected $operators;
	protected $wordExpansions;
	
	public function __construct(array $operators, $wordExpansions) {
		$this->operators = $operators;
		$this->wordExpansions = $wordExpansions;
	}
	
	protected function parseArgumentList(array $ast, $homogeneousOperandOperatorName=null) {
		if(
			$ast['type'] == 'operation' and
			$ast['operatorName'] == ',' || $ast['operatorName'] === $homogeneousOperandOperatorName
		) {
			return array_merge(
				$this->parseArgumentList($ast['operands'][0], $homogeneousOperandOperatorName),
				$this->parseArgumentList($ast['operands'][1], $homogeneousOperandOperatorName)
			);
		} else {
			return array($this->astToExpression($ast));
		}
	}
	
	public function astToExpression(array $ast) {
		switch( $ast['type'] ) {
		case 'literal':
			if( !is_string($ast['value']) ) {
				throw new Exception("Only string literals in AST supported; got ".json_encode($ast));
			}
			return array(
				'classUri' => 'http://ns.nuke24.net/TOGVM/Expressions/LiteralString',
				'literalValue' => $ast['value']
			);
		case 'phrase':
			$text = implode(' ',$ast['words']);
			if( !isset($this->wordExpansions[$text]) ) {
				throw new TOGoS_TOGVM_CompileError("Undefined symbol: '$text'", array($ast['sourceLocation']));
			}
			return $this->wordExpansions[$text];
		case 'operation':
			if( !isset($this->operators[$ast['operatorName']]) ) {
				throw new Exception("Unrecognized operator: '{$ast['operatorName']}'");
			}
			$operator = $this->operators[$ast['operatorName']];
			if( isset($operator['functionUri']) ) {
				$arguments = array();
				$arguments[] = $this->astToExpression($ast['operands'][0]);
				if( count($ast['operands']) == 2 ) {
					$argumentList = $this->parseArgumentList(
						$ast['operands'][1],
						!empty($operator['homogeneousOperands']) ? $ast['operatorName'] : null);
					foreach( $argumentList as $k=>$arg ) {
						if( is_string($k) ) $arguments[$k] = $arg;
						else $arguments[] = $arg;
					}
				}
				return array(
					'classUri' => 'http://ns.nuke24.net/TOGVM/Expressions/FunctionApplication',
					'functionUri' => $operator['functionUri'],
					'arguments' => $arguments
				);
			}
			throw new Exception("Don't know how to asdmaklm");
		default:
			throw new Exception("Don't yet know how to compile AST nodes of type '{$ast['type']}'");
		}
	}
	
	public function sourceToExpression($source, array $sourceLocation) {
		$endSourceLocation = $sourceLocation;
		$tokens = TOGoS_TOGES_Tokenizer::tokenize($source, $endSourceLocation);
		
		$parserConfig = [
			'operators'         => $this->operators,
			'flushingOperators' => ["\n"]
		];
		$ast = TOGoS_TOGES_Parser::tokensToAst($tokens, array_merge($sourceLocation, [
			'endLineNumber' => $endSourceLocation['lineNumber'],
			'endColumnNumber' => $endSourceLocation['columnNumber']
		]), $parserConfig);
		
		return $this->astToExpression($ast);
	}
}
