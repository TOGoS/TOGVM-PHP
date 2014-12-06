<?php

class TOGoS_TOGVM_ParserTest extends PHPUnit_Framework_TestCase
{
	public function _testParse( array $expectedAst, $source, $sourceFilename ) {
		$beginSourceLocation = array('filename'=>$sourceFilename, 'lineNumber'=>1, 'columnNumber'=>1);
		$endSourceLocation = $beginSourceLocation;
		$tokens = TOGoS_TOGVM_Tokenizer::tokenize($source, $endSourceLocation);
		
		$parserConfig = array(
			'infixOperators'=>TOGoS_TOGVM_Parser::getDefaultInfixOperators(),
			'flushingOperators'=>array("\n"));
		$actualAst = TOGoS_TOGVM_Parser::tokensToAst($tokens, array_merge($beginSourceLocation,array(
			'endLineNumber' => $endSourceLocation['lineNumber'],
			'endColumnNumber' => $endSourceLocation['columnNumber']
		)), $parserConfig);
		$this->assertEquals($expectedAst, $actualAst);
	}
	
	public function testParse() {
		$astDir = __DIR__.'/../../../../../test-vectors/ast';
		$dh = opendir($astDir);
		while( ($fn = readdir($dh)) !== false ) {
			if( preg_match('/(.*)\.json$/', $fn, $bif) ) {
				$astJsonFile = $astDir."/".$fn;
				$sourceFile = $astDir."/".$bif[1].".txt";
				$astJson = file_get_contents($astJsonFile);
				$source = file_get_contents($sourceFile);
				$expected = EarthIT_JSON::decode($astJson);
				$this->_testParse($expected, $source, $sourceFile);
			}
		}
		closedir($dh);
	}
}

