<?php

class TOGoS_TOGES_ParserTest extends TOGoS_TOGVM_MultiTestCase
{	
	protected function getTestVectorSubdirectoryName() { return 'ast'; }
	protected function getTestVectorExtensions() { return array('txt','json'); }

	public function _testParse( array $expectedAst, $source, $sourceFile ) {
		$this->setName("Parse $sourceFile");
		$sourceLocation = array('filename'=>$sourceFile, 'lineNumber'=>1, 'columnNumber'=>1);
		$tokens = TOGoS_TOGES_Tokenizer::tokenize($source, $sourceLocation);
		
		$parserConfig = $this->getTestLanguageConfig();
		$parsedAst = TOGoS_TOGES_Parser::tokensToAst($tokens, $sourceLocation, $parserConfig);
		$simplifiedAst = TOGoS_TOGES_ASTSimplifier::simplify($parsedAst, $parserConfig);
		TOGoS_TOGVM_TestUtil::matchSourceLocateyness($expectedAst, $simplifiedAst);
		
		$this->assertEquals($expectedAst, $simplifiedAst,
			"{$sourceFile} didn't parse right;\n".
			"parsed\n\n  ".str_replace("\n","\n  ",$source)."\n".
			"AST = ".EarthIT_JSON::prettyEncode($simplifiedAst));
	}
	
	public function _testFilePair($source, $sourceFile, $expectedAstJson, $expectedAstJsonFile) {
		$this->_testParse(EarthIT_JSON::decode($expectedAstJson), $source, basename($sourceFile));
	}
}
