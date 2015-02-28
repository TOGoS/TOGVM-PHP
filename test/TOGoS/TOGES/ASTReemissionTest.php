<?php

class TOGoS_TOGES_ASTReemissionTest extends TOGoS_TOGVM_MultiTestCase
{
	protected function getTestVectorSubdirectoryName() { return 'reemission'; }
	protected function getTestVectorExtensions() { return array('noparens.txt','parens.txt'); }
	
	protected function toSourceWithParens($ast) {
		return "(".$this->toSource($ast).")";
	}
	
	protected $operatorsBySymbol;
	protected function getOperatorBySymbol($symbol) {
		if( $this->operatorsBySymbol === null ) {
			$this->operatorsBySymbol = [];
			$lang = $this->getTestLanguageConfig();
			foreach( $lang['operators'] as $op ) {
				if( $op['type'] == 'bracket-pair' ) {
					$s = $op['openBracket'];
				} else {
					$s = $op['symbol'];
				}
				$this->operatorsBySymbol[$s] = $op;
			}
		}
		return $this->operatorsBySymbol[$symbol];
	}
	
	protected function toSource($ast) {
		switch( $ast['type'] ) {
		case 'literal':
			return '"'.$ast['value'].'"';
		case 'operation':
			$operandSources = [];
			foreach( $ast['operands'] as $k=>$operand ) {
				$operandSources[$k] = $this->toSourceWithParens($operand);
			}
			$operator = $this->getOperatorBySymbol($ast['operatorSymbol']);
			
			switch( $ks = implode(',',array_keys($ast['operands'])) ) {
			case 'left,right':
				return implode(" {$operator['symbol']} ", $operandSources);
			case 'left,inner':
				return $operandSources['left'].$operator['openBracket'].$operandSources['inner'].$operator['closeBracket'];
			case 'inner':
				return $operator['openBracket'].$operandSources['inner'].$operator['closeBracket'];
			default:
				throw new Exception("Don't know how to re-emit operands with keys '$ks': ".json_encode($ast));
			}
		case 'phrase':
			return implode(" ",$ast['words']);
		}
		echo "Don't know how to handle: ".json_encode($ast)."\n";
		exit(1);
	}
	
	public function _testFilePair($noParensSource, $noParensSourceFile, $parensSource, $parensSourceFile) {
		$noParensLines = explode("\n",$noParensSource);
		$parensLines = explode("\n",$parensSource);

		$this->setName("Parse lines from $noParensSourceFile");
		
		if( count($noParensLines) != count($parensLines) ) {
			throw new Exception("Line count of $noParensSourceFile and $parensSourceFile did not match.");
		}
		
		$parserConfig = $this->getTestLanguageConfig();

		$lineNumber = 1;
		for( $i=0; $i<count($noParensLines); ++$i, ++$lineNumber ) {
			$source = $noParensLines[$i];
			if( trim($source) === '' ) continue;
			
			$sourceLocation = array('filename'=>$noParensSourceFile, 'lineNumber'=>$lineNumber, 'columnNumber'=>1);
			$tokens = TOGoS_TOGES_Tokenizer::tokenize($source, $sourceLocation);
			
			$ast = TOGoS_TOGES_Parser::tokensToAst($tokens, $sourceLocation, $parserConfig);
			$reemitted = $this->toSourceWithParens($ast);
			$this->assertEquals( $parensLines[$i], $reemitted, $source." parsed as: ".EarthIT_JSON::prettyEncode($ast) );
		}
	}
}
