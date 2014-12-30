<?php

class TOGoS_TOGES_ExpressionParserTest extends TOGoS_TOGVM_MultiTestCase
{
	protected function getTestVectorSubdirectoryName() { return 'expressions'; }
	protected function getTestVectorExtensions() { return ['txt','json']; }
	
	protected function parseAst($source, $sourceFile) {
		$sourceLocation = ['filename'=>$sourceFile, 'lineNumber'=>1, 'columnNumber'=>1];
		$tokens = TOGoS_TOGES_Tokenizer::tokenize($source, $sourceLocation);
		
		$parserConfig = [
			'operators'         => TOGoS_TOGES_Parser::getDefaultOperators(),
			'flushingOperators' => ["\n"]
		];
		return TOGoS_TOGES_Parser::tokensToAst($tokens, $sourceLocation, $parserConfig);
	}

	protected function parseExpression($source, $sourceFile) {
		$ast = $this->parseAst($source, basename($sourceFile));
		
		$symbolTable = new TOGoS_TOGES_OverrideSymbolTable(
			['forty two' => ['classUri'=>'http://ns.nuke24.net/TOGVM/Expressions/LiteralInteger','literalValue'=>42]],
			new TOGoS_TOGES_NumberLiteralSymbolTable(),
			new TOGoS_TOGES_VariableSymbolTable()
		);
		
		$expressionParser = new TOGoS_TOGES_ExpressionParser(
			TOGoS_TOGES_Parser::getDefaultOperators(),
			$symbolTable
		);
		return $expressionParser->astToExpression($ast);
	}
	
	public function _testFilePair($source, $sourceFile, $expectedExpressionJson, $expectedExpressionJsonFile) {
		$expectedExpression = EarthIT_JSON::decode($expectedExpressionJson);
		$this->setName("astToExpression('".basename($sourceFile)."')");
		$expression = $this->parseExpression($source, $sourceFile);
		TOGoS_TOGVM_TestUtil::matchSourceLocateyness($expression, $expectedExpression);
		if( $expression != $expectedExpression ) {
			echo "Expression returned by astToExpression = ", EarthIT_JSON::prettyEncode($expression), "\n";
		}
		$this->assertEquals($expectedExpression, $expression);
	}
}
