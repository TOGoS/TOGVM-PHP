<?php

class TOGoS_TOGVM_ParserTest extends PHPUnit_Framework_TestCase
{
	protected static function stripSourceLocations(array $arr) {
		$rez = array();
		foreach($arr as $k=>$v) {
			// Triple-equal because 0 == "sourceLocation".  Oy.
			if( $k === 'sourceLocation' ) continue;
			if( is_array($v) ) {
				$rez[$k] = self::stripSourceLocations($v);
			} else {
				$rez[$k] = $v;
			}
		}
		return $rez;
	}
	
	protected static function containsSourceLocations(array $arr) {
		foreach($arr as $k=>$v) {
			// Triple-equal because 0 == "sourceLocation".  Oy.
			if( $k === 'sourceLocation' ) return true;
			if( is_array($v) && self::containsSourceLocations($v) ) return true;
		}
		return false;
	}
	
	public function _testParse( array $expectedAst, $source, $sourceFilename ) {
		$this->setName("Parse $sourceFilename");
		$beginSourceLocation = array('filename'=>$sourceFilename, 'lineNumber'=>1, 'columnNumber'=>1);
		$endSourceLocation = $beginSourceLocation;
		$tokens = TOGoS_TOGVM_Tokenizer::tokenize($source, $endSourceLocation);
		
		$parserConfig = array(
			'infixOperators'    => TOGoS_TOGVM_Parser::getDefaultInfixOperators(),
			'prefixOperators'   => TOGoS_TOGVM_Parser::getDefaultPrefixOperators(),
			'brackets'          => TOGoS_TOGVM_Parser::getDefaultBrackets(),
			'flushingOperators' => array("\n"));
		$actualAst = TOGoS_TOGVM_Parser::tokensToAst($tokens, array_merge($beginSourceLocation,array(
			'endLineNumber' => $endSourceLocation['lineNumber'],
			'endColumnNumber' => $endSourceLocation['columnNumber']
		)), $parserConfig);
		
		if( self::containsSourceLocations($expectedAst) ) {
			$checkAst = $actualAst;
		} else {
			$checkAst = self::stripSourceLocations($actualAst);
		}

		$this->assertEquals($expectedAst, $checkAst,
			"{$sourceFilename} didn't parse right;\n".
			"parsed\n\n  ".str_replace("\n","\n  ",$source)."\n".
			"AST = ".EarthIT_JSON::prettyEncode($checkAst));
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

