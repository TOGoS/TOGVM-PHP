<?php

class TOGoS_TOGES_ASTSimplifier
{
	protected static function _simplify( array $ast, array $rules ) {
		if( $ast['type'] == 'operation' ) {
			$simplifiedOperands = [];
			foreach( $ast['operands'] as $k=>$operand ) {
				$simplifiedOperands[$k] = self::_simplify($operand, $rules);
			}
			$p = implode(',',array_keys($simplifiedOperands));
			if( isset($rules['ignoreUnaryOperators'][$p][$ast['operatorSymbol']]) ) {
				foreach( $simplifiedOperands as $s ) return $s;
			}
			$ast['operands'] = $simplifiedOperands;
			return $ast;
		} else {
			return $ast;
		}
	}
	
	protected static function simplificationRules( array $languageConfig ) {
		$rules = ['ignoreUnaryOperators'=>['left'=>[]],['right'=>[]]];
		foreach( $languageConfig['operators'] as $oper ) {
			if( isset($oper['symbol']) ) {
				foreach( ['prefix'=>'right', 'postfix'=>'left'] as $fixity=>$operandPosition ) {
					if(
						isset($oper["{$fixity}Meaning"]) and
						$oper["{$fixity}Meaning"] == 'statement-delimiter' ||
						$oper["{$fixity}Meaning"] == 'ignore'
					) {
						$rules['ignoreUnaryOperators'][$operandPosition][$oper['symbol']] = $oper['symbol'];
					}
				}
			}
		}
		return $rules;
	}
	
	public static function simplify( array $ast, array $languageConfig ) {
		return self::_simplify($ast, self::simplificationRules($languageConfig));
	}
}
