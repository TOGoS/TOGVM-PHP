<?php

class TOGoS_TOGES_ExpressionParserTest extends TOGoS_TOGVM_MultiTestCase
{
	protected function getTestVectorSubdirectoryName() { return 'json-expressions'; }
	protected function getTestVectorExtensions() { return array('txt','json'); }
	
	protected function parseAst($source, $sourceFile) {
		$this->setName("Parse $sourceFile");
		$beginSourceLocation = array('filename'=>$sourceFile, 'lineNumber'=>1, 'columnNumber'=>1);
		$endSourceLocation = $beginSourceLocation;
		$tokens = TOGoS_TOGES_Tokenizer::tokenize($source, $endSourceLocation);
		
		$parserConfig = array(
			'operators'         => TOGoS_TOGES_Parser::getDefaultOperators(),
			'flushingOperators' => array("\n"));
		return TOGoS_TOGES_Parser::tokensToAst($tokens, array_merge($beginSourceLocation,array(
			'endLineNumber' => $endSourceLocation['lineNumber'],
			'endColumnNumber' => $endSourceLocation['columnNumber']
		)), $parserConfig);
	}

	protected function parseExpression($source, $sourceFile) {
		$ast = $this->parseAst($source, $sourceFile);
		$expressionParser = new TOGoS_TOGES_ExpressionParser(array_merge(
			TOGoS_TOGES_Parser::getDefaultInfixOperators(),
			TOGoS_TOGES_Parser::getDefaultPrefixOperators()
		));
		//$expression = ::astToExpre
	}
	
	public function _testFilePair($source, $sourceFile, $expectedExpressionJson, $expectedExpressionJsonFile) {
		$this->markTestSkipped('Fuh');
	}
}
