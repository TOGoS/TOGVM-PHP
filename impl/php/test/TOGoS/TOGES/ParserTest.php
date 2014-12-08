<?php

class TOGoS_TOGES_ParserTest extends PHPUnit_Framework_TestCase
{	
	public function _testParse( array $expectedAst, $source, $sourceFilename ) {
		$this->setName("Parse $sourceFilename");
		$beginSourceLocation = array('filename'=>$sourceFilename, 'lineNumber'=>1, 'columnNumber'=>1);
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
			"{$sourceFilename} didn't parse right;\n".
			"parsed\n\n  ".str_replace("\n","\n  ",$source)."\n".
			"AST = ".EarthIT_JSON::prettyEncode($actualAst));
	}
	
	public function testParse() {
		$astDir = __DIR__.'/../../../../../test-vectors/ast';
		$dh = opendir($astDir);
		$filePairs = array();
		while( ($fn = readdir($dh)) !== false ) {
			if( preg_match('/(.*)\.json$/', $fn, $bif) ) {
				$astJsonFile = $astDir."/".$fn;
				$sourceFile = $astDir."/".$bif[1].".txt";
				$filePairs[$sourceFile] = $astJsonFile;
			}
		}
		closedir($dh);
		
		ksort($filePairs);
		foreach( $filePairs as $sourceFile => $astJsonFile ) {
			$astJson = file_get_contents($astJsonFile);
			$source = file_get_contents($sourceFile);
			$expectedAst = EarthIT_JSON::decode($astJson);
			$sourceName = basename($sourceFile);
			$this->_testParse($expectedAst, $source, $sourceName);
		}
	}
}

