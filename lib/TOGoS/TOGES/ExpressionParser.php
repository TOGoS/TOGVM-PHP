<?php

class TOGoS_TOGES_ExpressionParser
{
	protected $languageConfig;
	protected $wordExpansions;
	protected $operatorsBySymbol = [];
	
	public function __construct(array $languageConfig, $wordExpansions) {
		$this->languageConfig = $languageConfig;
		$this->wordExpansions = $wordExpansions;
		foreach( $this->languageConfig['operators'] as $oper ) {
			if( isset($oper['symbol'])) {
				$this->operatorsBySymbol[$oper['symbol']] = $oper;
			} else if( isset($oper['openBracket']) ) {
				$this->operatorsBySymbol[$oper['openBracket']] = $oper;
			} else {
				throw new Exception("Unrecognized operator type in language config: ".json_encode($oper));
			}
		}
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
				'classRef' => 'http://ns.nuke24.net/TOGVM/Expressions/LiteralString',
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
			if( !isset($this->operatorsBySymbol[$ast['operatorSymbol']]) ) {
				throw new Exception("Unrecognized operator: '{$ast['operatorSymbol']}'");
			}
			$ods = implode(',',array_keys($ast['operands']));
			$operator = $this->operatorsBySymbol[$ast['operatorSymbol']];
			$functionRef = null;
			if( $ods == 'inner' and isset($operator['circumfixMeaning']['functionRef']) ) {
				// Look for 1-ary function (circumfix or prefix)
				$functionRef = $operator['circumfixMeaning']['functionRef'];
			} else if( $ods == 'right' and isset($operator['infixMeaning']['functionRef']) ) {
				// Look for 1-ary function (circumfix or prefix)
				$functionRef = $operator['infixMeaning']['functionRef'];
			} else if( $ods == 'left,right' and isset($operator['infixMeaning']['functionRef']) ) {
				$functionRef = $operator['infixMeaning']['functionRef'];
			} else {
				throw new Exception("Don't know how to convert ".count($ast['operands'])."-ary '".$ast['operatorSymbol']."'($ods) AST node to expression");
			}
			
			$arguments = array();
			
			if( count($ast['operands'] == 1) and $functionRef == 'http://ns.nuke24.net/TOGVM/Functions/Identity' ) {
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
				throw new Exception("Don't know how to deal with this: $functionRef, $ods");
			}
			return array(
				'classRef' => 'http://ns.nuke24.net/TOGVM/Expressions/FunctionApplication',
				'functionRef' => $functionRef,
				'arguments' => $arguments,
				'sourceLocation' => $ast['sourceLocation'],
			);
		default:
			throw new Exception("Don't yet know how to compile AST nodes of type '{$ast['type']}'");
		}
	}
	
	public function sourceToExpression($source, array $sourceLocation) {
		$tokens = TOGoS_TOGES_Tokenizer::tokenize($source, $sourceLocation);
		
		$ast = TOGoS_TOGES_Parser::tokensToAst($tokens, $sourceLocation, $this->languageConfig);
		
		return $this->astToExpression($ast);
	}
}
