<?php

class TOGoS_TOGES_ParserTest extends TOGoS_TOGVM_MultiTestCase
{	
	protected function getTestVectorSubdirectoryName() { return 'ast'; }
	protected function getTestVectorExtensions() { return array('txt','json'); }

	public function _testParse( array $expectedAst, $source, $sourceFile ) {
		$this->setName("Parse $sourceFile");
		$beginSourceLocation = array('filename'=>$sourceFile, 'lineNumber'=>1, 'columnNumber'=>1);
		$endSourceLocation = $beginSourceLocation;
		$tokens = TOGoS_TOGES_Tokenizer::tokenize($source, $endSourceLocation);
		
		$parserConfig = array(
			'operators'         => TOGoS_TOGES_Parser::getDefaultOperators(),
			'flushingOperators' => array("\n"));
		$actualAst = TOGoS_TOGES_Parser::tokensToAst($tokens, array_merge($beginSourceLocation,array(
			'endLineNumber' => $endSourceLocation['lineNumber'],
			'endColumnNumber' => $endSourceLocation['columnNumber']
		)), $parserConfig);
		
		TOGoS_TOGVM_TestUtil::matchSourceLocateyness($expectedAst, $actualAst);
		
		$this->assertEquals($expectedAst, $actualAst,
			"{$sourceFile} didn't parse right;\n".
			"parsed\n\n  ".str_replace("\n","\n  ",$source)."\n".
			"AST = ".EarthIT_JSON::prettyEncode($actualAst));
	}
	
	public function _testFilePair($source, $sourceFile, $expectedAstJson, $expectedAstJsonFile) {
		$this->_testParse(EarthIT_JSON::decode($expectedAstJson), $source, basename($sourceFile));
	}
}
