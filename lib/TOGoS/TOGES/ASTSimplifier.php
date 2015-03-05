<?php

class TOGoS_TOGES_ASTSimplifier
{
	protected static function operandNamesToFixity( $names ) {
		if( !is_string($names) ) throw new Exception("Expected \$names to be a comma-separated string.");
		
		switch( $names ) {
		case 'left': return 'postfix';
		case 'right': return 'prefix';
		case 'left,right': return 'infix';
		case 'inner': return 'circumfix';
		case 'left,inner,right': return 'infix';
		case 'left,inner': return 'postfix';
		case 'inner,right': return 'prefix';
		default: throw new Exception("No name for fixity with operand names '$names'");
		}
	}
	
	protected static function _simplify( array $ast, array $rules ) {
		if( $ast['type'] == 'operation' ) {
			$symbol = $ast['operatorSymbol'];
			$operator = $rules['operatorsBySymbol'][$symbol];
			$operandNameStr = implode(',',array_keys($ast['operands']));
			$fixity = self::operandNamesToFixity($operandNameStr);

			// If the operator doesn't have a meaning for the way it is used,
			// we'll make something up for simplificati purposes just
			// to indicate it's not 'ignore' or 'statement-delimiter'.
			$meaning = isset($operator["{$fixity}Meaning"]) ?
				$operator["{$fixity}Meaning"] :
				'something-normal';
			
			$simplifiedOperands = [];
			foreach( $ast['operands'] as $k=>$operand ) {
				$simplifiedOperands[$k] = self::_simplify($operand, $rules);
			}
			
			$nonVoidOperands = [];
			foreach( $simplifiedOperands as $k=>$operand ) {
				if( $operand['type'] != 'void' ) {
					$nonVoidOperands[$k] = $operand;
				}
			}
			
			if( $meaning == 'ignore' || $meaning == 'statement-delimiter' ) {
				if( count($nonVoidOperands) == 0 ) {
					// Then the whole thing's just void.
					foreach($simplifiedOperands as $k=>$operand) {
						return $operand;
					}
				}
				if( count($nonVoidOperands) == 1 ) {
					foreach($nonVoidOperands as $o) {
						return $o;
					}
				}
			}
			
			$ast['operands'] = $simplifiedOperands;
			return $ast;
		} else {
			return $ast;
		}
	}
	
	protected static function simplificationRules( array $languageConfig ) {
		$rules = ['operatorsBySymbol' => []];
		foreach( $languageConfig['operators'] as $oper ) {
			if( isset($oper['symbol']) ) {
				$symbol = $oper['symbol'];
			} else if( isset($oper['openBracket']) ) {
				$symbol = $oper['openBracket'];
			} else {
				throw new Exception("Operator has neither 'symbol' nor 'openBracket': ".json_encode($oper));
			}
			$rules['operatorsBySymbol'][$symbol] = $oper;
		}
		return $rules;
	}
	
	public static function simplify( array $ast, array $languageConfig ) {
		return self::_simplify($ast, self::simplificationRules($languageConfig));
	}
}
