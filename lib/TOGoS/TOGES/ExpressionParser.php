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
			$ast['operatorSymbol'] == ',' || $ast['operatorSymbol'] === $homogeneousOperandOperatorName
		) {
			return array_merge(
				$this->parseArgumentList($ast['operands']['left'], $homogeneousOperandOperatorName),
				$this->parseArgumentList($ast['operands']['right'], $homogeneousOperandOperatorName)
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
				'literalValue' => $ast['value'],
				'sourceLocation' => $ast['sourceLocation'],
			);
		case 'phrase':
			$text = implode(' ',$ast['words']);
			if( !isset($this->wordExpansions[$text]) ) {
				throw new TOGoS_TOGVM_CompileError("Undefined symbol: '$text'", array($ast['sourceLocation']));
			}
			$expr = $this->wordExpansions[$text];
			$expr['sourceLocation'] = $ast['sourceLocation'];
			return $expr;
		case 'operation':
			if( !isset($this->operators[$ast['operatorSymbol']]) ) {
				throw new Exception("Unrecognized operator: '{$ast['operatorSymbol']}'");
			}
			$ods = implode(',',array_keys($ast['operands']));
			$operator = $this->operators[$ast['operatorSymbol']];
			$functionUri = null;
			if( $ods == 'inner' and isset($operator['circumfixFunctionUri']) ) {
				// Look for 1-ary function (circumfix or prefix)
				$functionUri = $operator['circumfixFunctionUri'];
			} else if( $ods == 'right' and isset($operator['prefixFunctionUri']) ) {
				// Look for 1-ary function (circumfix or prefix)
				$functionUri = $operator['prefixFunctionUri'];
			} else if( $ods == 'left,right' and isset($operator['infixFunctionUri']) ) {
				$functionUri = $operator['infixFunctionUri'];
			} else {
				throw new Exception("Don't know how to convert ".count($ast['operands'])."-ary ($ods) ".$ast['operatorSymbol']." AST node to expression");
			}
			
			$arguments = array();
			
			if( count($ast['operands'] == 1) and $functionUri == 'http://ns.nuke24.net/TOGVM/Functions/Identity' ) {
				foreach( $ast['operands'] as $oper ) return $this->astToExpression($oper);
			}
			
			if( $ods == 'left,right' ) {
				if( !empty($operator['homogeneousOperands']) ) {
					$argumentList = $this->parseArgumentList($ast, $ast['operatorSymbol']);
				} else {
					$argumentList = [];
					foreach( $ast['operands'] as $oper ) {
						$argumentList[] = $this->astToExpression($oper);
					}
				}
				foreach( $argumentList as $k=>$arg ) {
					if( is_string($k) ) $arguments[$k] = $arg;
					else $arguments[] = $arg;
				}
			} else {
				throw new Exception("Don't know how to deal with this: $functionUri, $ods");
			}
			return array(
				'classUri' => 'http://ns.nuke24.net/TOGVM/Expressions/FunctionApplication',
				'functionUri' => $functionUri,
				'arguments' => $arguments,
				'sourceLocation' => $ast['sourceLocation'],
			);
		default:
			throw new Exception("Don't yet know how to compile AST nodes of type '{$ast['type']}'");
		}
	}
	
	public function sourceToExpression($source, array $sourceLocation) {
		$tokens = TOGoS_TOGES_Tokenizer::tokenize($source, $sourceLocation);
		
		$parserConfig = [
			'operators'         => $this->operators,
			'flushingOperators' => ["\n"]
		];
		$ast = TOGoS_TOGES_Parser::tokensToAst($tokens, $sourceLocation, $parserConfig);
		
		return $this->astToExpression($ast);
	}
}
