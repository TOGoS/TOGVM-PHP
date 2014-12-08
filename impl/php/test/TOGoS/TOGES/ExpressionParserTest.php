<?php

class TOGoS_TOGES_ExpressionParserTest extends TOGoS_TOGVM_MultiTestCase
{
	protected function getTestVectorSubdirectoryName() { return 'json-expressions'; }
	protected function getTestVectorExtensions() { return array('txt','json'); }
	
	protected function parseAst($source, $sourceFile) {
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
		$ast = $this->parseAst($source, basename($sourceFile));
		$expressionParser = new TOGoS_TOGES_ExpressionParser(TOGoS_TOGES_Parser::getDefaultOperators());
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
